from pydantic import BaseModel, EmailStr, Field


class OTPGenerateRequest(BaseModel):
    transaction_id: int = Field(
        gt=0,
        description="ID của giao dịch cần xác thực OTP"
    )

    email: EmailStr = Field(
        description="Email nhận mã OTP"
    )


class OTPGenerateResponse(BaseModel):
    message: str
    transaction_id: int
    expires_in_seconds: int


class OTPVerifyRequest(BaseModel):
    transaction_id: int = Field(
        gt=0,
        description="ID của giao dịch cần xác thực"
    )

    otp_code: str = Field(
        min_length=6,
        max_length=6,
        pattern=r"^\d{6}$",
        description="Mã OTP gồm 6 chữ số"
    )


class OTPVerifyResponse(BaseModel):
    message: str
    transaction_id: int
    verified: bool