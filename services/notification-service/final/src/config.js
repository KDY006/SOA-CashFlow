/**
 * Configuration Module - Notification Service (Final Phase)
 * SOA CashFlow Project - Express / Socket.io / Redis Architecture
 */
require('dotenv').config();

module.exports = {
  env: process.env.NODE_ENV || 'development',
  port: parseInt(process.env.NOTIFICATION_SERVICE_PORT || process.env.PORT || '8086', 10),
  host: process.env.HOST || '0.0.0.0',

  // Redis Configuration
  redis: {
    host: process.env.REDIS_HOST || 'redis',
    port: parseInt(process.env.REDIS_PORT || '6379', 10),
    channel: process.env.REDIS_CHANNEL || 'cashflow:notifications',
    eventsChannel: process.env.REDIS_EVENTS_CHANNEL || 'cashflow:events',
    password: process.env.REDIS_PASSWORD || undefined,
  },

  // CORS Settings
  cors: {
    origin: '*',
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization', 'X-User-Id', 'X-User-Role'],
  },

  // Supported Notification Types
  notificationTypes: [
    'BUDGET_ALERT',        // Cảnh báo ngân sách
    'CASHFLOW_ANOMALY',    // Chi tiêu bất thường
    'LOAN_REMINDER',       // Nhắc nợ / kỳ hạn
    'LARGE_TRANSACTION',   // Biến động số dư lớn
    'OTP_VERIFICATION',    // Mã xác thực OTP giao dịch thanh toán
    'SYSTEM',              // Thông báo hệ thống
  ],

  // OTP Configuration
  otp: {
    ttlSeconds: parseInt(process.env.OTP_TTL_SECONDS || '300', 10), // 5 phút
    length: 6,
    maxAttempts: 3,
  },
};
