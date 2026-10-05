from decimal import Decimal

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy import func, select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.core.locks import LockUnavailable, payment_locks
from app.core.service_clients import (
    ServiceCallError, debit_account, get_account, get_tuition, mark_tuition_paid,
    refund_account, request_otp, verify_otp,
)
from app.models.transaction import Transaction, TransactionStatus
from app.schemas.payment import (
    PaymentConfirmRequest, PaymentHistoryResponse, PaymentInitiateRequest, PaymentResponse,
)

router = APIRouter(prefix="/payments", tags=["Payments"])


@router.post("", response_model=PaymentResponse, status_code=status.HTTP_201_CREATED)
def initiate_payment(body: PaymentInitiateRequest, db: Session = Depends(get_db)):
    existing = db.scalar(select(Transaction).where(Transaction.idempotency_key == body.idempotency_key))
    if existing:
        return existing
    try:
        tuition = get_tuition(body.tuition_record_id)
        if tuition.get("status") == "PAID":
            raise HTTPException(409, "Khoản học phí đã được thanh toán")
        amount = Decimal(str(tuition["amount"]))
        if amount <= 0:
            raise HTTPException(422, "Số tiền học phí không hợp lệ")
        get_account(body.user_id)
        payment = Transaction(
            user_id=body.user_id, tuition_record_id=body.tuition_record_id,
            amount=amount, status=TransactionStatus.OTP_PENDING.value,
            idempotency_key=body.idempotency_key,
        )
        db.add(payment)
        try:
            db.commit()
        except IntegrityError:
            # A second identical request may have won the unique-key race.
            db.rollback()
            existing = db.scalar(select(Transaction).where(Transaction.idempotency_key == body.idempotency_key))
            if existing:
                return existing
            raise
        db.refresh(payment)
        request_otp(payment.id, payment.user_id)
        return payment
    except HTTPException:
        raise
    except (ServiceCallError, KeyError, ValueError) as exc:
        db.rollback()
        raise HTTPException(502, "Không thể kiểm tra User/Tuition hoặc gửi OTP") from exc


@router.post("/{transaction_id}/confirm", response_model=PaymentResponse)
def confirm_payment(transaction_id: str, body: PaymentConfirmRequest, db: Session = Depends(get_db)):
    payment = db.get(Transaction, transaction_id)
    if payment is None:
        raise HTTPException(404, "Không tìm thấy giao dịch")
    if payment.status == TransactionStatus.SUCCEEDED.value:
        return payment
    if payment.status != TransactionStatus.OTP_PENDING.value:
        raise HTTPException(409, "Giao dịch không ở trạng thái chờ OTP")

    try:
        with payment_locks(payment.user_id, payment.tuition_record_id):
            # Reload while holding locks; another request may have completed meanwhile.
            db.refresh(payment)
            if payment.status == TransactionStatus.SUCCEEDED.value:
                return payment
            otp_result = verify_otp(transaction_id, body.otp_code)
            if not otp_result.get("valid"):
                raise HTTPException(400, "OTP sai, hết hạn hoặc đã được sử dụng")
            account = get_account(payment.user_id)
            tuition = get_tuition(payment.tuition_record_id)
            if tuition.get("status") == "PAID":
                payment.status = TransactionStatus.FAILED.value
                db.commit()
                raise HTTPException(409, "Khoản học phí đã được thanh toán")
            if Decimal(str(account["balance"])) < payment.amount:
                payment.status = TransactionStatus.FAILED.value
                db.commit()
                raise HTTPException(409, "Số dư không đủ")
            debit_account(payment.user_id, str(payment.amount), payment.id)
            try:
                mark_tuition_paid(payment.tuition_record_id, payment.id)
            except ServiceCallError:
                # Saga compensation: reverse the debit if Tuition could not be updated.
                refund_account(payment.user_id, str(payment.amount), payment.id)
                payment.status = TransactionStatus.FAILED.value
                db.commit()
                raise HTTPException(502, "Gạch nợ thất bại; tiền đã được hoàn lại")
            payment.status = TransactionStatus.SUCCEEDED.value
            db.commit()
            db.refresh(payment)
            return payment
    except LockUnavailable as exc:
        raise HTTPException(409, "Tài khoản hoặc khoản học phí đang được xử lý, hãy thử lại") from exc
    except ServiceCallError as exc:
        db.rollback()
        raise HTTPException(502, "Một dịch vụ phụ trợ tạm thời không phản hồi") from exc


@router.get("/history", response_model=PaymentHistoryResponse)
def payment_history(
    user_id: str | None = Query(default=None),
    limit: int = Query(default=20, ge=1, le=100),
    offset: int = Query(default=0, ge=0),
    db: Session = Depends(get_db),
):
    query = select(Transaction)
    count_query = select(func.count()).select_from(Transaction)
    if user_id:
        query = query.where(Transaction.user_id == user_id)
        count_query = count_query.where(Transaction.user_id == user_id)
    rows = db.scalars(query.order_by(Transaction.created_at.desc()).limit(limit).offset(offset)).all()
    total = db.scalar(count_query) or 0
    return {"items": rows, "total": total}
