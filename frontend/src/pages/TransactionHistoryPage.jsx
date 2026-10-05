// TransactionHistoryPage.jsx - Màn hình Lịch sử Giao dịch chuẩn FinTech cao cấp TDTU iBanking
import React, { useState, useEffect } from "react";
import { useAuth } from "../services/authContext";
import { paymentApi } from "../services/api";
import ReceiptModal from "../components/ReceiptModal";
import FbAvatar from "../components/FbAvatar";
import {
  History,
  CheckCircle2,
  Clock,
  Eye,
  RefreshCw,
  Search,
  Sparkles,
  CreditCard,
  Building2,
  ShieldCheck,
  ArrowRight,
  TrendingUp,
  Receipt,
  FileCheck,
} from "lucide-react";

export default function TransactionHistoryPage({ onGoToPayment }) {
  const { currentUser } = useAuth();
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedTxn, setSelectedTxn] = useState(null);
  const [searchTerm, setSearchTerm] = useState("");

  const isAdmin = currentUser?.role === "admin";

  const loadHistory = async () => {
    if (!currentUser) return;
    setLoading(true);
    try {
      let data = [];
      if (isAdmin) {
        data = await paymentApi.getAllTransactions();
      } else {
        const studentMssv = currentUser.mssv || currentUser.username;
        data = await paymentApi.getUserTransactions(currentUser.id, studentMssv);
      }
      setTransactions(data || []);
    } catch (err) {
      console.error("Lỗi lấy lịch sử giao dịch:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadHistory();
  }, [currentUser]);

  const formatVND = (num) => {
    return new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
    }).format(num || 0);
  };

  // Lọc giao dịch theo tìm kiếm (Mã GD, MSSV, Tên sinh viên)
  const filteredTransactions = transactions.filter((txn) => {
    if (!searchTerm.trim()) return true;
    const term = searchTerm.toLowerCase();
    const idMatch = (txn.id || "").toLowerCase().includes(term);
    const mssvMatch = (txn.mssv || "").toLowerCase().includes(term);
    const nameMatch = (txn.studentName || txn.userName || "")
      .toLowerCase()
      .includes(term);
    return idMatch || mssvMatch || nameMatch;
  });

  // Tổng số tiền đã thanh toán
  const totalAmountPaid = transactions.reduce(
    (sum, txn) => sum + (txn.amount || 0),
    0
  );

  return (
    <div className="page-container history-page-container">
      {/* 1. HERO HEADER BANNER PHONG CÁCH FINTECH SANG TRỌNG */}
      <section className="history-hero-card">
        <div className="history-hero-inner">
          <div className="history-hero-content">
            <div className="history-hero-pill">
              <Sparkles size={14} />
              <span>
                {isAdmin
                  ? "Toàn quyền Quản trị viên • Toàn bộ giao dịch hệ thống"
                  : `Lịch sử giao dịch cá nhân • MSSV: ${currentUser?.mssv || currentUser?.username} (${currentUser?.fullName})`}
              </span>
            </div>
            <h1 className="history-hero-title">
              {isAdmin ? "Toàn Bộ Lịch Sử Giao Dịch Hệ Thống" : "Lịch Sử Nộp Học Phí Của Tôi"}
            </h1>
            <p className="history-hero-sub">
              {isAdmin
                ? "Bảng theo dõi và đối soát toàn bộ dòng tiền học phí của tất cả sinh viên trong hệ thống TDTU."
                : "Hệ thống theo dõi chi tiết biên lai nộp học phí điện tử có chữ ký số của sinh viên."}
            </p>
          </div>

          <div className="history-hero-actions">
            <button
              className="btn btn-hero-refresh"
              onClick={loadHistory}
              disabled={loading}
              title="Cập nhật danh sách giao dịch mới nhất"
            >
              <RefreshCw size={16} className={loading ? "spin" : ""} />
              <span>{loading ? "Đang tải..." : "Làm mới"}</span>
            </button>
            <button
              className="btn btn-hero-payment"
              onClick={onGoToPayment}
              title="Đến cổng thanh toán học phí"
            >
              <CreditCard size={16} />
              <span>Nộp học phí mới</span>
            </button>
          </div>
        </div>
      </section>

      {/* 2. FINTECH STATS SUMMARY CARDS */}
      <div className="history-stats-grid">
        <div className="history-stat-card">
          <div className="stat-card-top">
            <span className="stat-card-label">TỔNG TIỀN ĐÃ THANH TOÁN</span>
            <div className="stat-card-icon icon-blue">
              <TrendingUp size={18} />
            </div>
          </div>
          <div className="stat-card-value font-mono">
            {formatVND(totalAmountPaid)}
          </div>
          <div className="stat-card-foot text-muted">
            {transactions.length > 0
              ? `${transactions.length} giao dịch thành công`
              : "Chưa phát sinh giao dịch"}
          </div>
        </div>

        <div className="history-stat-card">
          <div className="stat-card-top">
            <span className="stat-card-label">SỐ GIAO DỊCH THÀNH CÔNG</span>
            <div className="stat-card-icon icon-emerald">
              <CheckCircle2 size={18} />
            </div>
          </div>
          <div className="stat-card-value font-mono">
            {transactions.length}
          </div>
          <div className="stat-card-foot text-success">
            100% Gạch nợ tự động tức thì
          </div>
        </div>

        <div className="history-stat-card">
          <div className="stat-card-top">
            <span className="stat-card-label">TÀI KHOẢN TRÍCH TIỀN</span>
            <div className="stat-card-icon icon-purple">
              <FbAvatar size={22} />
            </div>
          </div>
          <div className="stat-card-value stat-val-sm">
            {currentUser?.fullName || "Sinh viên"}
          </div>
          <div className="stat-card-foot text-muted">
            Tài khoản sẵn sàng giao dịch
          </div>
        </div>

        <div className="history-stat-card">
          <div className="stat-card-top">
            <span className="stat-card-label">ĐƠN VỊ THỤ HƯỞNG</span>
            <div className="stat-card-icon icon-amber">
              <Building2 size={18} />
            </div>
          </div>
          <div className="stat-card-value stat-val-sm">
            ĐH Tôn Đức Thắng
          </div>
          <div className="stat-card-foot text-muted">
            Cổng thanh toán tích hợp đào tạo
          </div>
        </div>
      </div>

      {/* 3. MAIN TABLE CARD & SEARCH FILTER */}
      <div className="card luxury-history-card">
        {/* Thanh tìm kiếm & lọc giao dịch */}
        <div className="history-card-header">
          <div className="header-left-title">
            <div className="history-header-icon-wrap">
              <Receipt size={20} />
            </div>
            <div>
              <h3 className="history-card-title">Danh sách biên lai điện tử</h3>
              <p className="history-card-desc">
                {transactions.length > 0
                  ? `Đang hiển thị ${filteredTransactions.length} trên tổng số ${transactions.length} giao dịch`
                  : "Dữ liệu giao dịch được đồng bộ từ Payment Microservice"}
              </p>
            </div>
          </div>

          {transactions.length > 0 && (
            <div className="history-search-wrap">
              <Search size={16} className="history-search-icon" />
              <input
                type="text"
                className="history-search-input"
                placeholder="Tìm MSSV, Mã GD, Họ tên..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
              {searchTerm && (
                <button
                  type="button"
                  className="history-clear-btn"
                  onClick={() => setSearchTerm("")}
                >
                  ×
                </button>
              )}
            </div>
          )}
        </div>

        {/* Nội dung dữ liệu hoặc trạng thái trống */}
        {loading ? (
          <div className="loading-state history-loading-state">
            <div className="spinner"></div>
            <p className="loading-text">
              Đang đối soát lịch sử giao dịch từ Payment Microservice...
            </p>
          </div>
        ) : transactions.length === 0 ? (
          <div className="empty-state history-empty-state">
            <div className="empty-state-halo">
              <div className="empty-icon-circle">
                <History size={46} color="#0066cc" />
              </div>
            </div>
            <h3 className="empty-title">Chưa Có Giao Dịch Nào</h3>
            <p className="empty-desc">
              Tài khoản iBanking của bạn hiện chưa thực hiện giao dịch nộp học
              phí trực tuyến nào. Mọi phiếu thu khi thanh toán xong sẽ được lưu
              trữ vĩnh viễn và cấp biên lai điện tử có mã xác thực tại đây.
            </p>
            <button
              className="btn btn-primary empty-action-btn"
              onClick={onGoToPayment}
            >
              <span>Nộp học phí ngay</span>
              <ArrowRight size={17} />
            </button>
            <div className="empty-sub-tip">
              <ShieldCheck size={14} color="#059669" />
              <span>Hỗ trợ đối soát và kiểm tra 24/7 • Hotline: (028) 37 755 035</span>
            </div>
          </div>
        ) : filteredTransactions.length === 0 ? (
          <div className="empty-state empty-search-state">
            <AlertCircle size={40} className="text-muted" />
            <h4 className="empty-title">Không tìm thấy giao dịch</h4>
            <p className="empty-desc">
              Không có giao dịch nào khớp với từ khóa "{searchTerm}".
            </p>
            <button
              className="btn btn-outline btn-sm"
              onClick={() => setSearchTerm("")}
            >
              Xóa bộ lọc
            </button>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="data-table history-table">
              <thead>
                <tr>
                  <th>Mã giao dịch</th>
                  <th>Thời gian</th>
                  <th>Sinh viên & MSSV</th>
                  <th>Học phí thanh toán</th>
                  <th>Trạng thái</th>
                  <th style={{ textAlign: "right" }}>Thao tác</th>
                </tr>
              </thead>
              <tbody>
                {filteredTransactions.map((txn) => (
                  <tr key={txn.id} className="history-row">
                    <td>
                      <span className="txn-id-pill font-mono">
                        {txn.id}
                      </span>
                    </td>
                    <td>
                      <div className="txn-time-wrap">
                        <Clock size={13} className="text-muted" />
                        <span className="text-muted">{txn.createdAt}</span>
                      </div>
                    </td>
                    <td>
                      <div className="txn-student-info">
                        <FbAvatar size={30} />
                        <div>
                          <div className="txn-student-name">
                            {txn.studentName || txn.userName}
                          </div>
                          <span className="badge-mssv">{txn.mssv}</span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div className="amount-highlight font-mono">
                        {formatVND(txn.amount)}
                      </div>
                      <span className="txn-free-fee">Miễn phí giao dịch</span>
                    </td>
                    <td>
                      <span className="status-badge badge-success">
                        <span className="status-dot"></span>
                        <CheckCircle2 size={13} />
                        <span>Thành công</span>
                      </span>
                    </td>
                    <td style={{ textAlign: "right" }}>
                      <button
                        className="btn btn-sm btn-view-receipt"
                        onClick={() => setSelectedTxn(txn)}
                        title="Mở và in biên lai điện tử"
                      >
                        <Eye size={14} />
                        <span>Xem biên lai</span>
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal xem lại biên lai khi bấm nút */}
      <ReceiptModal
        isOpen={Boolean(selectedTxn)}
        onClose={() => setSelectedTxn(null)}
        transaction={selectedTxn}
      />
    </div>
  );
}
