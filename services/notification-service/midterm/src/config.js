module.exports = {
  port: parseInt(process.env.NOTIFICATION_SERVICE_PORT || process.env.PORT || '8086', 10),
  host: process.env.HOST || '0.0.0.0',
  redis: {
    host: process.env.REDIS_HOST || 'redis',
    port: parseInt(process.env.REDIS_PORT || '6379', 10),
    channel: process.env.REDIS_CHANNEL || 'cashflow:notifications',
  },
  notificationTypes: [
    'BUDGET_ALERT',
    'CASHFLOW_ANOMALY',
    'LOAN_REMINDER',
    'LARGE_TRANSACTION',
    'SYSTEM',
  ],
};
