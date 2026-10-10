/**
 * Cấu hình Notification Service (Native Node.js)
 * SOA CashFlow Project - Midterm
 */

module.exports = {
  port: parseInt(process.env.NOTIFICATION_SERVICE_PORT || process.env.PORT || '8086', 10),
  host: process.env.HOST || '0.0.0.0',
  
  // Cấu hình Redis Pub/Sub
  redis: {
    host: process.env.REDIS_HOST || 'redis',
    port: parseInt(process.env.REDIS_PORT || '6379', 10),
    channel: process.env.REDIS_CHANNEL || 'cashflow:notifications',
  },

  // Loại thông báo hỗ trợ
  notificationTypes: [
    'BUDGET_ALERT',       // Vượt hoặc cảnh báo chạm ngưỡng ngân sách
    'CASHFLOW_ANOMALY',   // Khoản chi tiêu đột biến bất thường (phát hiện từ Analytics)
    'LOAN_REMINDER',      // Nhắc nhở kỳ hạn thanh toán nợ / lãi vay
    'SYSTEM',             // Thông báo hệ thống chung
  ],
};
