from datetime import datetime
from decimal import Decimal

from pydantic import BaseModel, ConfigDict, Field


class PaymentInitiateRequest(BaseModel):
    user_id: str = Field(min_length=1, max_length=100)
    tuition_record_id: str = Field(min_length=1, max_length=100)
    idempotency_key: str = Field(min_length=8, max_length=100)


class PaymentConfirmRequest(BaseModel):
    otp_code: str = Field(min_length=4, max_length=10)


class PaymentResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: str
    user_id: str
    tuition_record_id: str
    amount: Decimal
    status: str
    created_at: datetime


class PaymentHistoryResponse(BaseModel):
    items: list[PaymentResponse]
    total: int
