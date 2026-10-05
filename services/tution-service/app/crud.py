from sqlalchemy.orm import Session
from datetime import datetime
from . import models, schemas

# 1. Các hàm xử lý Sinh viên
def get_student(db: Session, mssv: str):
    return db.query(models.Student).filter(models.Student.mssv == mssv).first()

def get_students(db: Session, skip: int = 0, limit: int = 100):
    return db.query(models.Student).offset(skip).limit(limit).all()

def create_student(db: Session, student: schemas.StudentCreate):
    db_student = models.Student(
        mssv=student.mssv,
        full_name=student.full_name,
        faculty=student.faculty
    )
    db.add(db_student)
    db.commit()
    db.refresh(db_student)
    return db_student

# 2. Các hàm xử lý Học phí
def get_tuitions_by_mssv(db: Session, mssv: str):
    return db.query(models.TuitionRecord).filter(models.TuitionRecord.mssv == mssv).all()

def create_tuition_record(db: Session, tuition: schemas.TuitionRecordCreate):
    db_tuition = models.TuitionRecord(
        mssv=tuition.mssv,
        semester=tuition.semester,
        amount=tuition.amount,
        status=tuition.status
    )
    db.add(db_tuition)
    db.commit()
    db.refresh(db_tuition)
    return db_tuition

def update_tuition_record(db: Session, tuition_id: int, update_data: schemas.TuitionRecordUpdate):
    db_tuition = db.query(models.TuitionRecord).filter(models.TuitionRecord.id == tuition_id).first()
    if not db_tuition:
        return None
    for key, value in update_data.model_dump(exclude_unset=True).items():
        setattr(db_tuition, key, value)
    db.commit()
    db.refresh(db_tuition)
    return db_tuition

def mark_tuition_as_paid(db: Session, mssv: str, tuition_id: int):
    db_tuition = db.query(models.TuitionRecord).filter(
        models.TuitionRecord.id == tuition_id,
        models.TuitionRecord.mssv == mssv
    ).first()
    if not db_tuition:
        return None
    db_tuition.status = models.PaymentStatus.PAID
    db_tuition.paid_at = datetime.now()
    db.commit()
    db.refresh(db_tuition)
    return db_tuition