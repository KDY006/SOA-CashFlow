// TuitionPaymentPage.jsx - Màn hình Tra cứu & Nộp học phí FinTech cao cấp
// Tích hợp Step Progress Indicator, Đối soát số dư và Quy trình Core Workflow

import React, { useState } from "react";
import { useAuth } from "../services/authContext";
import { tuitionApi, otpApi, paymentApi } from "../services/api";
import OtpModal from "../components/OtpModal";
import ReceiptModal from "../components/ReceiptModal";
import FbAvatar from "../components/FbAvatar";
import {
  Search,
  AlertCircle,
  CheckCircle,
  CheckCircle2,
  CreditCard,
  GraduationCap,
  Sparkles,
  ShieldCheck,
  Building2,
  ArrowRight,
  FileText,
  Calendar,
} from "lucide-react";

export default function TuitionPaymentPage({ onGoToHistory }) {
  const { currentUser, updateBalance } = useAuth();

  const [mssvInput, setMssvInput] = useState("");
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState("");
  const [tuitionData, setTuitionData] = useState(null);

  // Modal OTP & Biên lai
  const [isOtpOpen, setIsOtpOpen] = useState(false);
  const [otpLoading, setOtpLoading] = useState(false);
  const [activeTxnData, setActiveTxnData] = useState(null);

  const [isReceiptOpen, setIsReceiptOpen] = useState(false);
  const [lastTransaction, setLastTransaction] = useState(null);
  const [adminStudents, setAdminStudents] = useState([
    {
      mssv: "52400138",
      fullName: "Trần Hữu Long",
      amount: 12500000,
      status: "PAID",
    },
  ]);

  const formatVND = (num) => {
    return new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
    }).format(num || 0);
  };

  const isAdmin = currentUser?.role === "admin";

  React.useEffect(() => {
    if (isAdmin) {
      tuitionApi.getAdminTuitions().then((data) => {
        if (Array.isArray(data) && data.length > 0) {
          setAdminStudents(data);
        }
      }).catch(() => {});
    }
  }, [isAdmin]);

  // Tra cứu theo MSSV
  const handleLookup = async (e) => {
    if (e) e.preventDefault();
    const cleanMssv = mssvInput.trim();
    if (!cleanMssv) {
      setSearchError("Vui lòng nhập Mã số sinh viên (MSSV) để tra cứu");
      return;
    }

    setSearching(true);
    setSearchError("");
    setTuitionData(null);
    setLastTransaction(null);

    try {
      const data = await tuitionApi.lookupTuition(cleanMssv);
      setTuitionData(data);
      if (data && data.status === "PAID") {
        prepareReceiptForPaid(data);
      }
    } catch (err) {
      setSearchError(err.message || "Không tìm thấy dữ liệu học phí của sinh viên này");
    } finally {
      setSearching(false);
    }
  };

  // Tự động nạp khoản học phí của chính sinh viên khi đăng nhập
  React.useEffect(() => {
    if (!isAdmin && currentUser) {
      const studentMssv = currentUser.mssv || currentUser.username;
      if (studentMssv && studentMssv !== "admin") {
        setMssvInput(studentMssv);
        setSearching(true);
        setSearchError("");
        setLastTransaction(null);
        tuitionApi
          .lookupTuition(studentMssv)
          .then((data) => {
            setTuitionData(data);
            if (data && data.status === "PAID") {
              prepareReceiptForPaid(data);
            }
          })
          .catch((err) => {
            setSearchError(err.message || "Chưa có dữ liệu học phí cho tài khoản này");
          })
          .finally(() => setSearching(false));
      }
    }
  }, [currentUser, isAdmin]);

  // Chuẩn bị dữ liệu biên lai khi học phí đã thanh toán
  const prepareReceiptForPaid = async (data) => {
    try {
      const allTxns = await paymentApi.getAllTransactions();
      const found = allTxns.find((t) => t.mssv === data.mssv);
      if (found) {
        setLastTransaction(found);
      } else {
        setLastTransaction({
          id: `TXN-883921`,
          userId: currentUser?.id || 1,
          userName: currentUser?.fullName || "Nguyễn Văn An",
          studentName: data.fullName,
          mssv: data.mssv,
          amount: data.amount,
          status: "SUCCESS",
          createdAt: data.paidAt || "2024-10-01 09:15:30",
          description: `Thanh toán học phí ${data.semester || "HK1 2024-2025"} cho MSSV ${data.mssv}`,
        });
      }
    } catch (err) {
      setLastTransaction({
        id: `TXN-REC-${data.mssv}`,
        userId: currentUser?.id || 1,
        userName: currentUser?.fullName || "Nguyễn Văn An",
        studentName: data.fullName,
        mssv: data.mssv,
        amount: data.amount,
        status: "SUCCESS",
        createdAt: "2024-10-01 09:15:30",
        description: `Thanh toán học phí cho MSSV ${data.mssv}`,
      });
    }
  };

  // Mở modal biên lai điện tử
  const handleOpenReceipt = async () => {
    if (lastTransaction) {
      setIsReceiptOpen(true);
      return;
    }
    if (tuitionData) {
      await prepareReceiptForPaid(tuitionData);
      setIsReceiptOpen(true);
    }
  };

  // Click chọn nhanh MSSV mẫu
  const handleSelectQuickMssv = (code) => {
    setMssvInput(code);
    setSearchError("");
    setSearching(true);
    setLastTransaction(null);
    tuitionApi
      .lookupTuition(code)
      .then((data) => {
        setTuitionData(data);
        if (data && data.status === "PAID") {
          prepareReceiptForPaid(data);
        }
      })
      .catch((err) => setSearchError(err.message))
      .finally(() => setSearching(false));
  };

  // Khởi tạo thanh toán & gửi OTP
  const handleInitiatePayment = async () => {
    if (!tuitionData || !currentUser) return;

    if (currentUser.balance < tuitionData.amount) {
      alert("Số dư khả dụng của bạn không đủ để thanh toán khoản học phí này!");
      return;
    }

    if (tuitionData.status === "PAID") {
      alert("Khoản học phí này đã được thanh toán rồi!");
      return;
    }

    setOtpLoading(true);
    try {
      const otpRes = await otpApi.generateOtp({
        userId: currentUser.id,
        email: currentUser.email,
        amount: tuitionData.amount,
        mssv: tuitionData.mssv,
      });

      setActiveTxnData({
        transactionId: otpRes.transactionId,
        amount: tuitionData.amount,
        studentName: tuitionData.fullName,
        mssv: tuitionData.mssv,
        email: currentUser.email,
        mockDebugOtp: otpRes.mockDebugOtp,
      });
      setIsOtpOpen(true);
    } catch (err) {
      alert(err.message || "Lỗi khởi tạo giao dịch OTP");
    } finally {
      setOtpLoading(false);
    }
  };

  // Gửi lại OTP
  const handleResendOtp = async () => {
    try {
      const otpRes = await otpApi.generateOtp({
        userId: currentUser.id,
        email: currentUser.email,
        amount: tuitionData.amount,
        mssv: tuitionData.mssv,
      });
      setActiveTxnData((prev) => ({
        ...prev,
        transactionId: otpRes.transactionId,
        mockDebugOtp: otpRes.mockDebugOtp,
      }));
    } catch (err) {
      alert(err.message || "Không thể gửi lại OTP");
    }
  };

  // Submit OTP & Trừ tiền
  const handleOtpSubmit = async (otpCode) => {
    if (!activeTxnData) return;
    setOtpLoading(true);

    try {
      const result = await paymentApi.executePayment({
        userId: currentUser.id,
        tuitionId: tuitionData.id,
        mssv: tuitionData.mssv,
        studentName: tuitionData.fullName,
        amount: tuitionData.amount,
        transactionId: activeTxnData.transactionId,
        otpCode,
      });

      updateBalance(result.newBalance);
      setTuitionData((prev) => ({ ...prev, status: "PAID" }));
      setIsOtpOpen(false);
      setLastTransaction(result.transaction);
      setIsReceiptOpen(true);
    } catch (err) {
      alert(err.message || "Giao dịch thanh toán thất bại!");
    } finally {
      setOtpLoading(false);
    }
  };

  const isBalanceEnough = tuitionData
    ? currentUser.balance >= tuitionData.amount
    : true;
  const isAlreadyPaid = tuitionData?.status === "PAID";

  return (
    <div className="page-container tuition-page-wrapper">
      {/* 1. HERO BANNER CHUẨN FINTECH CAO CẤP */}
      <section className="tuition-hero-banner">
        <div className="tuition-hero-inner">
          <div className="tuition-hero-text">
            <div className="tuition-hero-pill">
              <Sparkles size={14} />
              <span>Học kỳ 1 • Năm học 2024 - 2025</span>
            </div>
            <h1 className="tuition-hero-title">Tra cứu & Nộp Học Phí Trực Tuyến</h1>
            <p className="tuition-hero-sub">
              Cổng thanh toán iBanking liên kết trực tiếp phân hệ đào tạo TDTU • Tự động đối soát và gạch nợ tức thì.
            </p>
          </div>

          {/* Widget Tóm tắt thẻ & tài khoản liên kết */}
          <div className="tuition-account-card-widget">
            <div className="account-widget-head">
              <div className="widget-chip-icon">
                <CreditCard size={18} />
              </div>
              <span className="widget-card-type">Thẻ Sinh Viên Số • iBanking</span>
              <span className="widget-status-dot" title="Tài khoản sẵn sàng giao dịch"></span>
            </div>
            <div className="widget-card-number">9704 22•• •••• 0888</div>
            <div className="widget-card-foot">
              <div>
                <div className="widget-label">CHỦ TÀI KHOẢN</div>
                <div className="widget-holder-name">{currentUser.fullName}</div>
              </div>
              <div className="widget-balance-block">
                <div className="widget-label">SỐ DƯ KHẢ DỤNG</div>
                <div className="widget-balance-val">{formatVND(currentUser.balance)}</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 2. KHU VỰC 2 CỘT CHÍNH: TRA CỨU & KẾT QUẢ PHIẾU THU */}
      <div className="payment-grid">
        {/* CỘT TRÁI: Form Tra cứu MSSV */}
        <div className="luxury-glass-panel lookup-card">
          <div className="panel-header">
            <div className="panel-icon-wrap">
              <Search size={20} />
            </div>
            <div>
              <h3 className="panel-title">Tra cứu thông tin học phí</h3>
              <p className="panel-desc">Nhập Mã số sinh viên để đối soát công nợ</p>
            </div>
          </div>

          <form onSubmit={handleLookup} className="lookup-form">
            <div className="form-group lookup-form-group">
              <label className="form-label lookup-label">
                <span>MÃ SỐ SINH VIÊN (MSSV)</span>
                <span className="lookup-label-tip">Đối soát công nợ tức thì</span>
              </label>
              <div className="search-input-group">
                <div className="search-input-field-wrap">
                  <Search size={18} className="search-field-icon" />
                  <input
                    type="text"
                    className="search-field-input"
                    placeholder="Nhập MSSV... (Ví dụ: 52400138)"
                    value={mssvInput}
                    readOnly={!isAdmin}
                    style={!isAdmin ? { backgroundColor: "#f8fafc", cursor: "default" } : {}}
                    onChange={(e) => isAdmin && setMssvInput(e.target.value)}
                  />
                  {isAdmin && mssvInput && (
                    <button
                      type="button"
                      className="search-clear-btn"
                      onClick={() => setMssvInput("")}
                    >
                      ×
                    </button>
                  )}
                </div>
                <button
                  type="submit"
                  className="btn-lookup-action"
                  disabled={searching}
                >
                  <Search size={17} />
                  <span>{searching ? "Đang tìm..." : (isAdmin ? "Tra cứu" : "Làm mới")}</span>
                </button>
              </div>
            </div>

            {searchError && (
              <div className="alert-box alert-danger">
                <AlertCircle size={18} />
                <span>{searchError}</span>
              </div>
            )}

            {/* Nếu là sinh viên: Khóa quyền tra cứu MSSV khác và hiển thị chứng chỉ liên kết */}
            {!isAdmin ? (
              <div
                style={{
                  marginTop: "1.25rem",
                  padding: "0.85rem 1rem",
                  background: "#eff6ff",
                  border: "1px solid #bfdbfe",
                  borderRadius: "10px",
                  display: "flex",
                  alignItems: "center",
                  gap: "0.75rem",
                  fontSize: "0.86rem",
                  color: "#1e40af",
                }}
              >
                <ShieldCheck size={20} color="#2563eb" style={{ flexShrink: 0 }} />
                <div>
                  <strong>Liên kết tài khoản xác thực:</strong> Bạn đang đăng nhập bằng tài khoản sinh viên{" "}
                  <strong>{currentUser?.fullName}</strong> (MSSV: <strong>{currentUser?.mssv || currentUser?.username}</strong>). Bạn chỉ có quyền tra cứu và nộp học phí của chính mình.
                </div>
              </div>
            ) : (
              /* Dành riêng cho Admin: Chọn nhanh MSSV kiểm thử */
              <div className="quick-mssv-container">
                <div className="quick-mssv-header">
                  <Sparkles size={14} color="#0066cc" />
                  <span>MSSV mẫu kiểm thử nhanh (Admin Mode - Click để tự điền):</span>
                </div>
                <div className="quick-mssv-grid">
                  {adminStudents.map((st) => (
                    <div
                      key={st.mssv}
                      className={`quick-student-pill ${mssvInput === st.mssv ? "is-selected" : ""}`}
                      onClick={() => handleSelectQuickMssv(st.mssv)}
                    >
                      <div className="pill-student-left">
                        <FbAvatar size={26} />
                        <div>
                          <div className="pill-mssv">{st.mssv}</div>
                          <div className="pill-subname">{st.fullName}</div>
                        </div>
                      </div>
                      <span className={`pill-status-tag ${st.status === "PAID" ? "tag-paid" : "tag-unpaid"}`}>
                        {st.status === "PAID" ? "Đã thanh toán" : `${Math.round(st.amount / 1000000)}tr • Chưa nộp`}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </form>

          {/* Quy định & Cam kết bảo mật thanh toán */}
          <div className="tuition-security-box">
            <div className="security-box-header">
              <ShieldCheck size={18} className="security-shield-icon" />
              <span>Cam kết bảo mật & Quy định giao dịch:</span>
            </div>
            <ul className="security-points-list">
              <li>
                <CheckCircle size={14} className="point-icon" />
                <span>Thanh toán <strong>toàn bộ một lần (100%)</strong> theo đúng định mức đào tạo.</span>
              </li>
              <li>
                <CheckCircle size={14} className="point-icon" />
                <span>Khóa đối soát đồng thời <strong>(Concurrency Lock)</strong> tránh trừ tiền trùng lặp.</span>
              </li>
              <li>
                <CheckCircle size={14} className="point-icon" />
                <span>Xác thực bảo mật 2 lớp <strong>OTP Email 2FA</strong> có hiệu lực 5 phút.</span>
              </li>
            </ul>
          </div>
        </div>

        {/* CỘT PHẢI: Chi tiết khoản nợ & Xác nhận thanh toán */}
        <div className="luxury-glass-panel result-card">
          {!tuitionData && !searching && (
            <div className="empty-interactive-stage">
              <div className="floating-crest-wrap">
                <div className="crest-halo-glow"></div>
                <div className="crest-icon-box">
                  <GraduationCap size={48} className="crest-svg-icon" />
                </div>
              </div>
              <h3 className="empty-stage-title">Sẵn sàng kiểm tra phiếu thu học phí</h3>
              <p className="empty-stage-desc">
                Vui lòng nhập Mã số sinh viên (MSSV) ở bảng bên trái hoặc click chọn một sinh viên mẫu để xem chi tiết khoản nộp.
              </p>
              <div className="workflow-steps-micro">
                <div className="micro-step">
                  <span className="step-num">1</span>
                  <span>Nhập MSSV</span>
                </div>
                <div className="micro-step-arrow">➔</div>
                <div className="micro-step">
                  <span className="step-num">2</span>
                  <span>Đối soát số dư</span>
                </div>
                <div className="micro-step-arrow">➔</div>
                <div className="micro-step">
                  <span className="step-num">3</span>
                  <span>Xác thực OTP</span>
                </div>
                <div className="micro-step-arrow">➔</div>
                <div className="micro-step">
                  <span className="step-num">4</span>
                  <span>Cấp biên lai</span>
                </div>
              </div>
            </div>
          )}

          {searching && (
            <div className="loading-state-box">
              <div className="fintech-spinner"></div>
              <h4 className="loading-state-title">Đang kết nối Tuition Service</h4>
              <p className="loading-state-desc">Hệ thống đang truy vấn cơ sở dữ liệu học vụ TDTU...</p>
            </div>
          )}

          {tuitionData && (
            <div className="tuition-detail-container">
              {/* Header phiếu thu */}
              <div className="detail-header-row">
                <div>
                  <div className="detail-tag-row">
                    <span className="badge-semester">{tuitionData.semester}</span>
                    <span className="badge-academic-year">Năm học 2024 - 2025</span>
                  </div>
                  <h2 className="student-name">{tuitionData.fullName}</h2>
                  <p className="student-meta">
                    MSSV: <strong>{tuitionData.mssv}</strong> • Khoa: <strong>{tuitionData.faculty}</strong>
                  </p>
                </div>
                <div>
                  {isAlreadyPaid ? (
                    <span className="status-badge badge-success">
                      <CheckCircle size={15} /> Đã hoàn thành
                    </span>
                  ) : (
                    <span className="status-badge badge-warning">
                      <AlertCircle size={15} /> Chưa thanh toán
                    </span>
                  )}
                </div>
              </div>

              {/* Bảng chi tiết khoản nộp */}
              <div className="tuition-fee-ticket">
                <div className="ticket-header-ribbon">
                  <span>PHIẾU BÁO THU HỌC PHÍ CHÍNH THỨC</span>
                  <span className="ticket-id">#{tuitionData.id || "HP-2024-TDTU"}</span>
                </div>

                <div className="ticket-content">
                  <div className="fee-row">
                    <span className="fee-label">Hạn đóng học phí:</span>
                    <strong className="fee-val-highlight">{tuitionData.dueDate || "30/11/2024 (Theo quy định)"}</strong>
                  </div>
                  <div className="fee-row">
                    <span className="fee-label">Hình thức thanh toán:</span>
                    <strong>Trực tuyến qua TDTU iBanking (Gạch nợ tức thì)</strong>
                  </div>
                  <div className="fee-row">
                    <span className="fee-label">Cổng xử lý trung gian:</span>
                    <strong>iBanking Payment Gateway • Bảo mật 2FA</strong>
                  </div>

                  <div className="fee-divider"></div>

                  <div className="fee-total-row">
                    <div>
                      <span className="total-label">Tổng học phí cần đóng:</span>
                      <p className="total-sub">Đã bao gồm học phí tín chỉ & các khoản dịch vụ SV</p>
                    </div>
                    <span className="total-amount">{formatVND(tuitionData.amount)}</span>
                  </div>
                </div>
              </div>

              {/* Đối chiếu số dư khả dụng */}
              <div className="balance-check-section">
                <div className="balance-comparison-row">
                  <div className="comp-item">
                    <span className="comp-label">Số dư khả dụng của bạn:</span>
                    <span className="comp-val">{formatVND(currentUser.balance)}</span>
                  </div>
                  <div className="comp-divider-vertical"></div>
                  <div className="comp-item">
                    <span className="comp-label">Số tiền cần trích:</span>
                    <span className="comp-val comp-accent">{formatVND(tuitionData.amount)}</span>
                  </div>
                </div>

                {!isBalanceEnough && !isAlreadyPaid && (
                  <div className="alert-box alert-danger">
                    <AlertCircle size={22} className="alert-icon-shrink" />
                    <div>
                      <strong>Số dư tài khoản không đủ để hoàn tất!</strong>
                      <p>
                        Tài khoản của bạn còn thiếu{" "}
                        <strong className="deficit-highlight">
                          {formatVND(tuitionData.amount - currentUser.balance)}
                        </strong>{" "}
                        để thanh toán khoản này.{" "}
                        {currentUser.role === "admin"
                          ? "(Bạn có thể vào tab Admin Dashboard để nạp thêm số dư kiểm thử)."
                          : "(Vui lòng nạp thêm tiền vào tài khoản iBanking để hoàn tất giao dịch)."}
                      </p>
                    </div>
                  </div>
                )}

                {isAlreadyPaid && (
                  <div className="tuition-paid-natural-card">
                    <div className="paid-natural-icon-wrap">
                      <CheckCircle2 size={22} className="paid-natural-check-icon" />
                    </div>
                    <div className="paid-natural-text">
                      <h4 className="paid-natural-title">Khoản học phí này đã được thanh toán hoàn tất!</h4>
                      <p className="paid-natural-desc">
                        Sinh viên đã hoàn thành nghĩa vụ học phí học kỳ này, bạn có thể xem lại biên lai điện tử bên dưới.
                      </p>
                    </div>
                  </div>
                )}
              </div>

              {/* Nút hành động */}
              {isAlreadyPaid ? (
                <button
                  type="button"
                  className="btn-tuition-action btn-receipt-view"
                  onClick={handleOpenReceipt}
                >
                  <FileText size={18} />
                  <span>Xem lại biên lai điện tử đã cấp</span>
                  <ArrowRight size={16} />
                </button>
              ) : (
                <button
                  type="button"
                  className="btn-tuition-action btn-pay-now"
                  onClick={handleInitiatePayment}
                  disabled={!isBalanceEnough || otpLoading}
                >
                  <CreditCard size={19} />
                  <span>
                    {otpLoading
                      ? "Đang khởi tạo mã OTP xác thực..."
                      : !isBalanceEnough
                      ? "Số dư tài khoản không đủ để thanh toán"
                      : "Xác nhận & Nhận mã OTP Email (2FA)"}
                  </span>
                  <ArrowRight size={18} />
                </button>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Modal OTP */}
      <OtpModal
        isOpen={isOtpOpen}
        onClose={() => setIsOtpOpen(false)}
        onSubmit={handleOtpSubmit}
        transactionData={activeTxnData}
        onResendOtp={handleResendOtp}
        loading={otpLoading}
      />

      {/* Modal Biên lai điện tử */}
      <ReceiptModal
        isOpen={isReceiptOpen}
        onClose={() => setIsReceiptOpen(false)}
        transaction={lastTransaction}
      />
    </div>
  );
}
