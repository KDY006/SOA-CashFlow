/**
 * Express Application Setup
 * Middleware, CORS, Routes & Error Handling
 */
const express = require('express');
const cors = require('cors');
const config = require('./config');
const redis = require('./redis');
const sse = require('./sse');
const socket = require('./socket');

const notificationRoutes = require('./routes/notificationRoutes');
const otpRoutes = require('./routes/otpRoutes');

const app = express();

// Middlewares
app.use(cors(config.cors));
app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true }));

// Request logging middleware
app.use((req, res, next) => {
  if (req.path !== '/health') {
    const timestamp = new Date().toISOString();
    console.log(`[${timestamp}] ${req.method} ${req.originalUrl}`);
  }
  next();
});

// Health check endpoint
app.get(['/health', '/'], (req, res) => {
  res.json({
    service: 'cashflow-notification-service',
    status: 'UP',
    version: '2.0.0-final',
    framework: 'Node.js Express + Socket.io + Redis Pub/Sub',
    environment: config.env,
    timestamp: new Date().toISOString(),
    uptime: Math.floor(process.uptime()),
    redis: redis.getStatus(),
    clients: {
      sseClients: sse.getClientCount(),
      socketClients: socket.getSocketClientCount(),
    },
    features: [
      'Realtime WebSocket (Socket.io)',
      'Server-Sent Events (SSE Stream)',
      'Redis Pub/Sub Event Consumer',
      'Transaction Payment OTP Verification (5-min TTL, Single-use)',
      'In-Memory Notification History & Unread Tracking',
    ],
    endpoints: [
      'GET  /api/notifications (Danh sách thông báo)',
      'GET  /api/notifications/stream (Kênh realtime SSE)',
      'POST /api/notifications/send (Gửi thông báo mới)',
      'PUT  /api/notifications/:id/read (Đánh dấu đã đọc)',
      'PUT  /api/notifications/read-all (Đánh dấu đọc tất cả)',
      'POST /api/notifications/otp/generate (Sinh mã OTP giao dịch)',
      'POST /api/notifications/otp/verify (Xác thực mã OTP)',
    ],
  });
});

// API Routes
app.use('/api', notificationRoutes);
app.use('/api', otpRoutes);

// Fallback 404
app.use((req, res) => {
  res.status(404).json({
    success: false,
    error: 'ROUTE_NOT_FOUND',
    message: `Đường dẫn ${req.method} ${req.originalUrl} không tồn tại trên Notification Service.`,
  });
});

// Global Error Handler
app.use((err, req, res, next) => {
  console.error(`[Unhandled Error] ${err.stack || err.message}`);
  res.status(500).json({
    success: false,
    error: 'INTERNAL_SERVER_ERROR',
    message: err.message || 'Lỗi hệ thống nội bộ',
  });
});

module.exports = app;
