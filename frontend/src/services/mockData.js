// Dữ liệu mẫu chuẩn bị sẵn phục vụ Test & Demo (Seed data theo tài liệu đề bài)
// Đồng bộ 1-1 giữa User iBanking và Học phí sinh viên theo MSSV

export const INITIAL_USERS = [
  {
    id: 1,
    mssv: "52400138",
    username: "52400138",
    password: "52400138@",
    fullName: "Trần Hữu Long",
    faculty: "Công nghệ thông tin",
    email: "52400138@student.tdtu.edu.vn",
    phoneNumber: "0901234138",
    accountNumber: "9704220052400138",
    balance: 20000000, // 20.000.000 VNĐ
    role: "user",
  },
  {
    id: 999,
    username: "admin",
    password: "admin123",
    fullName: "Quản Trị Viên Hệ Thống",
    email: "admin.ibanking@tdtu.edu.vn",
    phoneNumber: "0988888888",
    accountNumber: "8888888888",
    balance: 999999999,
    role: "admin",
  },
];

// Danh sách sinh viên & các khoản học phí trường ĐH Tôn Đức Thắng (TDTU)
// Đồng bộ 100% với danh sách tài khoản sinh viên ở trên
export const INITIAL_STUDENTS_TUITION = [
  {
    id: 101,
    mssv: "52400138",
    fullName: "Trần Hữu Long",
    faculty: "Công nghệ thông tin",
    semester: "Học kỳ 1 - 2024-2025",
    amount: 12500000,
    status: "PAID", // Đã thanh toán học phí
    dueDate: "2024-11-15",
  },
];

// Lịch sử giao dịch ban đầu (Đồng bộ với sinh viên 52400138 Trần Hữu Long)
export const INITIAL_TRANSACTIONS = [
  {
    id: "TXN-52400138",
    userId: 1,
    userName: "Trần Hữu Long",
    mssv: "52400138",
    studentName: "Trần Hữu Long",
    amount: 12500000,
    status: "SUCCESS",
    createdAt: "2024-10-05 14:30:00",
    description: "Thanh toán học phí Học kỳ 1 - 2024-2025 cho MSSV 52400138",
  },
];
