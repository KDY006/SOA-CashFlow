from sqlalchemy import Column, Integer, String, BigInteger, Enum, ForeignKey, DateTime
from sqlalchemy.orm import relationship
import enum
from .database import Base

class PaymentStatus(str, enum.Enum):
    UNPAID = "UNPAID"
    PAID = "PAID"

class Student(Base):
    __tablename__ = "students"

    mssv = Column(String(20), primary_key=True, index=True)
    full_name = Column(String(100), nullable=False)
    faculty = Column(String(100), default="Công nghệ thông tin")

    tuitions = relationship("TuitionRecord", back_populates="student")

class TuitionRecord(Base):
    __tablename__ = "tuition_records"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    mssv = Column(String(20), ForeignKey("students.mssv"), nullable=False)
    semester = Column(String(30), nullable=False)
    amount = Column(BigInteger, nullable=False)
    status = Column(Enum(PaymentStatus), default=PaymentStatus.UNPAID)
    paid_at = Column(DateTime, nullable=True)

    student = relationship("Student", back_populates="tuitions")