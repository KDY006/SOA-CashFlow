// OtpModal.jsx - Hộp thoại xác thực OTP với đồng hồ đếm ngược 5 phút (300 giây)
// Gắn chặt với Transaction ID, bảo đảm tính duy nhất và chỉ dùng 1 lần theo chuẩn đề bài

import React, { useState, useEffect } from "react";
import { ShieldCheck, Clock, AlertTriangle, KeyRound, X } from "lucide-react";

export default function OtpModal({
  isOpen,
  onClose,
  onSubmit,
  transactionData,
  onResendOtp,
  loading,
}) {
  const [otpCode, setOtpCode] = useState("");
  const [timeLeft, setTimeLeft] = useState(300); // 5 phút = 300 giây
  const [errorMsg, setErrorMsg] = useState("");

  // Bắt đầu đếm ngược 5 phút khi mở modal
  useEffect(() => {
    if (!isOpen) {
      setOtpCode("");
      setErrorMsg("");
      return;
    }

    setTimeLeft(300);
    const timer = setInterval(() => {
      setTimeLeft((prev) => {
        if (prev <= 1) {
          clearInterval(timer);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [isOpen, transactionData?.transactionId]);

  if (!isOpen || !transactionData) return null;

  // Format mm:ss
  const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
  };

  const handleVerify = (e) => {
    e.preventDefault();
    if (timeLeft === 0) {
      setErrorMsg("Mã OTP đã hết hạn hiệu lực (5 phút). Vui lòng gửi lại mã mới!");
      return;
    }
    if (otpCode.trim().length !== 6) {
      setErrorMsg("Vui lòng nhập đầy đủ 6 chữ số mã OTP!");
      return;
    }
    setErrorMsg("");
    onSubmit(otpCode.trim());
  };

  const handleResend = async () => {
    setErrorMsg("");
    setOtpCode("");
    setTimeLeft(300);
    if (onResendOtp) {
      await onResendOtp();
    }
  };

  // Ẩn bớt email người nhận bảo mật: vana.nguyen@gmail.com -> va***@gmail.com
  const maskEmail = (email) => {
    if (!email) return "email của bạn";
    const [name, domain] = email.split("@");
    if (!domain) return email;
    const masked = name.slice(0, 2) + "***" + (name.length > 5 ? name.slice(-1) : "");
    return `${masked}@${domain}`;
  };

  const formatVND = (num) => {
    return new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
    }).format(num || 0);
  };

  return (
    <div className="modal-backdrop">
      <div className="modal-dialog">
        <div className="modal-header">
          <div className="modal-title-group">
            <div className="modal-icon-badge">
              <ShieldCheck size={22} color="#0056b3" />
            </div>
            <div>
              <h3 className="modal-title">Xác thực giao dịch OTP</h3>
              <p className="modal-desc">Bảo mật xác thực 2 lớp qua Email</p>
            </div>
          </div>
          <button className="modal-close-btn" onClick={onClose} disabled={loading}>
            <X size={18} />
          </button>
        </div>

        <div className="modal-body">
          {/* Thông tin tóm tắt khoản tiền nộp */}
          <div className="otp-summary-box">
            <div className="summary-row">
              <span className="text-muted">Mã giao dịch:</span>
              <span className="font-mono font-bold">{transactionData.transactionId}</span>
            </div>
            <div className="summary-row">
              <span className="text-muted">Số tiền thanh toán:</span>
              <span className="amount-highlight font-bold">
                {formatVND(transactionData.amount)}
              </span>
            </div>
            <div className="summary-row">
              <span className="text-muted">Sinh viên thụ hưởng:</span>
              <span>
                {transactionData.studentName} ({transactionData.mssv})
              </span>
            </div>
          </div>

          {/* Hướng dẫn nhận mã */}
          <div className="otp-email-notice">
            <p>
              Mã xác thực đã được gửi đến email{" "}
              <strong>{maskEmail(transactionData.email)}</strong>. Mã chỉ có hiệu lực
              trong vòng <strong>5 phút</strong> và sử dụng duy nhất một lần.
            </p>
          </div>

          {/* Gợi ý OTP khi đang ở chế độ Mock Test để Giảng viên/Nhóm test nhanh */}
          {transactionData.mockDebugOtp && (
            <div className="mock-otp-helper">
              <KeyRound size={16} color="#059669" />
              <span>
                Mã OTP nhận được (Test mode):{" "}
                <strong className="copy-otp" onClick={() => setOtpCode(transactionData.mockDebugOtp)}>
                  {transactionData.mockDebugOtp}
                </strong>{" "}
                (Bấm để điền nhanh)
              </span>
            </div>
          )}

          {/* Form nhập OTP */}
          <form onSubmit={handleVerify}>
            <div className="otp-input-wrapper">
              <label className="form-label text-center">
                Nhập mã OTP 6 chữ số
              </label>
              <input
                type="text"
                maxLength={6}
                autoFocus
                placeholder="• • • • • •"
                className="otp-code-input"
                value={otpCode}
                onChange={(e) => {
                  const val = e.target.value.replace(/\D/g, "");
                  setOtpCode(val);
                  if (errorMsg) setErrorMsg("");
                }}
                disabled={loading || timeLeft === 0}
              />
            </div>

            {/* Đồng hồ đếm ngược */}
            <div className="otp-timer-row">
              <Clock size={16} className={timeLeft <= 30 ? "text-danger" : "text-muted"} />
              <span className={timeLeft <= 30 ? "timer-countdown danger" : "timer-countdown"}>
                Thời gian hiệu lực còn: <strong>{formatTime(timeLeft)}</strong>
              </span>
            </div>

            {errorMsg && (
              <div className="alert-box alert-danger">
                <AlertTriangle size={16} />
                <span>{errorMsg}</span>
              </div>
            )}

            <div className="modal-actions">
              <button
                type="button"
                className="btn btn-outline"
                onClick={handleResend}
                disabled={loading}
              >
                Gửi lại mã OTP
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={loading || otpCode.length !== 6 || timeLeft === 0}
              >
                {loading ? "Đang xử lý..." : "Xác nhận & Thanh toán"}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
