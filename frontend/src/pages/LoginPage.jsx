// LoginPage.jsx - Giao diện Đăng nhập Chuẩn FinTech / iBanking TDTU
// Phong cách Apple & Stripe: Parallax SVG Waves, Glassmorphism, Micro-animations sống động

import React, { useState, useEffect } from "react";
import { useAuth } from "../services/authContext";
import { authApi } from "../services/api";
import { INITIAL_USERS } from "../services/mockData";
import FbAvatar from "../components/FbAvatar";
import {
  Building2,
  Lock,
  User,
  AlertCircle,
  ArrowRight,
  ShieldCheck,
  CheckCircle2,
  Phone,
  Mail,
  FileCheck,
  Check,
  ChevronRight,
  Sparkles,
  CreditCard,
  Wifi,
  Eye,
  EyeOff,
} from "lucide-react";

export default function LoginPage() {
  const { login, loading } = useAuth();
  // Fill sẵn mặc định là admin / admin123 theo yêu cầu
  const [username, setUsername] = useState("admin");
  const [password, setPassword] = useState("admin123");
  const [error, setError] = useState("");
  const [selectedUser, setSelectedUser] = useState("admin");
  const [activeCard, setActiveCard] = useState("visa");
  const [showFullCampus, setShowFullCampus] = useState(false);
  const [testAccounts, setTestAccounts] = useState(INITIAL_USERS);

  useEffect(() => {
    // Tự động đồng bộ danh sách tài khoản mới nhất (kể cả khi admin vừa thêm mới)
    authApi
      .getAdminUsers()
      .then((users) => {
        if (Array.isArray(users) && users.length > 0) {
          setTestAccounts(users);
        }
      })
      .catch(() => {});
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!username || !password) {
      setError("Vui lòng nhập tên đăng nhập và mật khẩu");
      return;
    }
    setError("");
    try {
      await login(username, password);
    } catch (err) {
      setError(err.message || "Tên đăng nhập hoặc mật khẩu không chính xác");
    }
  };

  const quickFill = (u, p) => {
    setUsername(u);
    setPassword(p);
    setSelectedUser(u);
    setError("");
  };

  return (
    <div className="login-experience-wrapper">
      {/* Background Campus Photo & Animated Rising Bubbles */}
      <div className="login-campus-backdrop" aria-hidden="true">
        <div
          className="login-campus-image"
          style={{ backgroundImage: "url(/tdtu-campus.jpg)" }}
        ></div>
        <div className="login-campus-overlay"></div>
      </div>

      {/* Animated Rising Glass Bubbles & Effervescent Fizz */}
      <div className="bubbles-container" aria-hidden="true">
        <span className="bubble b-1"></span>
        <span className="bubble b-2"></span>
        <span className="bubble b-3"></span>
        <span className="bubble b-4"></span>
        <span className="bubble b-5"></span>
        <span className="bubble b-6"></span>
        <span className="bubble b-7"></span>
        <span className="bubble b-8"></span>
        <span className="bubble b-9"></span>
        <span className="bubble b-10"></span>
        <span className="bubble b-11"></span>
        <span className="bubble b-12"></span>
        <span className="bubble b-13"></span>
        <span className="bubble b-14"></span>
        <span className="bubble b-15"></span>
        <span className="bubble b-16"></span>
        <span className="bubble b-17"></span>
        <span className="bubble b-18"></span>
        <span className="bubble b-19"></span>
        <span className="bubble b-20"></span>
      </div>

      {/* 1. FULL-WIDTH FROSTED BLUE HEADER BAR (GIỮ NGUYÊN MÀU KÍNH SAPPHIRE, TRÀN FULL VIỀN) */}
      <header className="apple-fullwidth-navbar">
        <div className="navbar-fullwidth-inner">
          {/* Cụm Logo & Tên trường */}
          <div className="navbar-brand-wrap">
            <div className="navbar-logo-badge">
              <img
                src="/tdtu-logo.png"
                alt="Logo Trường Đại học Tôn Đức Thắng"
                className="navbar-tdtu-logo"
              />
            </div>
            <div className="navbar-brand-text">
              <h1>ĐẠI HỌC TÔN ĐỨC THẮNG</h1>
              <p>CỔNG THANH TOÁN HỌC PHÍ TRỰC TUYẾN • iBANKING</p>
            </div>
          </div>

          {/* Hotline & Email liên hệ to rõ - Đảm bảo luôn nằm trên 1 hàng */}
          <div className="navbar-contact-pills">
            <span className="contact-item">
              <Phone size={15} /> <span>Hotline: <strong>037 484 3823</strong></span>
            </span>
            <span className="contact-divider">•</span>
            <span className="contact-item">
              <Mail size={15} /> <span>Email: <strong>phongtaichinh@tdtu.edu.vn</strong></span>
            </span>
          </div>

          {/* Nút Xem toàn cảnh trường & Badge bảo mật */}
          <div className="navbar-action-group">
            <button
              type="button"
              className={`campus-view-toggle-btn ${showFullCampus ? "active" : ""}`}
              onClick={() => setShowFullCampus(!showFullCampus)}
              title="Bấm để ẩn các box và ngắm toàn cảnh trường sắc nét 100%"
            >
              {showFullCampus ? <EyeOff size={15} /> : <Eye size={15} />}
              <span>{showFullCampus ? "Hiện form" : "Toàn cảnh TDTU"}</span>
            </button>

            <div className="navbar-security-badge">
              <ShieldCheck size={15} />
              <span>Bảo mật PCI-DSS</span>
            </div>
          </div>
        </div>
      </header>

      {/* 2. KHU VỰC SÂN KHẤU 2 CỘT GLASSMORPHISM CHUẨN FINTECH */}
      <main className={`apple-main-stage ${showFullCampus ? "stage-hidden" : ""}`}>
        {/* CỘT TRÁI: THÔNG BÁO VÀ QUY TRÌNH 4 BƯỚC NỘP HỌC PHÍ */}
        <section className="apple-info-card">
          <div className="info-announcement-header">
            <div className="semester-pill-badge">
              <Sparkles size={14} />
              <span>Học kỳ 1 • Năm học 2024 - 2025</span>
            </div>
            <h2 className="info-main-heading">
              Cổng nộp học phí trực tuyến dành cho sinh viên TDTU
            </h2>
            <p className="info-sub-paragraph">
              Hệ thống iBanking kết nối tự động giữa ngân hàng và phân hệ đào tạo,
              hỗ trợ tra cứu công nợ sinh viên tức thì, kiểm tra điều kiện số dư và xác thực
              thanh toán an toàn qua mã OTP Email dùng một lần.
            </p>
          </div>
          
          {/* KHU VỰC THẺ THANH TOÁN SỐ CHUẨN THỰC TẾ (TDTU VISA SIGNATURE & NAPAS STUDENT) */}
          <div className="cards-showcase-wrapper">
            <div className="cards-showcase-header">
              <div className="cards-showcase-badge">
                <CreditCard size={15} />
                <span>Thẻ iBanking & Thẻ Sinh Viên Số Liên Kết</span>
              </div>
              <div className="card-switch-pills">
                <button
                  type="button"
                  className={`card-pill-btn ${activeCard === "visa" ? "active" : ""}`}
                  onClick={() => setActiveCard("visa")}
                >
                  Thẻ Visa Signature
                </button>
                <button
                  type="button"
                  className={`card-pill-btn ${activeCard === "napas" ? "active" : ""}`}
                  onClick={() => setActiveCard("napas")}
                >
                  Thẻ Sinh Viên Napas
                </button>
              </div>
            </div>

            <div className="realistic-card-stage">
              <div
                className={`realistic-bank-card ${
                  activeCard === "visa" ? "card-theme-visa" : "card-theme-napas"
                }`}
              >
                {/* Vệt sáng quét phản quang Specular */}
                <div className="card-specular-glint"></div>

                {/* Hàng trên cùng: Logo TDTU + Tên Ngân Hàng + Hạng thẻ */}
                <div className="real-card-top">
                  <div className="card-bank-header-group">
                    <img
                      src="/tdtu-logo.png"
                      alt="TDTU Logo"
                      className="card-tdtu-mini-logo"
                    />
                    <div>
                      <div className="card-bank-title">TDTU iBanking</div>
                      <div className="card-sub-brand">
                        {activeCard === "visa" ? "DEBIT PLATINUM" : "SMART STUDENT ID"}
                      </div>
                    </div>
                  </div>
                  <span className="card-tier-pill">
                    {activeCard === "visa" ? "Signature" : "Thẻ Sinh Viên"}
                  </span>
                </div>

                {/* Hàng phần cứng: Chip vàng EMV 6-pad + Biểu tượng sóng không chạm Contactless */}
                <div className="real-card-hardware-row">
                  <div className="real-emv-chip" title="EMV Smart Security Chip">
                    <div className="emv-pad"></div>
                    <div className="emv-pad"></div>
                    <div className="emv-pad"></div>
                    <div className="emv-pad"></div>
                    <div className="emv-pad"></div>
                    <div className="emv-pad"></div>
                    <div className="emv-chip-groove"></div>
                  </div>

                  {/* Biểu tượng sóng thanh toán chạm Contactless */}
                  <svg
                    viewBox="0 0 24 24"
                    width="22"
                    height="22"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.2"
                    strokeLinecap="round"
                    className="contactless-wave-svg"
                    title="NFC Contactless Payment"
                  >
                    <path d="M4 12a14 14 0 0 1 2.5-8" />
                    <path d="M8.5 12a10 10 0 0 1 2-5.5" />
                    <path d="M13 12a6 6 0 0 1 1.5-3.5" />
                    <path d="M17.5 12a2 2 0 0 1 .5-1.5" />
                  </svg>
                </div>

                {/* Dãy 16 số thẻ dập nổi công nghệ ngân hàng (Ẩn số bằng ** theo chuẩn bảo mật PCI-DSS) */}
                <div className="real-card-number">
                  {activeCard === "visa" ? "4111 88** **** 9988" : "9704 22** **** 0888"}
                </div>

                {/* Hàng dưới cùng: Chủ thẻ, Ngày hết hạn, Logo Mạng lưới thanh toán */}
                <div className="real-card-bottom">
                  <div>
                    <div className="card-label-caption">
                      {activeCard === "visa" ? "CHỦ THẺ / CARDHOLDER" : "HỌ VÀ TÊN SINH VIÊN"}
                    </div>
                    <div className="card-embossed-name">
                      {selectedUser === "admin"
                        ? "ADMINISTRATOR"
                        : (testAccounts.find((u) => u.username === selectedUser)?.fullName?.toUpperCase() ||
                           (selectedUser === "tranthib" ? "TRAN THI BICH" : "NGUYEN VAN AN"))}
                    </div>
                  </div>

                  <div style={{ textAlign: "center" }}>
                    <div className="card-label-caption">
                      {activeCard === "visa" ? "VALID THRU" : "MSSV / ID"}
                    </div>
                    <div className="card-expiry-val">
                      {activeCard === "visa" ? "12/**" : "5210****"}
                    </div>
                  </div>

                  {/* Logo thanh toán quốc tế VISA hoặc nội địa NAPAS */}
                  <div>
                    {activeCard === "visa" ? (
                      <svg
                        viewBox="0 0 80 26"
                        width="66"
                        height="22"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        title="VISA Official Logo"
                      >
                        <path
                          d="M31.2 1.3L20.4 25.1H13.6L8.3 5.4C8 4.2 7.7 3.8 6.7 3.3C5.1 2.4 2.4 1.7 0 1.2L0.2 0.3H11C12.4 0.3 13.6 1.3 13.9 2.8L16.5 17.2L23.4 0.3H31.2V1.3ZM58.7 17.1C58.8 10.6 49.7 10.2 49.8 7.3C49.8 6.4 50.7 5.5 52.6 5.2C53.5 5.1 56.1 5 58.9 6.3L60 1.1C58.5 0.5 56.5 0 54 0C47.4 0 42.8 3.5 42.7 8.5C42.6 12.2 45.9 14.3 48.4 15.6C51 16.9 51.9 17.7 51.9 18.9C51.8 20.6 49.7 21.4 47.9 21.4C44.5 21.4 42.6 20.9 41 20.1L39.8 25.5C41.5 26.2 44.4 26.8 47.4 26.8C54.4 26.8 58.6 23.3 58.7 17.1ZM76 25.1H82L76.8 0.3H71.3C70.1 0.3 69.1 1 68.7 2.1L58.6 25.1H65.6L67 21.3H75.5L76 25.1ZM68.9 16.1L72.4 6.8L74.4 16.1H68.9ZM41.4 0.3L36 25.1H29.3L34.7 0.3H41.4Z"
                          fill="#ffffff"
                        />
                        <path
                          d="M11 0.3H0.2L0 1.2C2.4 1.7 5.1 2.4 6.7 3.3C7.7 3.8 8 4.2 8.3 5.4L13.6 25.1H20.4L31.2 1.3H23.4L16.5 17.2L13.9 2.8C13.6 1.3 12.4 0.3 11 0.3Z"
                          fill="#f9a825"
                        />
                      </svg>
                    ) : (
                      <div className="napas-brand-badge" title="NAPAS National Payment Gateway">
                        <span
                          style={{
                            color: "#0066b3",
                            fontWeight: "900",
                            fontStyle: "italic",
                            fontSize: "1.1rem",
                            letterSpacing: "-0.04em",
                          }}
                        >
                          na
                        </span>
                        <span
                          style={{
                            color: "#f37021",
                            fontWeight: "900",
                            fontStyle: "italic",
                            fontSize: "1.1rem",
                            letterSpacing: "-0.04em",
                          }}
                        >
                          pas
                        </span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h3 className="workflow-heading">
              Quy trình 4 bước hoàn tất học phí:
            </h3>
            <div className="workflow-grid-4">
              <div className="workflow-step-box">
                <div className="step-circle-badge">1</div>
                <div className="step-text-wrap">
                  <h4>Tra cứu MSSV</h4>
                  <p>Nhập mã sinh viên để hệ thống tự động kiểm tra phiếu thu học phí.</p>
                </div>
              </div>

              <div className="workflow-step-box">
                <div className="step-circle-badge">2</div>
                <div className="step-text-wrap">
                  <h4>Kiểm tra điều kiện</h4>
                  <p>Đối soát số dư tài khoản đủ thanh toán 100% toàn bộ khoản nợ.</p>
                </div>
              </div>

              <div className="workflow-step-box">
                <div className="step-circle-badge">3</div>
                <div className="step-text-wrap">
                  <h4>Xác thực OTP Email</h4>
                  <p>Nhập mã OTP 6 số bảo mật (hiệu lực 5 phút, dùng 1 lần duy nhất).</p>
                </div>
              </div>

              <div className="workflow-step-box">
                <div className="step-circle-badge">4</div>
                <div className="step-text-wrap">
                  <h4>Gạch nợ & Biên lai</h4>
                  <p>Hệ thống tự động gạch nợ tức thì và cấp biên lai có giá trị pháp lý.</p>
                </div>
              </div>
            </div>
          </div>

        </section>

        {/* CỘT PHẢI: FORM ĐĂNG NHẬP APPLE GLASSMORPHISM */}
        <section className="apple-login-card">
          <div className="login-title-row">
            <h2 className="login-heading">Đăng nhập iBanking</h2>
            <p className="login-subhead">
              Vui lòng nhập tài khoản để tra cứu và hoàn tất học phí
            </p>
          </div>

          <form onSubmit={handleSubmit}>
            {error && (
              <div className="alert-box alert-danger">
                <AlertCircle size={18} />
                <span>{error}</span>
              </div>
            )}

            <div className="form-group">
              <label className="form-label">Tên đăng nhập / Mã khách hàng</label>
              <div className="input-container">
                <input
                  type="text"
                  className="apple-input"
                  placeholder="Ví dụ: 52400138 hoặc admin"
                  value={username}
                  onChange={(e) => {
                    setUsername(e.target.value);
                    setSelectedUser("");
                  }}
                  autoFocus
                />
                <User size={19} className="input-icon-left" />
              </div>
            </div>

            <div className="form-group">
              <label className="form-label">Mật khẩu truy cập</label>
              <div className="input-container">
                <input
                  type="password"
                  className="apple-input"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
                <Lock size={19} className="input-icon-left" />
              </div>
            </div>

            <button
              type="submit"
              className="btn-apple-primary"
              disabled={loading}
            >
              <span>{loading ? "Đang xác thực bảo mật..." : "Đăng nhập hệ thống"}</span>
              <ArrowRight size={19} />
            </button>
          </form>

          {/* KHU VỰC TÀI KHOẢN MẪU KIỂM THỬ (APPLE WALLET CARDS) */}
          <div className="test-accounts-section">
            <div className="test-accounts-head">
              <span className="test-title-text">
                <FileCheck size={16} color="#0066cc" />
                Dữ liệu mẫu kiểm thử (ĐỒ ÁN SOA):
              </span>
              <span className="test-action-tip">Bấm để tự điền</span>
            </div>

            <div className="test-accounts-list">
              {testAccounts
                .filter(
                  (u) =>
                    u.role !== "admin" &&
                    u.username?.toLowerCase() !== "admin"
                )
                .map((u) => {
                  const isSelected = selectedUser === u.username;
                  const isDeficit = u.balance < 10000000;
                  const defaultPass = u.password || `${u.username}@`;

                  return (
                    <div
                      key={u.id || u.username}
                      className={`wallet-user-card ${isSelected ? "active-selected" : ""}`}
                      onClick={() => quickFill(u.username, defaultPass)}
                      title={`Bấm để đăng nhập bằng tài khoản sinh viên ${u.fullName}`}
                    >
                      <div className="wallet-user-left">
                        {isSelected ? (
                          <div className="wallet-avatar-bubble">
                            <Check size={16} />
                          </div>
                        ) : (
                          <FbAvatar size={36} />
                        )}
                        <div>
                          <div className="wallet-user-name">{u.fullName}</div>
                          <div className="wallet-user-creds">
                            {u.username} • pass: {defaultPass}
                          </div>
                        </div>
                      </div>

                      {isDeficit ? (
                        <span className="wallet-badge-pill pill-red">
                          {new Intl.NumberFormat("vi-VN").format(u.balance)} đ (Thiếu)
                        </span>
                      ) : (
                        <span className="wallet-badge-pill pill-green">
                          {new Intl.NumberFormat("vi-VN").format(u.balance)} đ (Đủ nộp)
                        </span>
                      )}
                    </div>
                  );
                })}
            </div>
          </div>
        </section>
      </main>

      {/* 3. CHÂN TRANG FOOTER */}
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