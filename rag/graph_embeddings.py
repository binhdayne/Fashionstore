import random

import networkx as nx
import numpy as np
import pandas as pd
from sklearn.metrics.pairwise import cosine_similarity


def build_bipartite_graph(interactions: pd.DataFrame) -> nx.Graph:
    graph = nx.Graph()

    for row in interactions.itertuples(index=False):
        user_node = f"user::{row.user_id}"
        product_node = f"product::{int(row.product_id)}"
        graph.add_node(user_node, bipartite="user")
        graph.add_node(product_node, bipartite="product")
        graph.add_edge(user_node, product_node, weight=1.0)

    return graph


def generate_random_walks(
    graph: nx.Graph,
    num_walks: int = 12,
    walk_length: int = 18,
) -> list[list[str]]:
    walks: list[list[str]] = []
    nodes = list(graph.nodes())

    for _ in range(num_walks):
        random.shuffle(nodes)
        for start_node in nodes:
            walk = [start_node]
            current_node = start_node

            for _ in range(walk_length - 1):
                neighbors = list(graph.neighbors(current_node))
                if not neighbors:
                    break
                current_node = random.choice(neighbors)
                walk.append(current_node)

            walks.append(walk)

    return walks


def train_deepwalk_embeddings(
    interactions: pd.DataFrame,
    vector_size: int = 64,
    window: int = 5,
    min_count: int = 1,
    epochs: int = 10,
) -> tuple[object, pd.DataFrame]:
    try:
        from gensim.models import Word2Vec
    except ImportError as exception:
        raise RuntimeError("DeepWalk sample requires gensim with a compatible scipy build") from exception

    graph = build_bipartite_graph(interactions)
    walks = generate_random_walks(graph)
    model = Word2Vec(
        sentences=walks,
        vector_size=vector_size,
        window=window,
        min_count=min_count,
        sg=1,
        workers=1,
        epochs=epochs,
    )

    product_nodes = [node for node in graph.nodes if str(node).startswith("product::")]
    rows = []
    for product_node in product_nodes:
        product_id = int(product_node.split("::", 1)[1])
        rows.append({
            "product_id": product_id,
            "embedding": model.wv[product_node],
        })

    return model, pd.DataFrame(rows)


def most_similar_products(embeddings: pd.DataFrame, product_id: int, top_k: int = 5) -> list[dict]:
    if embeddings.empty:
        return []

    target_row = embeddings.loc[embeddings["product_id"] == product_id]
    if target_row.empty:
        return []

    matrix = np.vstack(embeddings["embedding"].to_list())
    target_vector = np.vstack(target_row["embedding"].to_list())
    similarities = cosine_similarity(target_vector, matrix).flatten()
    candidate_frame = embeddings.copy()
    candidate_frame["score"] = similarities
    candidate_frame = candidate_frame[candidate_frame["product_id"] != product_id]
    candidate_frame = candidate_frame.sort_values("score", ascending=False).head(top_k)

    return [
        {
            "product_id": int(row.product_id),
            "score": float(row.score),
            "reason": "DeepWalk proximity on user-product bipartite graph",
        }
        for row in candidate_frame.itertuples(index=False)
    ]