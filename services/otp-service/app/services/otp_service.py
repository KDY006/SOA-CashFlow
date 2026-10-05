import secrets
from datetime import datetime, timedelta

from sqlalchemy.orm import Session

from app.models.otp import OTP


OTP_EXPIRE_MINUTES = 5


def generate_unique_otp(db: Session) -> str:
    now = datetime.utcnow()

    while True:
        otp_code = f"{secrets.randbelow(1_000_000):06d}"

        existing_otp = (
            db.query(OTP)
            .filter(
                OTP.otp_code == otp_code,
                OTP.is_used == False,
                OTP.expires_at > now
            )
            .first()
        )

        if existing_otp is None:
            return otp_code


def create_otp(
    db: Session,
    transaction_id: int
) -> OTP:
    now = datetime.utcnow()

    active_otps = (
        db.query(OTP)
        .filter(
            OTP.transaction_id == transaction_id,
            OTP.is_used == False,
            OTP.expires_at > now
        )
        .all()
    )

    for old_otp in active_otps:
        old_otp.is_used = True
        old_otp.used_at = now

    otp_code = generate_unique_otp(db)

    otp = OTP(
        transaction_id=transaction_id,
        otp_code=otp_code,
        expires_at=now + timedelta(
            minutes=OTP_EXPIRE_MINUTES
        ),
        is_used=False
    )

    db.add(otp)
    db.commit()
    db.refresh(otp)

    return otp


def verify_otp(
    db: Session,
    transaction_id: int,
    otp_code: str
):
    now = datetime.utcnow()

    otp = (
        db.query(OTP)
        .filter(
            OTP.transaction_id == transaction_id,
            OTP.otp_code == otp_code
        )
        .order_by(OTP.created_at.desc())
        .first()
    )

    if otp is None:
        return False, "OTP không hợp lệ"

    if otp.is_used:
        return False, "OTP đã được sử dụng hoặc vô hiệu hóa"

    if otp.expires_at <= now:
        return False, "OTP đã hết hạn"

    otp.is_used = True
    otp.used_at = now

    db.commit()

    return True, "Xác thực OTP thành công"


def invalidate_otp(
    db: Session,
    otp: OTP
):
    otp.is_used = True
    otp.used_at = datetime.utcnow()

    db.commit()