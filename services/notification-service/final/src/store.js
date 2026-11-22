/**
 * In-Memory Store with Seed Data & Persistence Support
 * Lưu trữ lịch sử thông báo, đánh dấu đọc, phân trang
 */

let nextId = 1;
const notifications = [];

function seedInitialData() {
  const seeds = [
    {
      userId: 1,
      type: 'BUDGET_ALERT',
      title: 'Cảnh báo Ngân sách tháng 11/2026',
      message: "Giao dịch gần nhất đã làm ngân sách danh mục 'Ăn uống & Cà phê' vượt hạn mức (Đã chi 5,450,000 / 5,000,000 VND).",
      metadata: { categoryId: 3, limit: 5000000, spent: 5450000, overAmount: 450000 },
      isRead: false,
      createdAt: new Date(Date.now() - 3600 * 1000 * 2).toISOString(),
    },
    {
      userId: 1,
      type: 'CASHFLOW_ANOMALY',
      title: 'Phát hiện Chi tiêu Bất thường (Analytics)',
      message: 'Khoản chi 3,500,000 VND cho Tiền nhà & Tiện ích vượt xa mức trung vị chi tiêu thông thường.',
      metadata: { amount: 3500000, category: 'Tiền nhà & Tiện ích', median: 450000 },
      isRead: false,
      createdAt: new Date(Date.now() - 3600 * 1000 * 5).toISOString(),
    },
    {
      userId: 1,
      type: 'LOAN_REMINDER',
      title: 'Nhắc nhở Kỳ hạn Khoản vay (Asset Service)',
      message: 'Khoản vay mua tài sản của bạn sắp đến hạn thanh toán định kỳ vào ngày 25/11/2026.',
      metadata: { amountDue: 2500000, dueDate: '2026-11-25' },
      isRead: true,
      createdAt: new Date(Date.now() - 3600 * 1000 * 24).toISOString(),
    },
    {
      userId: 1,
      type: 'LARGE_TRANSACTION',
      title: 'Biến động số dư lớn (Banking Integration)',
      message: 'Tài khoản VCB + 20.000.000 VND từ CÔNG TY TNHH ABC. Số dư: 45.250.000 VND.',
      metadata: { bankTxId: 102, bankCode: 'VCB', amount: 20000000 },
      isRead: false,
      createdAt: new Date(Date.now() - 3600 * 1000 * 1).toISOString(),
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

  notifications.unshift(item); // Đưa lên đầu danh sách
  return item;
}

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

function getById(id, userId = null) {
  const targetId = Number(id);
  const item = notifications.find((n) => n.id === targetId);
  if (!item) return null;
  if (userId !== null && item.userId !== Number(userId)) return null;
  return item;
}

function markAsRead(id, userId = null) {
  const targetId = Number(id);
  const item = notifications.find((n) => n.id === targetId);
  if (!item) return null;
  if (userId !== null && item.userId !== Number(userId)) return null;

  item.isRead = true;
  item.updatedAt = new Date().toISOString();
  return item;
}

function markAllAsRead(userId) {
  const uid = Number(userId);
  let updatedCount = 0;
  notifications.forEach((n) => {
    if (n.userId === uid && !n.isRead) {
      n.isRead = true;
      n.updatedAt = new Date().toISOString();
      updatedCount++;
    }
  });
  return updatedCount;
}

function deleteNotification(id, userId = null) {
  const targetId = Number(id);
  const index = notifications.findIndex((n) => n.id === targetId);
  if (index === -1) return false;
  if (userId !== null && notifications[index].userId !== Number(userId)) return false;

  notifications.splice(index, 1);
  return true;
}

function getUnreadCount(userId) {
  const uid = Number(userId);
  return notifications.filter((n) => n.userId === uid && !n.isRead).length;
}

module.exports = {
  addNotification,
  getByUser,
  getById,
  markAsRead,
  markAllAsRead,
  deleteNotification,
  getUnreadCount,
};
