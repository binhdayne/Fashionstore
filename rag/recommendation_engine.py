import threading
import time
from dataclasses import dataclass

import numpy as np
import pandas as pd
import sqlalchemy
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from surprise import Dataset, Reader, SVD


INTERACTION_WEIGHTS = {
    "view": 1.0,
    "add_to_cart": 3.0,
    "purchase": 5.0,
}


@dataclass
class RecommendationResult:
    mode: str
    items: list[dict]
    trained_at: float | None
    matrix_shape: tuple[int, int]


class RecommendationEngine:
    def __init__(self, engine: sqlalchemy.Engine, refresh_interval_seconds: int = 900):
        self.engine = engine
        self.refresh_interval_seconds = refresh_interval_seconds
        self.lock = threading.Lock()
        self.last_trained_at: float | None = None
        self.interactions = pd.DataFrame(columns=["user_id", "product_id", "interaction_type", "timestamp"])
        self.training_frame = pd.DataFrame(columns=["user_id", "product_id", "rating", "timestamp"])
        self.user_item_matrix = pd.DataFrame()
        self.product_features = pd.DataFrame(columns=["product_id", "name", "content", "category_ids"])
        self.product_ids: list[int] = []
        self.user_history: dict[str, list[int]] = {}
        self.popularity_scores: dict[int, float] = {}
        self.product_index: dict[int, int] = {}
        self.tfidf_vectorizer: TfidfVectorizer | None = None
        self.tfidf_matrix = None
        self.svd_model: SVD | None = None
        self.known_users: set[str] = set()
        self.collaborative_ready = False
        self.collaborative_status_reason = "not_trained"

    def ensure_fitted(self, force: bool = False) -> None:
        should_train = force or self.last_trained_at is None
        if not should_train and self.last_trained_at is not None:
            should_train = time.time() - self.last_trained_at > self.refresh_interval_seconds

        if not should_train:
            return

        with self.lock:
            should_train = force or self.last_trained_at is None
            if not should_train and self.last_trained_at is not None:
                should_train = time.time() - self.last_trained_at > self.refresh_interval_seconds

            if not should_train:
                return

            self.interactions = self._load_interactions()
            self.training_frame = self._build_training_frame(self.interactions)
            self.user_item_matrix = self._build_user_item_matrix(self.training_frame)
            self.product_features = self._load_product_features()
            self.product_ids = self.product_features["product_id"].astype(int).tolist()
            self.user_history = self._build_user_history(self.interactions)
            self.popularity_scores = self._build_popularity_scores(self.training_frame)
            self.product_index = {
                int(product_id): index for index, product_id in enumerate(self.product_ids)
            }
            self._fit_content_model()
            self._fit_collaborative_model()
            self.last_trained_at = time.time()

    def predict_top_k(
        self,
        user_id: str,
        top_k: int = 6,
        seed_product_id: int | None = None,
        category_id: int | None = None,
    ) -> RecommendationResult:
        self.ensure_fitted()
        top_k = max(1, top_k)
        user_id = user_id or "guest"
        seen_products = set(self.user_history.get(user_id, []))

        if self.svd_model is not None and user_id in self.known_users:
            collaborative_items = self._predict_collaborative(user_id, top_k, seen_products, category_id)
            if collaborative_items:
                return RecommendationResult(
                    mode="collaborative",
                    items=collaborative_items,
                    trained_at=self.last_trained_at,
                    matrix_shape=self.user_item_matrix.shape,
                )

        seed_ids: list[int] = []
        if seed_product_id:
            seed_ids.append(seed_product_id)
        elif seen_products:
            seed_ids.extend(self.user_history.get(user_id, [])[:3])

        content_items = self._predict_content_based(seed_ids, top_k, seen_products, category_id)
        if content_items:
            return RecommendationResult(
                mode="content_based",
                items=content_items,
                trained_at=self.last_trained_at,
                matrix_shape=self.user_item_matrix.shape,
            )

        return RecommendationResult(
            mode="popular",
            items=self._popular_items(top_k, seen_products, category_id),
            trained_at=self.last_trained_at,
            matrix_shape=self.user_item_matrix.shape,
        )

    def find_similar_products(self, product_id: int, top_k: int = 6) -> list[dict]:
        self.ensure_fitted()
        return self._predict_content_based([product_id], top_k, {product_id}, None)

    def get_status(self) -> dict:
        self.ensure_fitted()
        interaction_breakdown = (
            self.interactions["interaction_type"].value_counts().to_dict()
            if not self.interactions.empty
            else {}
        )
        return {
            "trained_at": self.last_trained_at,
            "interaction_count": int(len(self.interactions.index)),
            "training_rows": int(len(self.training_frame.index)),
            "matrix_shape": list(self.user_item_matrix.shape),
            "product_count": len(self.product_ids),
            "known_users": len(self.known_users),
            "unique_users": int(self.training_frame["user_id"].nunique()) if not self.training_frame.empty else 0,
            "unique_products": int(self.training_frame["product_id"].nunique()) if not self.training_frame.empty else 0,
            "interaction_breakdown": {key: int(value) for key, value in interaction_breakdown.items()},
            "collaborative_ready": self.collaborative_ready,
            "collaborative_status_reason": self.collaborative_status_reason,
        }

    def _load_interactions(self) -> pd.DataFrame:
        query = sqlalchemy.text(
            """
            SELECT
                user_id,
                product_id,
                interaction_type,
                timestamp
            FROM (
                SELECT
                    CASE WHEN re.subtype = 0
                        THEN CONCAT('customer:', re.subject_id)
                        ELSE CONCAT('visitor:', re.subject_id)
                    END AS user_id,
                    re.object_id AS product_id,
                    CASE re.event_type_id
                        WHEN 1 THEN 'view'
                        WHEN 4 THEN 'add_to_cart'
                    END AS interaction_type,
                    re.logged_at AS timestamp
                FROM report_event AS re
                WHERE re.event_type_id IN (1, 4)
                    AND re.object_id > 0
                    AND re.subject_id > 0

                UNION ALL

                SELECT
                    CASE
                        WHEN so.customer_id IS NOT NULL THEN CONCAT('customer:', so.customer_id)
                        WHEN so.customer_email IS NOT NULL AND so.customer_email <> '' THEN CONCAT('guest:', so.customer_email)
                        ELSE CONCAT('order:', so.increment_id)
                    END AS user_id,
                    soi.product_id AS product_id,
                    'purchase' AS interaction_type,
                    so.created_at AS timestamp
                FROM sales_order AS so
                INNER JOIN sales_order_item AS soi ON soi.order_id = so.entity_id
                WHERE soi.parent_item_id IS NULL
                    AND soi.product_id IS NOT NULL
                    AND so.state <> 'canceled'
            ) AS interactions
            WHERE interaction_type IS NOT NULL
            """
        )

        with self.engine.connect() as connection:
            frame = pd.read_sql(query, connection)

        if frame.empty:
            return pd.DataFrame(columns=["user_id", "product_id", "interaction_type", "timestamp"])

        frame["timestamp"] = pd.to_datetime(frame["timestamp"], errors="coerce")
        frame["product_id"] = frame["product_id"].astype(int)

        return frame.dropna(subset=["timestamp"]) 

    def _build_training_frame(self, interactions: pd.DataFrame) -> pd.DataFrame:
        if interactions.empty:
            return pd.DataFrame(columns=["user_id", "product_id", "rating", "timestamp"])

        frame = interactions.copy()
        frame["rating"] = frame["interaction_type"].map(INTERACTION_WEIGHTS).fillna(1.0)
        training_frame = (
            frame.groupby(["user_id", "product_id"], as_index=False)
            .agg(rating=("rating", "sum"), timestamp=("timestamp", "max"))
        )
        training_frame["rating"] = training_frame["rating"].clip(upper=10.0)

        return training_frame

    def _build_user_item_matrix(self, training_frame: pd.DataFrame) -> pd.DataFrame:
        if training_frame.empty:
            return pd.DataFrame()

        return training_frame.pivot_table(
            index="user_id",
            columns="product_id",
            values="rating",
            aggfunc="sum",
            fill_value=0.0,
        )

    def _build_user_history(self, interactions: pd.DataFrame) -> dict[str, list[int]]:
        if interactions.empty:
            return {}

        sorted_frame = interactions.sort_values("timestamp", ascending=False)
        history: dict[str, list[int]] = {}
        for user_id, group in sorted_frame.groupby("user_id"):
            unique_products: list[int] = []
            for product_id in group["product_id"].astype(int).tolist():
                if product_id not in unique_products:
                    unique_products.append(product_id)
            history[str(user_id)] = unique_products

        return history

    def _build_popularity_scores(self, training_frame: pd.DataFrame) -> dict[int, float]:
        if training_frame.empty:
            return {}

        grouped = training_frame.groupby("product_id")["rating"].sum().sort_values(ascending=False)
        return {int(product_id): float(score) for product_id, score in grouped.items()}

    def _load_product_features(self) -> pd.DataFrame:
        query = sqlalchemy.text(
            """
            SELECT
                p.entity_id AS product_id,
                p.sku AS sku,
                COALESCE(name_attr.value, p.sku) AS name,
                COALESCE(short_attr.value, '') AS short_description,
                COALESCE(desc_attr.value, '') AS description,
                COALESCE(categories.category_names, '') AS categories,
                COALESCE(categories.category_ids, '') AS category_ids
            FROM catalog_product_entity AS p
            LEFT JOIN eav_entity_type AS product_entity_type
                ON product_entity_type.entity_type_code = 'catalog_product'
            LEFT JOIN eav_attribute AS name_attribute
                ON name_attribute.entity_type_id = product_entity_type.entity_type_id
                AND name_attribute.attribute_code = 'name'
            LEFT JOIN catalog_product_entity_varchar AS name_attr
                ON name_attr.entity_id = p.entity_id
                AND name_attr.attribute_id = name_attribute.attribute_id
                AND name_attr.store_id = 0
            LEFT JOIN eav_attribute AS short_attribute
                ON short_attribute.entity_type_id = product_entity_type.entity_type_id
                AND short_attribute.attribute_code = 'short_description'
            LEFT JOIN catalog_product_entity_text AS short_attr
                ON short_attr.entity_id = p.entity_id
                AND short_attr.attribute_id = short_attribute.attribute_id
                AND short_attr.store_id = 0
            LEFT JOIN eav_attribute AS desc_attribute
                ON desc_attribute.entity_type_id = product_entity_type.entity_type_id
                AND desc_attribute.attribute_code = 'description'
            LEFT JOIN catalog_product_entity_text AS desc_attr
                ON desc_attr.entity_id = p.entity_id
                AND desc_attr.attribute_id = desc_attribute.attribute_id
                AND desc_attr.store_id = 0
            LEFT JOIN (
                SELECT
                    ccp.product_id,
                    GROUP_CONCAT(DISTINCT category_name.value ORDER BY category_name.value SEPARATOR ' ') AS category_names,
                    GROUP_CONCAT(DISTINCT ccp.category_id ORDER BY ccp.category_id SEPARATOR ',') AS category_ids
                FROM catalog_category_product AS ccp
                LEFT JOIN eav_entity_type AS category_entity_type
                    ON category_entity_type.entity_type_code = 'catalog_category'
                LEFT JOIN eav_attribute AS category_name_attribute
                    ON category_name_attribute.entity_type_id = category_entity_type.entity_type_id
                    AND category_name_attribute.attribute_code = 'name'
                LEFT JOIN catalog_category_entity_varchar AS category_name
                    ON category_name.entity_id = ccp.category_id
                    AND category_name.attribute_id = category_name_attribute.attribute_id
                    AND category_name.store_id = 0
                GROUP BY ccp.product_id
            ) AS categories ON categories.product_id = p.entity_id
            """
        )

        with self.engine.connect() as connection:
            frame = pd.read_sql(query, connection)

        if frame.empty:
            return pd.DataFrame(columns=["product_id", "name", "content", "category_ids"])

        frame["product_id"] = frame["product_id"].astype(int)
        frame["content"] = (
            frame["name"].fillna("")
            + " "
            + frame["sku"].fillna("")
            + " "
            + frame["short_description"].fillna("")
            + " "
            + frame["description"].fillna("")
            + " "
            + frame["categories"].fillna("")
        ).str.strip()

        return frame[["product_id", "name", "content", "category_ids"]]

    def _fit_content_model(self) -> None:
        if self.product_features.empty:
            self.tfidf_vectorizer = None
            self.tfidf_matrix = None
            return

        self.tfidf_vectorizer = TfidfVectorizer(stop_words="english", max_features=6000, ngram_range=(1, 2))
        self.tfidf_matrix = self.tfidf_vectorizer.fit_transform(self.product_features["content"].fillna(""))

    def _fit_collaborative_model(self) -> None:
        if self.training_frame.empty:
            self.svd_model = None
            self.known_users = set()
            self.collaborative_ready = False
            self.collaborative_status_reason = "no_interactions"
            return

        if self.training_frame["user_id"].nunique() < 2:
            self.svd_model = None
            self.known_users = set()
            self.collaborative_ready = False
            self.collaborative_status_reason = "need_at_least_two_users"
            return

        if self.training_frame["product_id"].nunique() < 2:
            self.svd_model = None
            self.known_users = set()
            self.collaborative_ready = False
            self.collaborative_status_reason = "need_at_least_two_products"
            return

        frame = self.training_frame[["user_id", "product_id", "rating"]].copy()
        reader = Reader(rating_scale=(0.0, float(frame["rating"].max())))
        dataset = Dataset.load_from_df(frame, reader)
        trainset = dataset.build_full_trainset()
        model = SVD(n_factors=50, n_epochs=25, biased=True, random_state=42)
        model.fit(trainset)

        self.svd_model = model
        self.known_users = set(frame["user_id"].astype(str).tolist())
        self.collaborative_ready = True
        self.collaborative_status_reason = "ready"

    def _predict_collaborative(
        self,
        user_id: str,
        top_k: int,
        seen_products: set[int],
        category_id: int | None,
    ) -> list[dict]:
        if self.svd_model is None:
            return []

        candidates = [product_id for product_id in self.product_ids if product_id not in seen_products]
        if category_id:
            candidates = [
                product_id for product_id in candidates if self._product_in_category(product_id, category_id)
            ]

        scored = []
        for product_id in candidates:
            estimate = self.svd_model.predict(user_id, product_id).est
            scored.append((product_id, float(estimate)))

        scored.sort(key=lambda item: item[1], reverse=True)

        return [
            {
                "product_id": product_id,
                "score": score,
                "reason": "Recommended from similar users via matrix factorization",
            }
            for product_id, score in scored[:top_k]
        ]

    def _predict_content_based(
        self,
        seed_product_ids: list[int],
        top_k: int,
        seen_products: set[int],
        category_id: int | None,
    ) -> list[dict]:
        if self.tfidf_matrix is None or not seed_product_ids:
            return []

        valid_seed_indexes = [
            self.product_index[product_id]
            for product_id in seed_product_ids
            if product_id in self.product_index
        ]
        if not valid_seed_indexes:
            return []

        seed_matrix = self.tfidf_matrix[valid_seed_indexes]
        seed_vector = np.asarray(seed_matrix.mean(axis=0)).reshape(1, -1)
        similarities = cosine_similarity(seed_vector, self.tfidf_matrix).flatten()
        ranked_indexes = np.argsort(-similarities)

        recommendations: list[dict] = []
        for index in ranked_indexes:
            product_id = int(self.product_features.iloc[index]["product_id"])
            if product_id in seen_products or product_id in seed_product_ids:
                continue
            if category_id and not self._product_in_category(product_id, category_id):
                continue

            recommendations.append(
                {
                    "product_id": product_id,
                    "score": float(similarities[index]),
                    "reason": "Cold-start fallback from cosine similarity on product content",
                }
            )

            if len(recommendations) >= top_k:
                break

        return recommendations

    def _popular_items(self, top_k: int, seen_products: set[int], category_id: int | None) -> list[dict]:
        recommendations: list[dict] = []
        for product_id, score in self.popularity_scores.items():
            if product_id in seen_products:
                continue
            if category_id and not self._product_in_category(product_id, category_id):
                continue

            recommendations.append(
                {
                    "product_id": product_id,
                    "score": float(score),
                    "reason": "Fallback to globally popular products",
                }
            )

            if len(recommendations) >= top_k:
                break

        return recommendations

    def _product_in_category(self, product_id: int, category_id: int) -> bool:
        row = self.product_features.loc[self.product_features["product_id"] == product_id]
        if row.empty:
            return False

        category_ids = str(row.iloc[0]["category_ids"] or "")
        return str(category_id) in {value for value in category_ids.split(",") if value}