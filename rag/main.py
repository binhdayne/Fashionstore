import os
import httpx
import sqlalchemy
from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from qdrant_client import QdrantClient
from qdrant_client.models import Distance, VectorParams, PointStruct
from sentence_transformers import SentenceTransformer
from pypdf import PdfReader
import io
import uuid

QDRANT_URL   = os.getenv("QDRANT_URL",   "http://qdrant:6333")
OLLAMA_URL   = os.getenv("OLLAMA_URL",   "http://ollama:11434")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "deepseek-r1")
DB_HOST = os.getenv("MAGENTO_DB_HOST", "mysql")
DB_NAME = os.getenv("MAGENTO_DB_NAME", "magento")
DB_USER = os.getenv("MAGENTO_DB_USER", "magento")
DB_PASS = os.getenv("MAGENTO_DB_PASS", "magento")

COLLECTION_NAME = "magento_docs"
EMBED_MODEL     = "all-MiniLM-L6-v2"
CHUNK_SIZE      = 500

app = FastAPI(title="Magento RAG Backend", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

embedder = SentenceTransformer(EMBED_MODEL)
qdrant   = QdrantClient(url=QDRANT_URL)
DB_URL   = f"mysql://{DB_USER}:{DB_PASS}@{DB_HOST}/{DB_NAME}"
engine   = sqlalchemy.create_engine(DB_URL, pool_pre_ping=True)


def ensure_collection():
    existing = [c.name for c in qdrant.get_collections().collections]
    if COLLECTION_NAME not in existing:
        qdrant.create_collection(
            collection_name=COLLECTION_NAME,
            vectors_config=VectorParams(size=384, distance=Distance.COSINE),
        )

@app.on_event("startup")
def on_startup():
    ensure_collection()


def chunk_text(text: str, size: int = CHUNK_SIZE) -> list[str]:
    words  = text.split()
    chunks = []
    step   = size // 2
    for i in range(0, len(words), step):
        chunk = " ".join(words[i : i + size])
        if chunk:
            chunks.append(chunk)
    return chunks


def embed(texts: list[str]) -> list[list[float]]:
    return embedder.encode(texts, show_progress_bar=False).tolist()


def search_qdrant(query: str, top_k: int = 5) -> list[str]:
    vec  = embed([query])[0]
    hits = qdrant.search(collection_name=COLLECTION_NAME, query_vector=vec, limit=top_k)
    return [h.payload.get("text", "") for h in hits]


def query_mysql_products(keyword: str, limit: int = 5) -> list[dict]:
    sql = sqlalchemy.text("""
        SELECT e.sku, v.value AS name
        FROM catalog_product_entity e
        JOIN catalog_product_entity_varchar v ON v.entity_id = e.entity_id
        JOIN eav_attribute a ON a.attribute_id = v.attribute_id
            AND a.attribute_code = 'name'
        WHERE v.value LIKE :kw
        LIMIT :lim
    """)
    try:
        with engine.connect() as conn:
            rows = conn.execute(sql, {"kw": f"%{keyword}%", "lim": limit}).fetchall()
        return [{"sku": r[0], "name": r[1]} for r in rows]
    except Exception as e:
        return [{"error": str(e)}]


async def call_ollama(prompt: str) -> str:
    async with httpx.AsyncClient(timeout=120) as client:
        resp = await client.post(
            f"{OLLAMA_URL}/api/generate",
            json={"model": OLLAMA_MODEL, "prompt": prompt, "stream": False},
        )
        resp.raise_for_status()
        return resp.json().get("response", "")


class ChatRequest(BaseModel):
    question: str
    use_pdf_context: bool = True
    use_mysql_context: bool = True
    top_k: int = 5

class ChatResponse(BaseModel):
    answer: str
    pdf_context: list[str]
    mysql_products: list[dict]

class IngestResponse(BaseModel):
    filename: str
    chunks_indexed: int


@app.get("/health")
def health():
    return {"status": "ok", "model": OLLAMA_MODEL}


@app.post("/ingest/pdf", response_model=IngestResponse)
async def ingest_pdf(file: UploadFile = File(...)):
    if not file.filename.endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Only PDF files are accepted.")
    raw    = await file.read()
    reader = PdfReader(io.BytesIO(raw))
    pages  = " ".join(page.extract_text() or "" for page in reader.pages)
    chunks = chunk_text(pages)
    if not chunks:
        raise HTTPException(status_code=422, detail="No extractable text found in PDF.")
    vectors = embed(chunks)
    points  = [
        PointStruct(
            id=str(uuid.uuid4()),
            vector=vec,
            payload={"text": chunk, "source": file.filename},
        )
        for chunk, vec in zip(chunks, vectors)
    ]
    qdrant.upsert(collection_name=COLLECTION_NAME, points=points)
    return IngestResponse(filename=file.filename, chunks_indexed=len(chunks))


@app.post("/chat", response_model=ChatResponse)
async def chat(req: ChatRequest):
    pdf_context:    list[str]  = []
    mysql_products: list[dict] = []

    if req.use_pdf_context:
        pdf_context = search_qdrant(req.question, top_k=req.top_k)

    if req.use_mysql_context:
        keyword = req.question.split()[0] if req.question.split() else req.question
        mysql_products = query_mysql_products(keyword)

    context_parts = []
    if pdf_context:
        context_parts.append("=== Relevant documentation ===\n" + "\n---\n".join(pdf_context))
    if mysql_products:
        product_lines = "\n".join(
            f"- {p.get('name','?')} (SKU: {p.get('sku','?')})"
            for p in mysql_products if "error" not in p
        )
        if product_lines:
            context_parts.append("=== Matching products in store ===\n" + product_lines)

    context_block = "\n\n".join(context_parts) if context_parts else "No additional context available."

    prompt = f"""Bạn là nhân viên tư vấn khách hàng xuất sắc của Fashion Store.
Nhiệm vụ của bạn là trả lời câu hỏi của khách hàng CHỈ DỰA TRÊN các thông tin được cung cấp dưới đây.
Tuyệt đối KHÔNG tự bịa đặt, KHÔNG dùng kiến thức bên ngoài. Nếu thông tin dưới đây không có câu trả lời, hãy nói: "Dạ, hiện tại em chưa có thông tin chính xác về vấn đề này ạ."

{context_block}

Câu hỏi của khách: {req.question}

Trả lời:"""

    answer = await call_ollama(prompt)
    return ChatResponse(answer=answer, pdf_context=pdf_context, mysql_products=mysql_products)


@app.get("/collections/info")
def collection_info():
    ensure_collection()
    info = qdrant.get_collection(COLLECTION_NAME)
    return {
        "collection": COLLECTION_NAME,
        "vectors_count": info.vectors_count,
        "points_count": info.points_count,
    }


@app.delete("/collections/reset")
def reset_collection():
    qdrant.delete_collection(COLLECTION_NAME)
    ensure_collection()
    return {"status": "reset", "collection": COLLECTION_NAME}


@app.post("/ingest/text")
async def ingest_text(payload: dict):
    """Ingest raw text vào Qdrant."""
    text   = payload.get("text", "")
    source = payload.get("source", "manual")
    
    if not text:
        raise HTTPException(status_code=400, detail="text is required")
        
    chunks  = chunk_text(text)
    
    if not chunks:
        raise HTTPException(status_code=422, detail="Text is empty after chunking")
        
    vectors = embed(chunks)
    
    points  = [
        PointStruct(
            id=str(uuid.uuid4()),
            vector=vec,
            payload={
                "text": chunk, 
                "source": source,
                "type": "knowledge_base" # Metadata được giữ lại như bạn mong muốn
            },
        )
        for chunk, vec in zip(chunks, vectors)
    ]
    
    qdrant.upsert(collection_name=COLLECTION_NAME, points=points)
    
    return {
        "source": source, 
        "chunks_indexed": len(chunks)
    }