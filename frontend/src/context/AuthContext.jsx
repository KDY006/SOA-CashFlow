// AuthContext.jsx - Quản lý trạng thái đăng nhập, thông tin tài khoản và số dư iBanking

import React, { createContext, useContext, useState, useEffect } from "react";
import { authApi, isMockMode, setMockMode, resetMockData } from "../services/api";

const AuthContext = createContext();

export function AuthProvider({ children }) {
  const [currentUser, setCurrentUser] = useState(() => {
    const saved = localStorage.getItem("soa_current_user");
    return saved ? JSON.parse(saved) : null;
  });

  const [mockActive, setMockActive] = useState(isMockMode());
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (currentUser) {
      localStorage.setItem("soa_current_user", JSON.stringify(currentUser));
    } else {
      localStorage.removeItem("soa_current_user");
    }
  }, [currentUser]);

  // Cập nhật số dư realtime khi có thanh toán thành công hoặc admin nạp tiền
  const updateBalance = (newBalance) => {
    if (!currentUser) return;
    setCurrentUser((prev) => ({
      ...prev,
      balance: Number(newBalance),
    }));
  };

  // Đăng nhập
  const login = async (username, password) => {
    setLoading(true);
    try {
      const res = await authApi.login(username, password);
      setCurrentUser(res.user);
      return res.user;
    } finally {
      setLoading(false);
    }
  };

  // Đăng xuất
  const logout = () => {
    setCurrentUser(null);
    localStorage.removeItem("soa_current_user");
  };

  // Chuyển đổi giữa chế độ Mock Demo và Live Backend
  const toggleMockMode = () => {
    const nextVal = !mockActive;
    setMockMode(nextVal);
    setMockActive(nextVal);
  };

  const handleResetData = () => {
    resetMockData();
    // Cập nhật lại user hiện tại nếu có
    if (currentUser) {
      authApi.getProfile(currentUser.id).then((fresh) => {
        if (fresh) setCurrentUser(fresh);
      });
    }
    alert("Đã làm mới dữ liệu mẫu về mặc định ban đầu!");
  };

  return (
    <AuthContext.Provider
      value={{
        currentUser,
        loading,
        mockActive,
        login,
        logout,
        updateBalance,
        toggleMockMode,
        handleResetData,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
