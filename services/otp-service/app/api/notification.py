from fastapi import APIRouter, HTTPException

from app.schemas.otp import (
    PaymentSuccessNotificationRequest,
    PaymentSuccessNotificationResponse
)
from app.services.email_service import send_payment_success_email


router = APIRouter(
    prefix="/api/notifications",
    tags=["Notifications"]
)


@router.post(
    "/payment-success",
    response_model=PaymentSuccessNotificationResponse
)
def payment_success_notification(
    request: PaymentSuccessNotificationRequest
):
    try:
        send_payment_success_email(
            recipient_email=str(request.email),
            transaction_id=request.transaction_id,
            student_id=request.student_id,
            student_name=request.student_name,
            amount=request.amount
        )

        return PaymentSuccessNotificationResponse(
            message="Email thông báo thanh toán thành công đã được gửi",
            transaction_id=request.transaction_id,
            sent=True
        )

    except Exception as e:
        print(f"Payment success notification error: {e}")

        raise HTTPException(
            status_code=500,
            detail="Không thể gửi email thông báo thanh toán thành công"
        )