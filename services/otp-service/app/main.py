from fastapi import FastAPI

from app.core.database import Base, engine
from app.models.otp import OTP
from app.api.otp import router as otp_router


Base.metadata.create_all(bind=engine)


app = FastAPI(
    title="OTP & Notification Service",
    description=(
        "OTP and Email Notification Service "
        "for TDTU iBanking Tuition Payment"
    ),
    version="1.0.0"
)


app.include_router(otp_router)


@app.get("/")
def root():
    return {
        "service": "OTP & Notification Service",
        "status": "running"
    }


@app.get("/health")
def health_check():
    return {
        "status": "healthy"
    }