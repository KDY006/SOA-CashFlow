// api.js - Tầng kết nối REST API tập trung qua API Gateway (hoặc direct services)
// Hỗ trợ cả chế độ Live Backend lẫn Chế độ Giả lập (Mock Demo Mode) giúp nhóm bảo vệ đồ án mượt mà

import {
  INITIAL_USERS,
  INITIAL_STUDENTS_TUITION,
  INITIAL_TRANSACTIONS,
} from "./mockData";

// Cấu hình URL của API Gateway (do bạn Duy nhóm trưởng dựng)
export const API_BASE_URL =
  import.meta.env.VITE_API_URL || "http://localhost:8000/api";

// Quản lý LocalStorage để dữ liệu cập nhật bền vững khi chạy demo
const STORAGE_KEYS = {
  USERS: "soa_ibanking_users_v4",
  TUITIONS: "soa_ibanking_tuitions_v4",
  TRANSACTIONS: "soa_ibanking_transactions_v4",
  MOCK_MODE: "soa_ibanking_use_mock",
};

// Khởi tạo storage nếu chưa có (kèm tự động migrate dữ liệu mới)
function getLocalData(key, defaultData) {
  const stored = localStorage.getItem(key);
  if (!stored) {
    localStorage.setItem(key, JSON.stringify(defaultData));
    return defaultData;
  }
  try {
    const parsed = JSON.parse(stored);
    // Nếu dữ liệu cũ còn sót tài khoản mẫu cũ thì tự động làm mới về danh sách mới
    if (key === STORAGE_KEYS.USERS && Array.isArray(parsed)) {
      const hasOldUser = parsed.some((u) => u.username === "nguyenvana" || u.username === "52100888");
      if (hasOldUser) {
        localStorage.setItem(key, JSON.stringify(defaultData));
        return defaultData;
      }
    }
    return parsed;
  } catch (e) {
    console.error("Lỗi parse localStorage:", e);
    return defaultData;
  }
}

function setLocalData(key, data) {
  localStorage.setItem(key, JSON.stringify(data));
}

// Kiểm tra xem frontend có đang ép dùng Mock Mode hay không
export function isMockMode() {
  const val = localStorage.getItem(STORAGE_KEYS.MOCK_MODE);
  // Mặc định luôn là true khi chưa cấu hình để đảm bảo app luôn chạy được
  return val === null ? true : val === "true";
}

export function setMockMode(enabled) {
  localStorage.setItem(STORAGE_KEYS.MOCK_MODE, String(enabled));
}

// Reset dữ liệu mẫu về ban đầu nếu cần
export function resetMockData() {
  localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(INITIAL_USERS));
  localStorage.setItem(
    STORAGE_KEYS.TUITIONS,
    JSON.stringify(INITIAL_STUDENTS_TUITION)
  );
  localStorage.setItem(
    STORAGE_KEYS.TRANSACTIONS,
    JSON.stringify(INITIAL_TRANSACTIONS)
  );
}

// ==========================================
// 1. AUTH & USER SERVICE (Phân hệ User & Auth)
// ==========================================
export const authApi = {
  // Logic đăng nhập giả lập an toàn theo MSSV & Mật khẩu MSSV@
  async _mockLogin(username, password) {
    await new Promise((r) => setTimeout(r, 300));
    const users = getLocalData(STORAGE_KEYS.USERS, INITIAL_USERS);
    const cleanUser = String(username || "").trim().toLowerCase();
    const cleanPass = String(password || "").trim();

    const user = users.find((u) => {
      const uName = String(u.username || "").toLowerCase();
      const uMssv = String(u.mssv || "").toLowerCase();
      
      const isMatch = uName === cleanUser || uMssv === cleanUser;
      if (!isMatch) return false;

      // Tài khoản Admin: chấp nhận admin123, admin@, 123, admin
      if (uName === "admin" && (cleanPass === "admin123" || cleanPass === "admin@" || cleanPass === "123" || cleanPass === "admin")) {
        return true;
      }

      // Tài khoản Sinh viên: chuẩn là MSSV@ (ví dụ 52400138@) hoặc pass đã lưu hoặc 123
      if (cleanPass === `${uMssv}@` || cleanPass === `${uName}@` || u.password === cleanPass || cleanPass === "123") {
        return true;
      }
      return false;
    });

    if (!user) {
      throw new Error("Tên đăng nhập (MSSV) hoặc mật khẩu không chính xác!");
    }
    return {
      token: "mock-jwt-token-" + user.id + "-" + Date.now(),
      user: { ...user },
    };
  },

  // Đăng nhập
  async login(username, password) {
    if (isMockMode()) {
      return this._mockLogin(username, password);
    }

    try {
      const res = await fetch(`${API_BASE_URL}/auth/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, password }),
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Tên đăng nhập hoặc mật khẩu không chính xác!");
      }
      return await res.json();
    } catch (err) {
      if (err.name === "TypeError" || err.message?.includes("fetch")) {
        console.warn("[iBanking] Backend Gateway offline. Tự động kích hoạt Mock Mode để đăng nhập thông suốt.");
        setMockMode(true);
        return this._mockLogin(username, password);
      }
      throw err;
    }
  },

  // Lấy số dư và profile
  async getProfile(userId) {
    if (isMockMode()) {
      const users = getLocalData(STORAGE_KEYS.USERS, INITIAL_USERS);
      const user = users.find((u) => u.id === Number(userId));
      if (!user) throw new Error("Không tìm thấy người dùng");
      return { ...user };
    }

    const res = await fetch(`${API_BASE_URL}/users/${userId}/profile`);
    return res.json();
  },

  // API Quản trị Admin: Lấy danh sách users
  async getAdminUsers() {
    if (isMockMode()) {
      return getLocalData(STORAGE_KEYS.USERS, INITIAL_USERS);
    }
    const res = await fetch(`${API_BASE_URL}/admin/users`);
    return res.json();
  },

  // API Quản trị Admin: Nạp tiền / Chỉnh sửa số dư tài khoản
  async updateUserBalance(userId, newBalance) {
    if (isMockMode()) {
      const users = getLocalData(STORAGE_KEYS.USERS, INITIAL_USERS);
      const index = users.findIndex((u) => u.id === Number(userId));
      if (index === -1) throw new Error("User không tồn tại");
      users[index].balance = Number(newBalance);
      setLocalData(STORAGE_KEYS.USERS, users);
      return users[index];
    }

    const res = await fetch(`${API_BASE_URL}/admin/users/${userId}/balance`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ balance: Number(newBalance) }),
    });
    return res.json();
  },

  // API Quản trị Admin: Thêm tài khoản mới (Tự động đồng bộ sang Tuition Service)
  async createUser(userData) {
    if (isMockMode()) {
      const users = getLocalData(STORAGE_KEYS.USERS, INITIAL_USERS);
      const tuitions = getLocalData(STORAGE_KEYS.TUITIONS, INITIAL_STUDENTS_TUITION);

      const mssv = String(userData.mssv || userData.username || "").trim();
      const newUser = {
        id: Date.now(),
        ...userData,
        mssv: mssv,
        username: mssv || userData.username,
        password: userData.password || (mssv ? `${mssv}@` : "123"),
        balance: Number(userData.balance || 0),
        role: userData.role || "user",
      };
      users.push(newUser);
      setLocalData(STORAGE_KEYS.USERS, users);

      // ĐỒNG BỘ: Tự động tạo luôn khoản học phí tương ứng bên Tuition Service nếu là sinh viên
      if (newUser.role === "user" && mssv) {
        const existingTuition = tuitions.find((t) => String(t.mssv) === mssv);
        if (!existingTuition) {
          tuitions.push({
            id: Date.now() + 1,
            mssv: mssv,
            fullName: newUser.fullName,
            faculty: newUser.faculty || "Công nghệ thông tin",
            semester: userData.semester || "Học kỳ 1 - 2024-2025",
            amount: Number(userData.tuitionAmount || 12000000),
            status: "UNPAID",
            dueDate: "2024-12-15",
          });
          setLocalData(STORAGE_KEYS.TUITIONS, tuitions);
        }
      }

      return newUser;
    }

    const res = await fetch(`${API_BASE_URL}/admin/users`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(userData),
    });
    return res.json();
  },
};

// ==========================================
// 2. TUITION SERVICE (Phân hệ Học phí Sinh viên)
// ==========================================
export const tuitionApi = {
  // Logic giả lập tra cứu
  async _mockLookupTuition(mssv) {
    await new Promise((r) => setTimeout(r, 350));
    const cleanMssv = String(mssv).trim();
    const tuitions = getLocalData(
      STORAGE_KEYS.TUITIONS,
      INITIAL_STUDENTS_TUITION
    );
    const record = tuitions.find(
      (t) => String(t.mssv).toLowerCase() === cleanMssv.toLowerCase()
    );

    if (!record) {
      throw new Error(`Không tìm thấy sinh viên có MSSV: ${cleanMssv}`);
    }
    return { ...record };
  },

  // Tra cứu học phí theo MSSV
  async lookupTuition(mssv) {
    if (isMockMode()) {
      return this._mockLookupTuition(mssv);
    }

    try {
      const res = await fetch(`${API_BASE_URL}/tuitions/lookup?mssv=${mssv}`);
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Không thể tra cứu thông tin học phí");
      }
      return await res.json();
    } catch (err) {
      if (err.name === "TypeError" || err.message?.includes("fetch")) {
        console.warn("[iBanking] Backend Gateway offline. Tự động tra cứu bằng Mock Data.");
        setMockMode(true);
        return this._mockLookupTuition(mssv);
      }
      throw err;
    }
  },

  // API Admin: Lấy danh sách sinh viên & học phí
  async getAdminTuitions() {
    if (isMockMode()) {
      return getLocalData(STORAGE_KEYS.TUITIONS, INITIAL_STUDENTS_TUITION);
    }
    const res = await fetch(`${API_BASE_URL}/admin/tuitions`);
    return res.json();
  },

  // API Admin: Tạo khoản học phí mới (Tự động đồng bộ sang User Account nếu chưa có)
  async createTuition(tuitionData) {
    if (isMockMode()) {
      const tuitions = getLocalData(
        STORAGE_KEYS.TUITIONS,
        INITIAL_STUDENTS_TUITION
      );
      const users = getLocalData(STORAGE_KEYS.USERS, INITIAL_USERS);

      const mssv = String(tuitionData.mssv).trim();
      const newRecord = {
        id: Date.now(),
        ...tuitionData,
        mssv: mssv,
        amount: Number(tuitionData.amount),
        status: tuitionData.status || "UNPAID",
      };
      tuitions.push(newRecord);
      setLocalData(STORAGE_KEYS.TUITIONS, tuitions);

      // ĐỒNG BỘ: Tự động tạo luôn tài khoản iBanking tương ứng
      const existingUser = users.find(
        (u) => String(u.mssv) === mssv || String(u.username) === mssv
      );
      if (!existingUser) {
        users.push({
          id: Date.now() + 2,
          mssv: mssv,
          username: mssv,
          password: `${mssv}@`,
          fullName: tuitionData.fullName,
          faculty: tuitionData.faculty || "Công nghệ thông tin",
          email: `${mssv}@student.tdtu.edu.vn`,
          phoneNumber: "090" + Math.floor(1000000 + Math.random() * 9000000),
          accountNumber: "970422" + mssv,
          balance: 20000000,
          role: "user",
        });
        setLocalData(STORAGE_KEYS.USERS, users);
      }

      return newRecord;
    }

    const res = await fetch(`${API_BASE_URL}/admin/tuitions`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(tuitionData),
    });
    return res.json();
  },

  // API Admin: Chỉnh sửa học phí (số tiền, hạn nộp, học kỳ, trạng thái)
  async updateTuition(tuitionId, updatedFields) {
    if (isMockMode()) {
      const tuitions = getLocalData(
        STORAGE_KEYS.TUITIONS,
        INITIAL_STUDENTS_TUITION
      );
      const index = tuitions.findIndex((t) => t.id === Number(tuitionId));
      if (index === -1) throw new Error("Khoản học phí không tồn tại");

      tuitions[index] = {
        ...tuitions[index],
        ...updatedFields,
        amount: Number(
          updatedFields.amount !== undefined
            ? updatedFields.amount
            : tuitions[index].amount
        ),
      };
      setLocalData(STORAGE_KEYS.TUITIONS, tuitions);
      return tuitions[index];
    }

    const res = await fetch(`${API_BASE_URL}/admin/tuitions/${tuitionId}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(updatedFields),
    });
    return res.json();
  },

  // Đổi trạng thái gạch nợ (chỉ dùng nội bộ hoặc test)
  async updateTuitionStatus(tuitionId, status) {
    if (isMockMode()) {
      return this.updateTuition(tuitionId, { status });
    }

    const res = await fetch(`${API_BASE_URL}/tuitions/${tuitionId}/status`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ status }),
    });
    return res.json();
  },
};

// ==========================================
// 3. OTP & NOTIFICATION SERVICE (Phân hệ của bạn Phúc)
// ==========================================
// Lưu trữ OTP tạm trong session memory khi chạy mock
let currentMockOtpSession = null;

export const otpApi = {
  // Logic sinh OTP giả lập
  async _mockGenerateOtp({ userId, email, amount, mssv }) {
    await new Promise((r) => setTimeout(r, 400));
    const generatedOtp = String(
      Math.floor(100000 + Math.random() * 900000)
    );
    const transactionId = "TXN-" + Math.floor(100000 + Math.random() * 900000);

    currentMockOtpSession = {
      transactionId,
      otpCode: generatedOtp,
      userId,
      email,
      amount,
      mssv,
      createdAt: Date.now(),
      expiresAt: Date.now() + 5 * 60 * 1000,
      isUsed: false,
    };

    console.log(
      `%c[OTP SERVICE] Mã OTP gửi về email ${email}: ${generatedOtp} (Hạn 5 phút)`,
      "color: #10b981; font-weight: bold; font-size: 14px;"
    );

    return {
      success: true,
      transactionId,
      message: `Mã OTP đã được gửi đến email ${email}`,
      expiresInSeconds: 300,
      mockDebugOtp: generatedOtp,
    };
  },

  // Yêu cầu sinh mã OTP gửi về Email
  async generateOtp({ userId, email, amount, mssv }) {
    if (isMockMode()) {
      return this._mockGenerateOtp({ userId, email, amount, mssv });
    }

    try {
      const res = await fetch(`${API_BASE_URL}/otp/generate`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ userId, email, amount, mssv }),
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Không thể sinh mã OTP");
      }
      return await res.json();
    } catch (err) {
      if (err.name === "TypeError" || err.message?.includes("fetch")) {
        console.warn("[iBanking] Backend Gateway offline. Tự động sinh OTP bằng Mock Data.");
        setMockMode(true);
        return this._mockGenerateOtp({ userId, email, amount, mssv });
      }
      throw err;
    }
  },

  // Logic xác thực OTP giả lập
  async _mockVerifyOtp({ transactionId, otpCode }) {
    await new Promise((r) => setTimeout(r, 300));
    if (!currentMockOtpSession) {
      throw new Error("Phiên giao dịch OTP không tồn tại hoặc đã hết hạn!");
    }

    if (currentMockOtpSession.transactionId !== transactionId) {
      throw new Error("Mã giao dịch không khớp!");
    }

    if (Date.now() > currentMockOtpSession.expiresAt) {
      throw new Error("Mã OTP đã hết hiệu lực (quá hạn 5 phút)!");
    }

    if (currentMockOtpSession.isUsed) {
      throw new Error("Mã OTP này đã được sử dụng rồi!");
    }

    if (currentMockOtpSession.otpCode !== String(otpCode).trim()) {
      throw new Error("Mã OTP không chính xác. Vui lòng kiểm tra lại!");
    }

    currentMockOtpSession.isUsed = true;
    return { success: true, message: "Xác thực OTP thành công!" };
  },

  // Xác thực OTP
  async verifyOtp({ transactionId, otpCode }) {
    if (isMockMode()) {
      return this._mockVerifyOtp({ transactionId, otpCode });
    }

    try {
      const res = await fetch(`${API_BASE_URL}/otp/verify`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ transactionId, otpCode }),
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Xác thực OTP thất bại");
      }
      return await res.json();
    } catch (err) {
      if (err.name === "TypeError" || err.message?.includes("fetch")) {
        console.warn("[iBanking] Backend Gateway offline. Tự động xác thực OTP bằng Mock Data.");
        setMockMode(true);
        return this._mockVerifyOtp({ transactionId, otpCode });
      }
      throw err;
    }
  },
};

// ==========================================
// 4. PAYMENT SERVICE & TRANSACTIONS (Phân hệ của bạn Hoàn)
// ==========================================
export const paymentApi = {
  // Logic thanh toán giả lập
  async _mockExecutePayment({
    userId,
    tuitionId,
    mssv,
    studentName,
    amount,
    transactionId,
    otpCode,
  }) {
    await new Promise((r) => setTimeout(r, 600));

    // 1. Verify lại OTP
    await otpApi.verifyOtp({ transactionId, otpCode });

    // 2. Lấy dữ liệu user & tuition
    const users = getLocalData(STORAGE_KEYS.USERS, INITIAL_USERS);
    const tuitions = getLocalData(
      STORAGE_KEYS.TUITIONS,
      INITIAL_STUDENTS_TUITION
    );
    const transactions = getLocalData(
      STORAGE_KEYS.TRANSACTIONS,
      INITIAL_TRANSACTIONS
    );

    const userIndex = users.findIndex((u) => u.id === Number(userId));
    const tuitionIndex = tuitions.findIndex(
      (t) => t.id === Number(tuitionId)
    );

    if (userIndex === -1) throw new Error("Tài khoản thanh toán không tồn tại");
    if (tuitionIndex === -1)
      throw new Error("Khoản học phí cần thanh toán không tồn tại");

    const user = users[userIndex];
    const tuition = tuitions[tuitionIndex];

    // 3. Kiểm tra tính hợp lệ (Số dư, Trạng thái nợ)
    if (tuition.status === "PAID") {
      throw new Error(
        "Khoản học phí này đã được thanh toán trước đó bởi tài khoản khác!"
      );
    }

    if (user.balance < Number(amount)) {
      throw new Error(
        `Số dư khả dụng không đủ (${user.balance.toLocaleString()}đ < ${amount.toLocaleString()}đ)`
      );
    }

    // 4. Trừ tiền tài khoản
    users[userIndex].balance -= Number(amount);
    setLocalData(STORAGE_KEYS.USERS, users);

    // 5. Gạch nợ học phí
    tuitions[tuitionIndex].status = "PAID";
    setLocalData(STORAGE_KEYS.TUITIONS, tuitions);

    // 6. Ghi nhận lịch sử giao dịch
    const newTxn = {
      id: transactionId || "TXN-" + Date.now(),
      userId: user.id,
      userName: user.fullName,
      mssv: tuition.mssv,
      studentName: tuition.fullName,
      amount: Number(amount),
      status: "SUCCESS",
      createdAt: new Date().toLocaleString("vi-VN"),
      description: `Thanh toán học phí ${tuition.semester} cho MSSV ${tuition.mssv}`,
    };
    transactions.unshift(newTxn);
    setLocalData(STORAGE_KEYS.TRANSACTIONS, transactions);

    return {
      success: true,
      transaction: newTxn,
      newBalance: users[userIndex].balance,
      message: "Thanh toán học phí thành công!",
    };
  },

  // Xử lý thanh toán hoàn tất (Trừ tiền + Gạch nợ + Lưu lịch sử)
  async executePayment({
    userId,
    tuitionId,
    mssv,
    studentName,
    amount,
    transactionId,
    otpCode,
  }) {
    if (isMockMode()) {
      return this._mockExecutePayment({
        userId,
        tuitionId,
        mssv,
        studentName,
        amount,
        transactionId,
        otpCode,
      });
    }

    try {
      const res = await fetch(`${API_BASE_URL}/payments/process`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          userId,
          tuitionId,
          mssv,
          amount,
          transactionId,
          otpCode,
        }),
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "Thanh toán thất bại");
      }
      return await res.json();
    } catch (err) {
      if (err.name === "TypeError" || err.message?.includes("fetch")) {
        console.warn("[iBanking] Backend Gateway offline. Tự động thanh toán bằng Mock Data.");
        setMockMode(true);
        return this._mockExecutePayment({
          userId,
          tuitionId,
          mssv,
          studentName,
          amount,
          transactionId,
          otpCode,
        });
      }
      throw err;
    }
  },

  // Lịch sử giao dịch của 1 user / sinh viên
  async getUserTransactions(userId, userMssv) {
    if (isMockMode()) {
      const txns = getLocalData(
        STORAGE_KEYS.TRANSACTIONS,
        INITIAL_TRANSACTIONS
      );
      const cleanMssv = String(userMssv || "").trim();
      return txns.filter(
        (t) =>
          t.userId === Number(userId) ||
          (cleanMssv && String(t.mssv) === cleanMssv)
      );
    }
    const res = await fetch(`${API_BASE_URL}/users/${userId}/transactions`);
    return res.json();
  },

  // API Admin: Toàn bộ lịch sử giao dịch hệ thống
  async getAllTransactions() {
    if (isMockMode()) {
      return getLocalData(STORAGE_KEYS.TRANSACTIONS, INITIAL_TRANSACTIONS);
    }
    const res = await fetch(`${API_BASE_URL}/admin/transactions`);
    return res.json();
  },
};
