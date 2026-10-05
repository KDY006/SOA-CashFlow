from datetime import datetime, timezone
from decimal import Decimal
from enum import Enum
from uuid import uuid4

from sqlalchemy import CheckConstraint, DateTime, Numeric, String, UniqueConstraint
from sqlalchemy.orm import Mapped, mapped_column

from app.core.database import Base


class TransactionStatus(str, Enum):
    PENDING = "PENDING"
    OTP_PENDING = "OTP_PENDING"
    SUCCEEDED = "SUCCEEDED"
    FAILED = "FAILED"


class Transaction(Base):
    __tablename__ = "transactions"
    __table_args__ = (
        UniqueConstraint("idempotency_key", name="uq_transactions_idempotency_key"),
        CheckConstraint("amount > 0", name="ck_transactions_positive_amount"),
        CheckConstraint(
            "status IN ('PENDING', 'OTP_PENDING', 'SUCCEEDED', 'FAILED')",
            name="ck_transactions_valid_status",
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=lambda: str(uuid4()))
    user_id: Mapped[str] = mapped_column(String(100), nullable=False, index=True)
    tuition_record_id: Mapped[str] = mapped_column(String(100), nullable=False, index=True)
    amount: Mapped[Decimal] = mapped_column(Numeric(14, 2), nullable=False)
    status: Mapped[str] = mapped_column(String(20), nullable=False, default=TransactionStatus.PENDING.value)
    idempotency_key: Mapped[str] = mapped_column(String(100), nullable=False)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), nullable=False, default=lambda: datetime.now(timezone.utc)
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), nullable=False,
        default=lambda: datetime.now(timezone.utc),
        onupdate=lambda: datetime.now(timezone.utc),
    )
