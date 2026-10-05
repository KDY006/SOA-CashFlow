from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.schemas.otp import (
    OTPGenerateRequest,
    OTPGenerateResponse,
    OTPVerifyRequest,
    OTPVerifyResponse
)
from app.services.otp_service import (
    create_otp,
    verify_otp,
    invalidate_otp
)
from app.services.email_service import send_otp_email


router = APIRouter(
    prefix="/api/otp",
    tags=["OTP"]
)


@router.post(
    "/generate",
    response_model=OTPGenerateResponse
)
def generate_otp(
    request: OTPGenerateRequest,
    db: Session = Depends(get_db)
):
    otp = None

    try:
        otp = create_otp(
            db=db,
            transaction_id=request.transaction_id
        )

        send_otp_email(
            recipient_email=str(request.email),
            otp_code=otp.otp_code,
            transaction_id=request.transaction_id
        )

        return OTPGenerateResponse(
            message="OTP đã được gửi đến email",
            transaction_id=request.transaction_id,
            expires_in_seconds=300
        )

    except Exception as e:
        print(f"Generate OTP error: {e}")

        if otp is not None:
            try:
                invalidate_otp(
                    db=db,
                    otp=otp
                )
            except Exception as invalidate_error:
                print(
                    f"Invalidate OTP error: "
                    f"{invalidate_error}"
                )

        raise HTTPException(
            status_code=500,
            detail="Không thể tạo hoặc gửi email OTP"
        )


@router.post(
    "/verify",
    response_model=OTPVerifyResponse
)
def verify_otp_endpoint(
    request: OTPVerifyRequest,
    db: Session = Depends(get_db)
):
    try:
        success, message = verify_otp(
            db=db,
            transaction_id=request.transaction_id,
            otp_code=request.otp_code
        )

        if not success:
            raise HTTPException(
                status_code=400,
                detail=message
            )

        return OTPVerifyResponse(
            message=message,
            transaction_id=request.transaction_id,
            verified=True
        )

    except HTTPException:
        raise

    except Exception as e:
        print(f"Verify OTP error: {e}")

        raise HTTPException(
            status_code=500,
            detail="Có lỗi xảy ra khi xác thực OTP"
        )