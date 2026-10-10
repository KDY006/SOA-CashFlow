/**
 * Quản lý Lưu trữ Thông báo (In-Memory Store with Seed Data)
 * Hỗ trợ lưu trữ, truy vấn lịch sử và thống kê trạng thái đọc
 */

let nextId = 1;
const notifications = [];

// Seed dữ liệu mẫu ban đầu để demo và kiểm thử ngay lập tức
function seedInitialData() {
  const seeds = [
    {
      userId: 1,
      type: 'BUDGET_ALERT',
      title: 'Cảnh báo Ngân sách tháng 10/2026',
      message: "Giao dịch gần nhất đã làm ngân sách danh mục 'Ăn uống & Cà phê' vượt hạn mức (Đã chi 5,450,000 / 5,000,000 VND).",
      metadata: { categoryId: 3, limit: 5000000, spent: 5450000, overAmount: 450000 },
      isRead: false,
      createdAt: new Date(Date.now() - 3600 * 1000 * 2).toISOString(), // 2 giờ trước
    },
    {
      userId: 1,
      type: 'CASHFLOW_ANOMALY',
      title: 'Phát hiện Chi tiêu Bất thường (Analytics)',
      message: 'Khoản chi 3,500,000 VND cho Tiền nhà & Tiện ích vượt xa mức trung vị chi tiêu thông thường.',
      metadata: { amount: 3500000, category: 'Tiền nhà & Tiện ích', median: 450000 },
      isRead: false,
      createdAt: new Date(Date.now() - 3600 * 1000 * 5).toISOString(), // 5 giờ trước
    },
    {
      userId: 1,
      type: 'LOAN_REMINDER',
      title: 'Nhắc nhở Kỳ hạn Khoản vay (Asset Service)',
      message: 'Khoản vay mua tài sản của bạn sắp đến hạn thanh toán định kỳ vào ngày 15/10/2026.',
      metadata: { amountDue: 2500000, dueDate: '2026-10-15' },
      isRead: true,
      createdAt: new Date(Date.now() - 3600 * 1000 * 24).toISOString(), // 1 ngày trước
    },
    {
      userId: 2,
      type: 'SYSTEM',
      title: 'Chào mừng bạn đến với SOA CashFlow',
      message: 'Tài khoản của bạn đã được khởi tạo và liên kết thành công với hệ thống quản trị dòng tiền.',
      metadata: {},
      isRead: true,
      createdAt: new Date(Date.now() - 3600 * 1000 * 48).toISOString(),
    },
  ];

  for (const item of seeds) {
    notifications.push({
      id: nextId++,
      ...item,
    });
  }
}

seedInitialData();

/**
 * Thêm mới một thông báo
 */
function addNotification({ userId, title, message, type = 'SYSTEM', metadata = {} }) {
  const item = {
    id: nextId++,
    userId: Number(userId),
    type: String(type).toUpperCase(),
    title: String(title),
    message: String(message),
    metadata: metadata || {},
    isRead: false,
    createdAt: new Date().toISOString(),
  };

  notifications.unshift(item); // đưa lên đầu danh sách
  return item;
}

/**
 * Lấy danh sách thông báo theo userId
 */
function getByUser(userId, { limit = 50, offset = 0, unreadOnly = false } = {}) {
  const uid = Number(userId);
  let filtered = notifications.filter((n) => n.userId === uid);

  if (unreadOnly) {
    filtered = filtered.filter((n) => !n.isRead);
  }

  const total = filtered.length;
  const items = filtered.slice(offset, offset + limit);

  return {
    items,
    total,
    unreadCount: notifications.filter((n) => n.userId === uid && !n.isRead).length,
  };
}

/**
 * Đánh dấu một thông báo là đã đọc
 */
function markAsRead(id, userId) {
  const notifId = Number(id);
  const uid = Number(userId);
  const found = notifications.find((n) => n.id === notifId && n.userId === uid);

  if (found) {
    found.isRead = true;
    found.readAt = new Date().toISOString();
    return found;
  }
  return null;
}

/**
 * Đánh dấu tất cả thông báo của user là đã đọc
 */
function markAllAsRead(userId) {
  const uid = Number(userId);
  let updatedCount = 0;
  for (const n of notifications) {
    if (n.userId === uid && !n.isRead) {
      n.isRead = true;
      n.readAt = new Date().toISOString();
      updatedCount++;
    }
  }
  return updatedCount;
}

module.exports = {
  addNotification,
  getByUser,
  markAsRead,
  markAllAsRead,
};
