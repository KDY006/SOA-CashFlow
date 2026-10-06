// AdminDashboardPage.jsx - Bảng điều khiển Quản trị viên phục vụ CRUD và Test Demo Hệ thống
// Tích hợp các API của 3 bạn Backend: Đạt (User), Quý (Tuition), Hoàn (Payment)

import React, { useState, useEffect } from "react";
import { useAuth } from "../services/authContext";
import { authApi, tuitionApi, paymentApi } from "../services/api";
import FbAvatar from "../components/FbAvatar";
import {
  Users,
  GraduationCap,
  Receipt,
  PlusCircle,
  DollarSign,
  RotateCcw,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Search,
  Edit3,
} from "lucide-react";

export default function AdminDashboardPage() {
  const { currentUser, updateBalance } = useAuth();
  const [activeTab, setActiveTab] = useState("users"); // 'users' | 'tuitions' | 'transactions'

  // State danh sách
  const [users, setUsers] = useState([]);
  const [tuitions, setTuitions] = useState([]);
  const [transactions, setTransactions] = useState([]);
  const [loading, setLoading] = useState(false);

  // Modal / Form state
  const [selectedUserForTopup, setSelectedUserForTopup] = useState(null);
  const [topupAmount, setTopupAmount] = useState(10000000); // 10 triệu

  const [showAddTuition, setShowAddTuition] = useState(false);
  const [newTuitionForm, setNewTuitionForm] = useState({
    mssv: "",
    fullName: "",
    faculty: "Công nghệ thông tin",
    semester: "Học kỳ 1 - 2024-2025",
    amount: 10000000,
    dueDate: "2024-12-01",
  });

  // State chỉnh sửa học phí
  const [editingTuition, setEditingTuition] = useState(null);
  const [editTuitionForm, setEditTuitionForm] = useState({
    amount: 10000000,
    semester: "Học kỳ 1 - 2024-2025",
    dueDate: "2024-12-01",
    status: "UNPAID",
  });

  const [showAddUser, setShowAddUser] = useState(false);
  const [newUserForm, setNewUserForm] = useState({
    username: "",
    fullName: "",
    email: "",
    faculty: "Công nghệ thông tin",
    balance: 20000000,
    tuitionAmount: 12000000,
    role: "user",
  });

  const formatVND = (num) => {
    return new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
    }).format(num || 0);
  };

  // Tải dữ liệu theo tab
  const loadData = async () => {
    setLoading(true);
    try {
      if (activeTab === "users") {
        const u = await authApi.getAdminUsers();
        setUsers(u || []);
      } else if (activeTab === "tuitions") {
        const t = await tuitionApi.getAdminTuitions();
        setTuitions(t || []);
      } else if (activeTab === "transactions") {
        const tx = await paymentApi.getAllTransactions();
        setTransactions(tx || []);
      }
    } catch (err) {
      console.error("Lỗi tải dữ liệu admin:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [activeTab]);

  // Xử lý nạp tiền / sửa số dư tài khoản
  const handleUpdateBalance = async (e) => {
    e.preventDefault();
    if (!selectedUserForTopup) return;
    try {
      await authApi.updateUserBalance(selectedUserForTopup.id, Number(topupAmount));
      // Nếu user đang đăng nhập chính là user được nạp tiền -> cập nhật realtime
      if (currentUser && currentUser.id === selectedUserForTopup.id) {
        updateBalance(Number(topupAmount));
      }
      setSelectedUserForTopup(null);
      loadData();
      alert(`Đã cập nhật số dư cho ${selectedUserForTopup.fullName} thành ${formatVND(topupAmount)}!`);
    } catch (err) {
      alert(err.message || "Lỗi cập nhật số dư");
    }
  };

  // Xử lý tạo khoản học phí mới
  const handleCreateTuition = async (e) => {
    e.preventDefault();
    if (!newTuitionForm.mssv || !newTuitionForm.fullName || !newTuitionForm.amount) {
      alert("Vui lòng điền đủ MSSV, Họ tên và Số tiền học phí!");
      return;
    }
    try {
      await tuitionApi.createTuition(newTuitionForm);
      setShowAddTuition(false);
      setNewTuitionForm({
        mssv: "",
        fullName: "",
        faculty: "Công nghệ thông tin",
        semester: "Học kỳ 1 - 2024-2025",
        amount: 10000000,
        dueDate: "2024-12-01",
      });
      loadData();
      alert("Tạo khoản học phí sinh viên mới thành công!");
    } catch (err) {
      alert(err.message || "Lỗi tạo khoản học phí");
    }
  };

  // Đổi trạng thái học phí (phục vụ test nộp lại)
  const handleResetTuitionStatus = async (tuitionId, newStatus) => {
    try {
      await tuitionApi.updateTuitionStatus(tuitionId, newStatus);
      loadData();
    } catch (err) {
      alert("Lỗi đổi trạng thái học phí");
    }
  };

  // Xử lý tạo user mới
  const handleCreateUser = async (e) => {
    e.preventDefault();
    if (!newUserForm.username || !newUserForm.fullName) {
      alert("Vui lòng điền username và họ tên!");
      return;
    }
    try {
      await authApi.createUser(newUserForm);
      setShowAddUser(false);
      setNewUserForm({
        username: "",
        password: "123",
        fullName: "",
        email: "",
        phoneNumber: "0900000000",
        balance: 20000000,
        role: "user",
      });
      loadData();
      alert("Tạo tài khoản người dùng mới thành công!");
    } catch (err) {
      alert(err.message || "Lỗi tạo tài khoản");
    }
  };

  // Xử lý mở modal sửa học phí
  const handleOpenEditTuition = (tuition) => {
    setEditingTuition(tuition);
    setEditTuitionForm({
      amount: tuition.amount,
      semester: tuition.semester || "Học kỳ 1 - 2024-2025",
      dueDate: tuition.dueDate || "2024-12-01",
      status: tuition.status || "UNPAID",
    });
  };

  // Xử lý lưu chỉnh sửa học phí
  const handleSaveEditTuition = async (e) => {
    e.preventDefault();
    if (!editingTuition) return;
    try {
      await tuitionApi.updateTuition(editingTuition.id, editTuitionForm);
      setEditingTuition(null);
      loadData();
      alert(`Đã cập nhật thông tin học phí cho sinh viên ${editingTuition.fullName} (${editingTuition.mssv})!`);
    } catch (err) {
      alert(err.message || "Lỗi cập nhật học phí");
    }
  };

  // Tính tổng số tiền đã thu được
  const totalRevenue = transactions.reduce((sum, item) => sum + (item.amount || 0), 0);

  return (
    <div className="page-container">
      <div className="page-header page-header-row">
        <div>
          <h1 className="page-title">Admin Management Dashboard</h1>
          <p className="page-subtitle">
            Bảng quản trị hệ thống phục vụ Giảng viên & Nhóm kiểm thử các API CRUD của User, Tuition & Payment Service
          </p>
        </div>
        <button className="btn btn-outline" onClick={loadData} disabled={loading}>
          <RefreshCw size={16} className={loading ? "spin" : ""} />
          <span>Làm mới dữ liệu</span>
        </button>
      </div>

      {/* Tabs Quản trị */}
      <div className="admin-tabs-nav">
        <button
          className={`admin-tab-btn ${activeTab === "users" ? "active" : ""}`}
          onClick={() => setActiveTab("users")}
        >
          <Users size={18} />
          <span>1. Quản lý Tài khoản & Số dư</span>
        </button>
        <button
          className={`admin-tab-btn ${activeTab === "tuitions" ? "active" : ""}`}
          onClick={() => setActiveTab("tuitions")}
        >
          <GraduationCap size={18} />
          <span>2. Quản lý Học phí Sinh viên</span>
        </button>
        <button
          className={`admin-tab-btn ${activeTab === "transactions" ? "active" : ""}`}
          onClick={() => setActiveTab("transactions")}
        >
          <Receipt size={18} />
          <span>3. Toàn bộ Lịch sử Giao dịch</span>
        </button>
      </div>

      {/* TAB 1: QUẢN LÝ USERS */}
      {activeTab === "users" && (
        <div className="card table-card">
          <div className="card-header-actions">
            <div>
              <h3 className="card-title">Danh sách Tài khoản iBanking</h3>
              <p className="card-desc">Phân hệ Quản lý Tài khoản & Số dư iBanking</p>
            </div>
            <button
              className="btn btn-primary btn-sm"
              onClick={() => setShowAddUser(true)}
            >
              <PlusCircle size={16} />
              <span>Thêm tài khoản mới</span>
            </button>
          </div>

          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Tên đăng nhập</th>
                  <th>Họ và tên</th>
                  <th>Email</th>
                  <th>Số điện thoại</th>
                  <th>Số dư khả dụng</th>
                  <th>Vai trò</th>
                  <th>Hành động</th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id}>
                    <td className="text-muted font-mono">{u.id}</td>
                    <td className="font-bold">{u.username}</td>
                    <td>
                      <div style={{ display: "flex", alignItems: "center", gap: "0.6rem" }}>
                        <FbAvatar size={24} />
                        <span>{u.fullName}</span>
                      </div>
                    </td>
                    <td className="text-muted">{u.email}</td>
                    <td>{u.phoneNumber}</td>
                    <td className="font-bold text-success font-mono">
                      {formatVND(u.balance)}
                    </td>
                    <td>
                      <span className={`status-badge ${u.role === "admin" ? "badge-primary" : "badge-info"}`}>
                        {u.role}
                      </span>
                    </td>
                    <td>
                      <button
                        className="btn btn-sm btn-outline"
                        onClick={() => {
                          setSelectedUserForTopup(u);
                          setTopupAmount(u.balance);
                        }}
                      >
                        <DollarSign size={14} />
                        <span>Chỉnh số dư</span>
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 2: QUẢN LÝ HỌC PHÍ */}
      {activeTab === "tuitions" && (
        <div className="card table-card">
          <div className="card-header-actions">
            <div>
              <h3 className="card-title">Danh sách Học phí Sinh viên TDTU</h3>
              <p className="card-desc">Phân hệ Quản lý Dữ liệu Học phí Sinh viên TDTU</p>
            </div>
            <button
              className="btn btn-primary btn-sm"
              onClick={() => setShowAddTuition(true)}
            >
              <PlusCircle size={16} />
              <span>Tạo khoản nợ mới</span>
            </button>
          </div>

          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>MSSV</th>
                  <th>Họ và tên</th>
                  <th>Khoa / Ngành</th>
                  <th>Học kỳ</th>
                  <th>Số tiền học phí</th>
                  <th>Hạn nộp</th>
                  <th>Trạng thái</th>
                  <th>Thao tác Test</th>
                </tr>
              </thead>
              <tbody>
                {tuitions.map((t) => (
                  <tr key={t.id}>
                    <td>
                      <span className="badge-mssv">{t.mssv}</span>
                    </td>
                    <td className="font-bold">{t.fullName}</td>
                    <td className="text-muted">{t.faculty}</td>
                    <td>{t.semester}</td>
                    <td className="font-bold amount-highlight">
                      {formatVND(t.amount)}
                    </td>
                    <td className="text-muted">{t.dueDate}</td>
                    <td>
                      {t.status === "PAID" ? (
                        <span className="status-badge badge-success">
                          <CheckCircle2 size={13} /> Đã thanh toán
                        </span>
                      ) : (
                        <span className="status-badge badge-warning">
                          <AlertTriangle size={13} /> Chưa thanh toán
                        </span>
                      )}
                    </td>
                    <td>
                      <div style={{ display: "flex", gap: "0.4rem", flexWrap: "wrap" }}>
                        <button
                          className="btn btn-sm btn-outline"
                          onClick={() => handleOpenEditTuition(t)}
                          title="Chỉnh sửa chi tiết học phí"
                        >
                          <Edit3 size={13} />
                          <span>Sửa HP</span>
                        </button>
                        {t.status === "PAID" ? (
                          <button
                            className="btn btn-sm btn-outline text-warning"
                            onClick={() => handleResetTuitionStatus(t.id, "UNPAID")}
                            title="Chuyển về Chưa thanh toán để test nộp lại"
                          >
                            <RotateCcw size={13} />
                            <span>Reset UNPAID</span>
                          </button>
                        ) : (
                          <button
                            className="btn btn-sm btn-outline text-success"
                            onClick={() => handleResetTuitionStatus(t.id, "PAID")}
                            title="Đánh dấu đã nộp để test bẫy thanh toán trùng"
                          >
                            <span>Gạch nợ</span>
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: TOÀN BỘ GIAO DỊCH HỆ THỐNG */}
      {activeTab === "transactions" && (
        <div className="card table-card">
          <div className="admin-stats-row">
            <div className="stat-card">
              <span className="stat-label">Tổng giao dịch hệ thống</span>
              <span className="stat-value">{transactions.length}</span>
            </div>
            <div className="stat-card">
              <span className="stat-label">Tổng học phí đã thu</span>
              <span className="stat-value text-success">{formatVND(totalRevenue)}</span>
            </div>
          </div>

          <div className="card-header-actions" style={{ marginTop: "1rem" }}>
            <div>
              <h3 className="card-title">Toàn bộ Nhật ký Giao dịch (Transactions)</h3>
              <p className="card-desc">Phân hệ Xử lý & Đối soát Giao dịch Thanh toán</p>
            </div>
          </div>

          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Mã giao dịch</th>
                  <th>Thời gian</th>
                  <th>Tài khoản nộp</th>
                  <th>MSSV</th>
                  <th>Sinh viên</th>
                  <th>Số tiền</th>
                  <th>Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((tx) => (
                  <tr key={tx.id}>
                    <td>
                      <span className="font-mono font-bold text-primary">{tx.id}</span>
                    </td>
                    <td className="text-muted">{tx.createdAt}</td>
                    <td className="font-bold">{tx.userName}</td>
                    <td>
                      <span className="badge-mssv">{tx.mssv}</span>
                    </td>
                    <td>{tx.studentName}</td>
                    <td className="font-bold amount-highlight">
                      {formatVND(tx.amount)}
                    </td>
                    <td>
                      <span className="status-badge badge-success">
                        <CheckCircle2 size={13} /> {tx.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* MODAL NẠP TIỀN / SỬA SỐ DƯ USER */}
      {selectedUserForTopup && (
        <div
          className="modal-backdrop"
          onClick={(e) => {
            if (e.target === e.currentTarget) setSelectedUserForTopup(null);
          }}
        >
          <div className="modal-dialog modal-sm">
            <div className="modal-header">
              <h3 className="modal-title">Điều chỉnh số dư tài khoản</h3>
            </div>
            <form onSubmit={handleUpdateBalance} className="modal-body">
              <p style={{ marginBottom: "1rem" }}>
                Chỉnh sửa số dư cho: <strong>{selectedUserForTopup.fullName}</strong> ({selectedUserForTopup.username})
              </p>
              <div className="form-group">
                <label className="form-label">Số dư mới (VNĐ):</label>
                <input
                  type="number"
                  className="form-control"
                  value={topupAmount}
                  step={500000}
                  onChange={(e) => setTopupAmount(e.target.value)}
                  autoFocus
                />
              </div>

              {/* Nút bấm nhanh các mốc số dư */}
              <div className="quick-amount-tags">
                <button
                  type="button"
                  className="amount-tag"
                  onClick={() => setTopupAmount(0)}
                >
                  0đ (Hết tiền)
                </button>
                <button
                  type="button"
                  className="amount-tag"
                  onClick={() => setTopupAmount(3000000)}
                >
                  3 triệu (Thiếu tiền)
                </button>
                <button
                  type="button"
                  className="amount-tag"
                  onClick={() => setTopupAmount(20000000)}
                >
                  20 triệu (Dư dả)
                </button>
                <button
                  type="button"
                  className="amount-tag"
                  onClick={() => setTopupAmount(50000000)}
                >
                  50 triệu
                </button>
              </div>

              <div className="modal-actions" style={{ marginTop: "1.5rem" }}>
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => setSelectedUserForTopup(null)}
                >
                  Hủy
                </button>
                <button type="submit" className="btn btn-primary">
                  Cập nhật số dư
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL TẠO KHOẢN HỌC PHÍ MỚI */}
      {showAddTuition && (
        <div
          className="modal-backdrop"
          onClick={(e) => {
            if (e.target === e.currentTarget) setShowAddTuition(false);
          }}
        >
          <div className="modal-dialog">
            <div className="modal-header">
              <h3 className="modal-title">Tạo mới khoản nợ học phí sinh viên</h3>
            </div>
            <form onSubmit={handleCreateTuition} className="modal-body">
              <div className="form-group">
                <label className="form-label">Mã số sinh viên (MSSV)</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Ví dụ: 52400139"
                  value={newTuitionForm.mssv}
                  onChange={(e) =>
                    setNewTuitionForm({ ...newTuitionForm, mssv: e.target.value })
                  }
                  required
                />
              </div>
              <div className="form-group">
                <label className="form-label">Họ và tên sinh viên</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Ví dụ: Nguyễn Hoàng Anh"
                  value={newTuitionForm.fullName}
                  onChange={(e) =>
                    setNewTuitionForm({ ...newTuitionForm, fullName: e.target.value })
                  }
                  required
                />
              </div>
              <div className="form-group">
                <label className="form-label">Khoa / Chuyên ngành</label>
                <input
                  type="text"
                  className="form-control"
                  value={newTuitionForm.faculty}
                  onChange={(e) =>
                    setNewTuitionForm({ ...newTuitionForm, faculty: e.target.value })
                  }
                />
              </div>
              <div className="form-group">
                <label className="form-label">Số tiền học phí (VNĐ)</label>
                <input
                  type="number"
                  className="form-control"
                  step={100000}
                  value={newTuitionForm.amount}
                  onChange={(e) =>
                    setNewTuitionForm({ ...newTuitionForm, amount: e.target.value })
                  }
                  required
                />
              </div>
              <div className="modal-actions" style={{ marginTop: "1.5rem" }}>
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => setShowAddTuition(false)}
                >
                  Hủy
                </button>
                <button type="submit" className="btn btn-primary">
                  Lưu khoản nợ
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL SỬA HỌC PHÍ */}
      {editingTuition && (
        <div
          className="modal-backdrop"
          onClick={(e) => {
            if (e.target === e.currentTarget) setEditingTuition(null);
          }}
        >
          <div className="modal-dialog">
            <div className="modal-header">
              <h3 className="modal-title">Chỉnh sửa Học phí Sinh viên</h3>
            </div>
            <form onSubmit={handleSaveEditTuition} className="modal-body">
              <div style={{ marginBottom: "1rem", padding: "0.75rem", background: "var(--color-bg-secondary, #f1f5f9)", borderRadius: "8px" }}>
                <div>Sinh viên: <strong>{editingTuition.fullName}</strong></div>
                <div>MSSV: <span className="badge-mssv">{editingTuition.mssv}</span></div>
                <div className="text-muted" style={{ fontSize: "0.85rem" }}>Khoa: {editingTuition.faculty}</div>
              </div>
              <div className="form-group">
                <label className="form-label">Số tiền học phí (VNĐ)</label>
                <input
                  type="number"
                  className="form-control"
                  step={100000}
                  value={editTuitionForm.amount}
                  onChange={(e) =>
                    setEditTuitionForm({ ...editTuitionForm, amount: Number(e.target.value) })
                  }
                  required
                />
              </div>
              <div className="form-group">
                <label className="form-label">Học kỳ</label>
                <input
                  type="text"
                  className="form-control"
                  value={editTuitionForm.semester}
                  onChange={(e) =>
                    setEditTuitionForm({ ...editTuitionForm, semester: e.target.value })
                  }
                  required
                />
              </div>
              <div className="form-group">
                <label className="form-label">Hạn nộp</label>
                <input
                  type="date"
                  className="form-control"
                  value={editTuitionForm.dueDate}
                  onChange={(e) =>
                    setEditTuitionForm({ ...editTuitionForm, dueDate: e.target.value })
                  }
                  required
                />
              </div>
              <div className="form-group">
                <label className="form-label">Trạng thái công nợ</label>
                <select
                  className="form-control"
                  value={editTuitionForm.status}
                  onChange={(e) =>
                    setEditTuitionForm({ ...editTuitionForm, status: e.target.value })
                  }
                >
                  <option value="UNPAID">Chưa thanh toán (UNPAID)</option>
                  <option value="PAID">Đã thanh toán (PAID)</option>
                </select>
              </div>

              <div className="modal-actions" style={{ marginTop: "1.5rem" }}>
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => setEditingTuition(null)}
                >
                  Hủy
                </button>
                <button type="submit" className="btn btn-primary">
                  Lưu thay đổi
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL TẠO USER MỚI */}
      {showAddUser && (
        <div
          className="modal-backdrop"
          onClick={(e) => {
            if (e.target === e.currentTarget) setShowAddUser(false);
          }}
        >
          <div className="modal-dialog">
            <div className="modal-header">
              <h3 className="modal-title">Tạo mới tài khoản iBanking (Đồng bộ Học phí)</h3>
            </div>
            <form onSubmit={handleCreateUser} className="modal-body">
              <div className="form-group">
                <label className="form-label">Tên đăng nhập / MSSV</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Ví dụ: 52400139"
                  value={newUserForm.username}
                  onChange={(e) =>
                    setNewUserForm({ ...newUserForm, username: e.target.value })
                  }
                  required
                />
                <small className="text-muted" style={{ display: "block", marginTop: "4px" }}>
                  Mật khẩu mặc định: <strong>{newUserForm.username ? `${newUserForm.username}@` : "mssv@"}</strong>
                </small>
              </div>
              <div className="form-group">
                <label className="form-label">Họ và tên</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Ví dụ: Nguyễn Hoàng Nam"
                  value={newUserForm.fullName}
                  onChange={(e) =>
                    setNewUserForm({ ...newUserForm, fullName: e.target.value })
                  }
                  required
                />
              </div>
              <div className="form-group">
                <label className="form-label">Email</label>
                <input
                  type="email"
                  className="form-control"
                  placeholder="sinhvien@tdtu.edu.vn"
                  value={newUserForm.email}
                  onChange={(e) =>
                    setNewUserForm({ ...newUserForm, email: e.target.value })
                  }
                  required
                />
              </div>
              <div className="form-group">
                <label className="form-label">Khoa / Chuyên ngành</label>
                <input
                  type="text"
                  className="form-control"
                  value={newUserForm.faculty}
                  onChange={(e) =>
                    setNewUserForm({ ...newUserForm, faculty: e.target.value })
                  }
                />
              </div>
              <div className="form-group">
                <label className="form-label">Số dư khởi tạo (VNĐ)</label>
                <input
                  type="number"
                  className="form-control"
                  value={newUserForm.balance}
                  onChange={(e) =>
                    setNewUserForm({ ...newUserForm, balance: Number(e.target.value) })
                  }
                  required
                />
              </div>
              <div className="form-group">
                <label className="form-label">Khoản học phí cần nộp (VNĐ)</label>
                <input
                  type="number"
                  className="form-control"
                  value={newUserForm.tuitionAmount}
                  onChange={(e) =>
                    setNewUserForm({ ...newUserForm, tuitionAmount: Number(e.target.value) })
                  }
                  required
                />
                <small className="text-muted" style={{ display: "block", marginTop: "4px" }}>
                  Tự động đồng bộ sang Phân hệ Quản lý Học phí cho sinh viên này.
                </small>
              </div>
              <div className="modal-actions" style={{ marginTop: "1.5rem" }}>
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => setShowAddUser(false)}
                >
                  Hủy
                </button>
                <button type="submit" className="btn btn-primary">
                  Tạo tài khoản & Đồng bộ
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
