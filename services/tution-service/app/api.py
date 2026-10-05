from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from typing import List

from . import models, schemas, crud
from .database import get_db

router = APIRouter()

# 1. Tra cứu học phí theo MSSV
@router.get("/tuition/inquiry/{mssv}", response_model=schemas.StudentTuitionSummaryResponse, tags=["Tra cứu"])
def inquiry_tuition(mssv: str, db: Session = Depends(get_db)):
    student = crud.get_student(db, mssv)
    if not student:
        raise HTTPException(status_code=404, detail="Không tìm thấy sinh viên với MSSV này")
    
    tuition_records = crud.get_tuitions_by_mssv(db, mssv)
    total_unpaid = sum(rec.amount for rec in tuition_records if rec.status == models.PaymentStatus.UNPAID)
    
    return {
        "mssv": student.mssv,
        "full_name": student.full_name,
        "faculty": student.faculty,
        "total_unpaid": total_unpaid,
        "records": tuition_records
    }

# 2. Gạch nợ học phí sau khi thanh toán thành công
@router.post("/tuition/pay", response_model=schemas.TuitionRecordResponse, tags=["Thanh toán"])
def pay_tuition(payload: schemas.PayTuitionRequest, db: Session = Depends(get_db)):
    record = crud.mark_tuition_as_paid(db, payload.mssv, payload.tuition_id)
    if not record:
        raise HTTPException(status_code=404, detail="Không tìm thấy khoản nợ học phí tương ứng")
    return record

# 3. CRUD Quản trị - Danh sách sinh viên
@router.get("/admin/students", response_model=List[schemas.StudentResponse], tags=["Quản trị - Sinh viên"])
def list_students(skip: int = 0, limit: int = 100, db: Session = Depends(get_db)):
    return crud.get_students(db, skip=skip, limit=limit)

# 4. CRUD Quản trị - Thêm sinh viên
@router.post("/admin/students", response_model=schemas.StudentResponse, status_code=status.HTTP_201_CREATED, tags=["Quản trị - Sinh viên"])
def add_student(student: schemas.StudentCreate, db: Session = Depends(get_db)):
    if crud.get_student(db, student.mssv):
        raise HTTPException(status_code=400, detail="MSSV đã tồn tại trong hệ thống")
    return crud.create_student(db, student)

# 5. CRUD Quản trị - Thêm khoản nợ học phí
@router.post("/admin/tuitions", response_model=schemas.TuitionRecordResponse, status_code=status.HTTP_201_CREATED, tags=["Quản trị - Học phí"])
def add_tuition(tuition: schemas.TuitionRecordCreate, db: Session = Depends(get_db)):
    if not crud.get_student(db, tuition.mssv):
        raise HTTPException(status_code=404, detail="Sinh viên không tồn tại để gán học phí")
    return crud.create_tuition_record(db, tuition)

# 6. CRUD Quản trị - Cập nhật khoản học phí
@router.patch("/admin/tuitions/{tuition_id}", response_model=schemas.TuitionRecordResponse, tags=["Quản trị - Học phí"])
def update_tuition(tuition_id: int, update_data: schemas.TuitionRecordUpdate, db: Session = Depends(get_db)):
    record = crud.update_tuition_record(db, tuition_id, update_data)
    if not record:
        raise HTTPException(status_code=404, detail="Không tìm thấy bản ghi học phí")
    return record