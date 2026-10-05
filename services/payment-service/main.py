from contextlib import asynccontextmanager

from fastapi import FastAPI

from app.api.payment import router as payment_router
from app.core.database import Base, engine
from app.models import Transaction  # noqa: F401 - registers the table with Base


@asynccontextmanager
async def lifespan(_app: FastAPI):
    Base.metadata.create_all(bind=engine)
    yield


app = FastAPI(title="Payment Service", version="1.0.0", lifespan=lifespan)
app.include_router(payment_router, prefix="/api/v1")


@app.get("/health")
def health():
    return {"status": "ok", "service": "payment-service"}
