import smtplib
from email.message import EmailMessage

from app.core.config import settings


def send_otp_email(
    recipient_email: str,
    otp_code: str,
    transaction_id: int
):
    message = EmailMessage()

    message["Subject"] = "TDTU iBanking - Mã xác thực OTP"
    message["From"] = (
        f"{settings.SMTP_FROM_NAME} <{settings.SMTP_USER}>"
    )
    message["To"] = recipient_email

    message.set_content(
        f"""Xin chào,

Bạn đang thực hiện giao dịch thanh toán học phí trên TDTU iBanking.

Mã OTP của bạn là:

{otp_code}

Mã giao dịch: {transaction_id}

Mã OTP có hiệu lực trong 5 phút và chỉ được sử dụng một lần.

Nếu bạn không thực hiện giao dịch này, vui lòng bỏ qua email.

TDTU iBanking
"""
    )

    with smtplib.SMTP(
        settings.SMTP_HOST,
        settings.SMTP_PORT,
        timeout=15
    ) as server:
        server.ehlo()
        server.starttls()
        server.ehlo()

        server.login(
            settings.SMTP_USER,
            settings.SMTP_PASSWORD
        )

        server.send_message(message)
        
        
def send_payment_success_email(
    recipient_email: str,
    transaction_id: int,
    student_id: str,
    student_name: str,
    amount: float
):
    message = EmailMessage()
    message["Subject"] = "TDTU iBanking - Thanh toán học phí thành công"
    message["From"] = f"{settings.SMTP_FROM_NAME} <{settings.SMTP_USER}>"
    message["To"] = recipient_email

    message.set_content(
        f"""Xin chào,

Giao dịch thanh toán học phí của bạn đã được thực hiện thành công.

Thông tin giao dịch:

Mã giao dịch: {transaction_id}
MSSV: {student_id}
Sinh viên: {student_name}
Số tiền: {amount:,.0f} VND

Học phí đã được thanh toán thành công.

Cảm ơn bạn đã sử dụng TDTU iBanking.

TDTU iBanking
"""
    )

    with smtplib.SMTP(
        settings.SMTP_HOST,
        settings.SMTP_PORT,
        timeout=15
    ) as server:
        server.ehlo()
        server.starttls()
        server.ehlo()
        server.login(
            settings.SMTP_USER,
            settings.SMTP_PASSWORD
        )
        server.send_message(message)