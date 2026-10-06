from .database import SessionLocal, engine, Base
from .models import Student, TuitionRecord, PaymentStatus

Base.metadata.create_all(bind=engine)

def seed_data():
    db = SessionLocal()
    try:
        # Nếu đã có dữ liệu rồi thì bỏ qua không nạp trùng
        if db.query(Student).count() > 0:
            return

        mock_students = [
            {"mssv": "52400153", "full_name": "Lê Văn Quý", "faculty": "Kỹ thuật phần mềm"},
            {"mssv": "52400101", "full_name": "Nguyễn Trọng Phúc", "faculty": "Khoa học máy tính"},
            {"mssv": "52400102", "full_name": "Lê Tấn Đạt", "faculty": "Khoa học máy tính"},
            {"mssv": "52400103", "full_name": "Vũ Đắc Long", "faculty": "Kỹ thuật phần mềm"},
            {"mssv": "52400104", "full_name": "Trần Ngọc Hoàn", "faculty": "Kỹ thuật phần mềm"},
            {"mssv": "52400105", "full_name": "Phạm Quốc Duy", "faculty": "Mạng máy tính & TTTT"},
        ]
        for s in mock_students:
            db.add(Student(**s))
        db.commit()

        mock_tuitions = [
            {"mssv": "52400153", "semester": "HK1_2025_2026", "amount": 12500000, "status": PaymentStatus.UNPAID},
            {"mssv": "52400101", "semester": "HK1_2025_2026", "amount": 14200000, "status": PaymentStatus.UNPAID},
            {"mssv": "52400102", "semester": "HK1_2025_2026", "amount": 9800000, "status": PaymentStatus.UNPAID},
            {"mssv": "52400103", "semester": "HK1_2025_2026", "amount": 13000000, "status": PaymentStatus.PAID},
            {"mssv": "52400104", "semester": "HK1_2025_2026", "amount": 15600000, "status": PaymentStatus.UNPAID},
            {"mssv": "52400105", "semester": "HK1_2025_2026", "amount": 12000000, "status": PaymentStatus.UNPAID},
        ]
        for t in mock_tuitions:
            db.add(TuitionRecord(**t))
        db.commit()
        print("Tạo dữ liệu mẫu sinh viên TDTU thành công!")
    finally:
        db.close()

if __name__ == "__main__":
    seed_data()