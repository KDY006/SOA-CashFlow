// Navbar.jsx - Thanh điều hướng chuẩn giao diện Ngân hàng số TDTU iBanking

import React from "react";
import { useAuth } from "../services/authContext";
import FbAvatar from "./FbAvatar";
import {
  CreditCard,
  History,
  Shield,
  LogOut,
  RefreshCw,
  Wallet,
  Building2,
  ToggleLeft,
  ToggleRight,
} from "lucide-react";

export default function Navbar({ activeTab, setActiveTab }) {
  const { currentUser, logout, mockActive, toggleMockMode, handleResetData } =
    useAuth();

  if (!currentUser) return null;

  // Format tiền tệ VNĐ chuẩn
  const formatVND = (num) => {
    return new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
    }).format(num || 0);
  };

  const isAdmin =
    currentUser?.role === "admin" ||
    currentUser?.username?.toLowerCase() === "admin";

  return (
    <header className="navbar-container">
      <div className="navbar-wrapper">
        {/* Brand & Logo */}
        <div className="navbar-brand" onClick={() => setActiveTab("tuition")}>
          <img
            src="/tdtu-logo.png"
            alt="TDTU Logo"
            className="navbar-brand-logo"
          />
          <div className="navbar-brand-text">
            <span className="navbar-brand-title">TDTU iBanking</span>
            <span className="navbar-brand-subtitle">CỔNG THANH TOÁN HỌC PHÍ</span>
          </div>
        </div>

        {/* Menu Điều hướng */}
        <nav className="navbar-nav">
          <button
            className={`nav-item ${activeTab === "tuition" ? "active" : ""}`}
            onClick={() => setActiveTab("tuition")}
          >
            <CreditCard size={17} />
            <span>Nộp học phí</span>
          </button>

          <button
            className={`nav-item ${activeTab === "history" ? "active" : ""}`}
            onClick={() => setActiveTab("history")}
          >
            <History size={17} />
            <span>Lịch sử giao dịch</span>
          </button>

          {/* Tab Admin CHỈ hiển thị cho tài khoản Quản trị viên (admin) */}
          {isAdmin && (
            <button
              className={`nav-item admin-nav ${
                activeTab === "admin" ? "active" : ""
              }`}
              onClick={() => setActiveTab("admin")}
              title="Khu vực dành riêng cho Quản trị viên"
            >
              <Shield size={17} />
              <span>Admin Dashboard</span>
            </button>
          )}
        </nav>

        {/* Phần thông tin Tài khoản & Tùy chọn Demo */}
        <div className="navbar-actions">
          {/* Nút bật/tắt Mock Mode tiện lợi cho demo khi chưa có backend */}
          <button
            className={`mode-toggle-btn ${mockActive ? "is-mock" : "is-live"}`}
            onClick={toggleMockMode}
            title={
              mockActive
                ? "Đang ở chế độ Mock Data (Bấm để chuyển sang kết nối Gateway thật)"
                : "Đang kết nối API Gateway thật (Bấm để chuyển sang Mock Data)"
            }
          >
            <span className="mode-led-dot"></span>
            <span>{mockActive ? "Demo Mock" : "Live Gateway"}</span>
          </button>

          {/* Reset dữ liệu test */}
          {mockActive && (
            <button
              className="icon-action-btn"
              onClick={handleResetData}
              title="Khôi phục lại số dư và dữ liệu test ban đầu"
            >
              <RefreshCw size={15} />
            </button>
          )}

          {/* Hộp hiển thị số dư người dùng */}
          <div className="user-balance-pill">
            <div className="balance-pill-left">
              <Wallet size={16} className="balance-wallet-icon" />
              <div className="balance-text-group">
                <span className="balance-label">SỐ DƯ KHẢ DỤNG</span>
                <span className="balance-value">{formatVND(currentUser.balance)}</span>
              </div>
            </div>
          </div>

          {/* Tên User & Đăng xuất */}
          <div className="user-profile-menu">
            <FbAvatar size={34} />
            <div className="user-info">
              <span className="user-name">{currentUser.fullName}</span>
              <span className="user-role-badge">
                {currentUser.role === "admin" ? "Quản trị viên" : "Sinh viên"}
              </span>
            </div>
            <button
              className="logout-btn"
              onClick={logout}
              title="Đăng xuất khỏi hệ thống"
            >
              <LogOut size={16} />
            </button>
          </div>
        </div>
      </div>
    </header>
  );
}
