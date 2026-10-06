// ReceiptModal.jsx - Hóa đơn / Biên lai thanh toán học phí điện tử iBanking

import React from "react";
import { CheckCircle2, Printer, X, Download, Building2 } from "lucide-react";

export default function ReceiptModal({ isOpen, onClose, transaction }) {
  if (!isOpen || !transaction) return null;

  const formatVND = (num) => {
    return new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
    }).format(num || 0);
  };

  const handlePrint = () => {
    window.print();
  };

  return (
    <div className="modal-backdrop">
      <div className="modal-dialog receipt-dialog">
        <div className="receipt-paper">
          {/* Header Biên lai */}
          <div className="receipt-header">
            <div className="receipt-brand">
              <div className="brand-logo-small">
                <img src="/tdtu-logo.png" alt="TDTU Logo" className="receipt-brand-logo-img" />
              </div>
              <div>
                <h4 className="receipt-bank-name">ĐẠI HỌC TÔN ĐỨC THẮNG - iBANKING</h4>
                <p className="receipt-bank-sub">HỆ THỐNG THU HỌC PHÍ TRỰC TUYẾN</p>
              </div>
            </div>
            <button className="receipt-close-btn no-print" onClick={onClose}>
              <X size={18} />
            </button>
          </div>

          {/* Dấu trạng thái thành công */}
          <div className="receipt-status-section">
            <div className="success-icon-ring">
              <CheckCircle2 size={40} color="#16a34a" />
            </div>
            <h2 className="receipt-status-title">GIAO DỊCH THÀNH CÔNG</h2>
            <div className="receipt-amount-display">
              {formatVND(transaction.amount)}
            </div>
            <p className="receipt-date">{transaction.createdAt}</p>
          </div>

          <div className="receipt-divider"></div>

          {/* Bảng chi tiết giao dịch */}
          <div className="receipt-details">
            <div className="detail-item">
              <span className="detail-label">Mã giao dịch</span>
              <span className="detail-value font-mono font-bold">{transaction.id}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Tài khoản trích tiền</span>
              <span className="detail-value">{transaction.userName}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Sinh viên nộp học phí</span>
              <span className="detail-value font-bold">{transaction.studentName}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Mã số sinh viên (MSSV)</span>
              <span className="detail-value font-mono font-bold text-primary">
                {transaction.mssv}
              </span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Nội dung giao dịch</span>
              <span className="detail-value">{transaction.description}</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Phí dịch vụ thanh toán</span>
              <span className="detail-value text-success font-bold">0 VNĐ (Miễn phí)</span>
            </div>
            <div className="detail-item">
              <span className="detail-label">Trạng thái gạch nợ</span>
              <span className="detail-value text-success font-bold">ĐÃ GẠCH NỢ HỌC PHÍ</span>
            </div>
          </div>

          <div className="receipt-note">
            <p>
              * Biên lai điện tử có giá trị xác nhận sinh viên đã hoàn thành nghĩa vụ học phí.
              Thông báo xác nhận giao dịch cũng đã được gửi đến email người thanh toán.
            </p>
          </div>

          {/* Nút hành động */}
          <div className="receipt-actions no-print">
            <button className="btn btn-outline" onClick={handlePrint}>
              <Printer size={16} />
              <span>In biên lai</span>
            </button>
            <button className="btn btn-primary" onClick={onClose}>
              Hoàn tất giao dịch
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
