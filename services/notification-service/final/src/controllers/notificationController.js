/**
 * Notification Controller
 * Quản lý các endpoints CRUD thông báo
 */
const store = require('../store');
const sse = require('../sse');
const socket = require('../socket');
const redis = require('../redis');

function resolveUserId(req) {
  const headerId = req.headers['x-user-id'];
  const isAdmin = String(req.headers['x-user-role'] || '').toUpperCase() === 'ADMIN';

  if (headerId && Number(headerId) > 0) {
    if (isAdmin && (req.query.user_id || req.query.userId || req.body?.userId)) {
      return Number(req.query.user_id || req.query.userId || req.body?.userId);
    }
    return Number(headerId);
  }

  const requested = req.params.userId || req.query.user_id || req.query.userId || req.body?.userId || req.body?.user_id;
  if (requested && Number(requested) > 0) return Number(requested);

  return 1; // Default demo user
}

exports.getNotifications = (req, res) => {
  const userId = resolveUserId(req);
  const limit = parseInt(req.query.limit || '50', 10);
  const offset = parseInt(req.query.offset || '0', 10);
  const unreadOnly = req.query.unread_only === 'true' || req.query.unread === '1';

  const data = store.getByUser(userId, { limit, offset, unreadOnly });
  return res.json({
    success: true,
    userId,
    ...data,
  });
};

exports.getUnreadCount = (req, res) => {
  const userId = resolveUserId(req);
  const unreadCount = store.getUnreadCount(userId);
  return res.json({
    success: true,
    userId,
    unreadCount,
  });
};

exports.getNotificationById = (req, res) => {
  const userId = resolveUserId(req);
  const notif = store.getById(req.params.id, userId);

  if (!notif) {
    return res.status(404).json({
      success: false,
      message: `Không tìm thấy thông báo #${req.params.id}`,
    });
  }

  return res.json({
    success: true,
    data: notif,
  });
};

exports.createNotification = (req, res) => {
  const userId = resolveUserId(req);
  const { title, message, type = 'SYSTEM', metadata = {} } = req.body;

  if (!title || !message) {
    return res.status(400).json({
      success: false,
      error: 'VALIDATION_ERROR',
      message: 'Tiêu đề (title) và nội dung (message) là bắt buộc',
    });
  }

  // 1. Lưu trữ
  const notification = store.addNotification({ userId, title, message, type, metadata });

  // 2. Phát qua SSE
  const sseCount = sse.broadcastToUser(userId, notification);

  // 3. Phát qua Socket.io
  const socketCount = socket.emitToUser(userId, 'notification:new', notification);

  // 4. Publish lên Redis
  redis.publish(null, notification);

  return res.status(201).json({
    success: true,
    message: 'Tạo và phát sóng thông báo thành công',
    data: notification,
    realtimeDelivered: (sseCount + socketCount) > 0,
    activeClients: { sse: sseCount, socket: socketCount },
  });
};

exports.markAsRead = (req, res) => {
  const userId = resolveUserId(req);
  const updated = store.markAsRead(req.params.id, userId);

  if (!updated) {
    return res.status(404).json({
      success: false,
      message: `Không tìm thấy thông báo ID ${req.params.id} của người dùng`,
    });
  }

  // Báo qua Socket.io
  socket.emitToUser(userId, 'notification:read', { id: updated.id });

  return res.json({
    success: true,
    message: 'Đã đánh dấu thông báo là đã đọc',
    data: updated,
  });
};

exports.markAllAsRead = (req, res) => {
  const userId = resolveUserId(req);
  const count = store.markAllAsRead(userId);

  socket.emitToUser(userId, 'notification:read_all', { updatedCount: count });

  return res.json({
    success: true,
    message: `Đã đánh dấu ${count} thông báo là đã đọc`,
    updatedCount: count,
  });
};

exports.deleteNotification = (req, res) => {
  const userId = resolveUserId(req);
  const deleted = store.deleteNotification(req.params.id, userId);

  if (!deleted) {
    return res.status(404).json({
      success: false,
      message: `Không tìm thấy thông báo #${req.params.id} để xóa`,
    });
  }

  return res.json({
    success: true,
    message: 'Xóa thông báo thành công',
  });
};

exports.streamSse = (req, res) => {
  const userId = resolveUserId(req);
  return sse.registerClient(userId, req, res);
};
