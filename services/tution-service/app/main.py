from fastapi import FastAPI
from app.database import engine, Base
from app.api import router as tuition_router
from app.seed import seed_data

Base.metadata.create_all(bind=engine)
seed_data()

app = FastAPI(title="Tuition Service", version="1.0.0")
app.include_router(tuition_router, prefix="/api/v1")

@app.get("/health")
def health():
    return {"status": "ok", "service": "tuition-service"}