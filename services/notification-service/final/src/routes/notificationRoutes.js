/**
 * Notification Express Router
 */
const express = require('express');
const router = express.Router();
const controller = require('../controllers/notificationController');

// Realtime SSE stream
router.get('/notifications/stream', controller.streamSse);

// Danh sách thông báo
router.get('/notifications', controller.getNotifications);
router.get('/notifications/user/:userId', controller.getNotifications);

// Số lượng thông báo chưa đọc
router.get('/notifications/unread-count', controller.getUnreadCount);

// Chi tiết 1 thông báo
router.get('/notifications/:id', controller.getNotificationById);

// Gửi thông báo mới (hỗ trợ cả /send và /notifications)
router.post('/notifications/send', controller.createNotification);
router.post('/notifications', controller.createNotification);

// Đánh dấu đã đọc
router.put('/notifications/read-all', controller.markAllAsRead);
router.put('/notifications/:id/read', controller.markAsRead);

// Xóa thông báo
router.delete('/notifications/:id', controller.deleteNotification);

module.exports = router;
