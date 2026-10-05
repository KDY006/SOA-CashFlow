# PHÂN HỆ FRONTEND - TDTU iBANKING ĐÓNG HỌC PHÍ TRỰC TUYẾN
> **Môn học:** Kiến trúc Hướng dịch vụ (SOA / Microservices)  
> **Thành viên phụ trách:** Long (Frontend Developer - All UI)  
> **Repository:** https://github.com/KDY006/SOA-ibanking-tuition.git  
> 🌐 **Live Demo Surge:** https://hocphitdtu.surge.sh  

---

## 1. Giới thiệu tổng quan
Phân hệ Frontend được xây dựng hoàn chỉnh bằng **React (Vite) + Vanilla CSS hiện đại**, thiết kế theo tiêu chuẩn giao diện Ngân hàng số (iBanking) Việt Nam (phong cách tương tự Techcombank / Vietcombank liên kết Đại học Tôn Đức Thắng).

Giao diện đáp ứng chính xác 100% nhiệm vụ được giao trong bản phân công của Project Leader:
1. **Luồng Khách hàng (User Flow):**
   * **Đăng nhập:** Xác thực tài khoản iBanking (không cần đăng ký theo đề bài). Có sẵn các nút điền nhanh tài khoản để phục vụ chấm điểm.
   * **Tra cứu học phí theo MSSV:** Nhập MSSV -> Hiển thị tự động thông tin sinh viên, khoa, học kỳ và số tiền học phí cần nộp.
   * **Kiểm tra ràng buộc:** Bắt buộc thanh toán toàn bộ (100%), kiểm tra số dư khả dụng $\ge$ học phí, kiểm tra trạng thái chưa thanh toán. Nếu số dư không đủ sẽ cảnh báo đỏ và khóa nút thanh toán.
   * **Xác thực OTP 2 lớp qua Email:** Modal nhập OTP 6 chữ số có **đồng hồ đếm ngược 5 phút (05:00)**, gắn liền với Transaction ID cụ thể, hỗ trợ gửi lại mã khi hết hạn.
   * **Biên lai điện tử & Gạch nợ:** Trừ tiền tài khoản, gạch nợ học phí sang `PAID`, hiển thị Biên lai điện tử (Receipt) có nút In biên lai (`window.print()`).
   * **Lịch sử giao dịch:** Xem toàn bộ lịch sử thanh toán học phí cá nhân kèm nút mở lại biên lai bất cứ lúc nào.

2. **Luồng Quản trị (Admin Dashboard):**
   * **Tab 1: Quản lý Tài khoản (User Service - Đạt):** Xem danh sách users, nạp tiền / sửa số dư nhanh (0đ, 3tr, 20tr, 50tr) để test các tình huống thiếu tiền / race condition.
   * **Tab 2: Quản lý Học phí Sinh viên (Tuition Service - Quý):** Danh sách sinh viên TDTU, tạo mới khoản nợ học phí, nút reset trạng thái `UNPAID` để test nộp lại. Nút **"Sửa HP"** để cập nhật số tiền, học kỳ, hạn nộp, trạng thái.
   * **Tab 3: Toàn bộ Lịch sử Giao dịch (Payment Service - Hoàn):** Xem realtime nhật ký giao dịch toàn hệ thống.

3. **Cơ chế Dual-Mode (Live Gateway & Mock Demo):**
   * Tích hợp sẵn nút chuyển đổi **Demo Mock** $\leftrightarrow$ **Live API** ngay trên thanh Navbar.
   * Khi Backend chưa chạy hoặc chưa xong, nhóm vẫn có thể demo toàn bộ luồng mượt mà 100% bằng Mock Data mà không lo bị nghẽn!

---

## 2. Cấu trúc thư mục mã nguồn
```
frontend/
├── index.html                 # Entrypoint HTML có title và favicon TDTU iBanking
├── package.json               # Cấu hình dependencies (React 19, Lucide Icons, Vite)
├── vite.config.js             # Cấu hình Vite build
└── src/
    ├── main.jsx               # Render React DOM
    ├── App.jsx                # Layout chính & điều hướng các màn hình
    ├── index.css              # Hệ thống CSS chuẩn iBanking (Navy blue, thẻ card, bảng biểu)
    ├── context/
    │   └── AuthContext.jsx    # Quản lý User session, số dư realtime, chế độ Mock/Live
    ├── services/
    │   ├── mockData.js        # Dữ liệu mẫu (Users, Sinh viên TDTU, Giao dịch)
    │   └── api.js             # Tầng gọi REST API tập trung qua API Gateway
    ├── components/
    │   ├── Navbar.jsx         # Thanh điều hướng, số dư khả dụng, nút toggle Demo
    │   ├── OtpModal.jsx       # Modal nhập OTP 6 số + Countdown 5:00 phút
    │   └── ReceiptModal.jsx   # Biên lai điện tử giao dịch thành công (hỗ trợ Print)
    └── pages/
        ├── LoginPage.jsx              # Màn hình đăng nhập
        ├── TuitionPaymentPage.jsx     # Tra cứu & Nộp học phí (Core workflow)
        ├── TransactionHistoryPage.jsx # Lịch sử giao dịch cá nhân
        └── AdminDashboardPage.jsx     # Bảng điều khiển Admin (CRUD cho Đạt, Quý, Hoàn)
```

---

## 3. Hướng dẫn khởi chạy

```bash
cd frontend
npm install
npm run dev
```
Truy cập: **http://localhost:5173/**
