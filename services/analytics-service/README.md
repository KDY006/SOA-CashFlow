# Analytics Service (Python)

- **Phụ trách:** Thành viên 4
- **Công nghệ:** Python (Bản giữa kỳ: Python Native http.server / Bản cuối kỳ: FastAPI)
- **Cơ sở dữ liệu:** MongoDB (`analytics_db` trên port 27017)
- **Port phục vụ:** `8084`

## Các API Endpoints chính
- `GET /api/analytics/summary` - Tổng hợp doanh thu, chi phí theo kỳ
- `GET /api/analytics/trends` - Phân tích xu hướng dòng tiền (Cash flow forecast)
- `GET /api/analytics/category-breakdown` - Báo cáo cơ cấu chi tiêu theo nhóm danh mục
- `POST /api/analytics/aggregate` - Kích hoạt batch job tổng hợp dữ liệu

## Chạy Local
```bash
uvicorn src.main:app --host 0.0.0.0 --port 8084 --reload
```
