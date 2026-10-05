// App.jsx - Giao diện chính phân hệ Frontend Đóng học phí iBanking (Đồ án SOA)
// Phụ trách bởi: Long (Frontend Developer)

import React, { useState } from "react";
import { AuthProvider, useAuth } from "./context/AuthContext";
import Navbar from "./components/Navbar";
import LoginPage from "./pages/LoginPage";
import TuitionPaymentPage from "./pages/TuitionPaymentPage";
import TransactionHistoryPage from "./pages/TransactionHistoryPage";
import AdminDashboardPage from "./pages/AdminDashboardPage";

function MainContent() {
  const { currentUser } = useAuth();
  const [activeTab, setActiveTab] = useState("tuition"); // 'tuition' | 'history' | 'admin'

  const isAdmin =
    currentUser?.role === "admin" ||
    currentUser?.username?.toLowerCase() === "admin";

  React.useEffect(() => {
    if (!isAdmin && activeTab === "admin") {
      setActiveTab("tuition");
    }
  }, [isAdmin, activeTab]);

  if (!currentUser) {
    return <LoginPage />;
  }

  return (
    <div className="app-layout">
      {/* Thanh Header Điều Hướng */}
      <Navbar activeTab={activeTab} setActiveTab={setActiveTab} />

      {/* Nội dung trang */}
      <main className="main-content">
        {activeTab === "tuition" && (
          <TuitionPaymentPage onGoToHistory={() => setActiveTab("history")} />
        )}
        {activeTab === "history" && (
          <TransactionHistoryPage onGoToPayment={() => setActiveTab("tuition")} />
        )}
        {activeTab === "admin" && <AdminDashboardPage />}
      </main>

      {/* Footer chuẩn ngân hàng số TDTU */}
      <footer className="apple-bank-footer">
        <div className="apple-footer-inner">
          <p>
            Hotline: <strong>037 484 3823</strong> • Email: <strong>phongtaichinh@tdtu.edu.vn</strong> • © 2026 made by SOA team (TDTU)
          </p>
          <p>Đồ án môn Kiến trúc Hướng dịch vụ (SOA / Microservices) • Đại học Tôn Đức Thắng</p>
        </div>
      </footer>
    </div>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <MainContent />
    </AuthProvider>
  );
}
