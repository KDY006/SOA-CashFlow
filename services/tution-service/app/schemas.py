from pydantic import BaseModel, ConfigDict
from typing import Optional, List
from datetime import datetime
from .models import PaymentStatus

# Định dạng dữ liệu Sinh viên
class StudentBase(BaseModel):
    mssv: str
    full_name: str
    faculty: Optional[str] = "Công nghệ thông tin"

class StudentCreate(StudentBase):
    pass

class StudentResponse(StudentBase):
    model_config = ConfigDict(from_attributes=True)

# Định dạng dữ liệu Học phí
class TuitionRecordBase(BaseModel):
    semester: str
    amount: int
    status: PaymentStatus = PaymentStatus.UNPAID

class TuitionRecordCreate(TuitionRecordBase):
    mssv: str

class TuitionRecordUpdate(BaseModel):
    amount: Optional[int] = None
    semester: Optional[str] = None
    status: Optional[PaymentStatus] = None

class TuitionRecordResponse(TuitionRecordBase):
    id: int
    mssv: str
    paid_at: Optional[datetime] = None
    model_config = ConfigDict(from_attributes=True)

# Kết quả trả về khi tra cứu theo MSSV
class StudentTuitionSummaryResponse(BaseModel):
    mssv: str
    full_name: str
    faculty: str
    total_unpaid: int
    records: List[TuitionRecordResponse]
    model_config = ConfigDict(from_attributes=True)

# Dữ liệu gửi lên khi gạch nợ học phí
class PayTuitionRequest(BaseModel):
    mssv: str
    tuition_id: int