/**
 * Cấu hình hệ thống API Gateway (Native Node.js)
 * SOA CashFlow Project - Midterm
 */

const isDocker = Boolean(process.env.APP_ENV === 'midterm' || process.env.IS_DOCKER);

module.exports = {
  port: parseInt(process.env.GATEWAY_PORT || process.env.PORT || '8080', 10),
  host: process.env.GATEWAY_HOST || '0.0.0.0',
  jwtSecret: process.env.JWT_SECRET || process.env.AUTH_JWT_SECRET || 'cashflow-auth-midterm-development-secret-key',
  
  // Cho phép bỏ qua kiểm tra JWT ở môi trường dev nếu bật GATEWAY_AUTH_STRICT=false
  authStrict: process.env.GATEWAY_AUTH_STRICT !== 'false',

  // Danh sách địa chỉ 6 Microservices nội bộ
  services: {
    auth: process.env.AUTH_SERVICE_URL || (isDocker ? 'http://auth-service:8081' : 'http://localhost:8081'),
    transaction: process.env.TRANSACTION_SERVICE_URL || (isDocker ? 'http://transaction-service:8082' : 'http://localhost:8082'),
    asset: process.env.ASSET_SERVICE_URL || (isDocker ? 'http://asset-service:8083' : 'http://localhost:8083'),
    analytics: process.env.ANALYTICS_SERVICE_URL || (isDocker ? 'http://analytics-service:8084' : 'http://localhost:8084'),
    integration: process.env.INTEGRATION_SERVICE_URL || (isDocker ? 'http://integration-service:8085' : 'http://localhost:8085'),
    notification: process.env.NOTIFICATION_SERVICE_URL || (isDocker ? 'http://notification-service:8086' : 'http://localhost:8086'),
  },

  // Bảng ánh xạ route prefix tới microservice tương ứng
  routeMap: [
    { prefix: '/api/auth', service: 'auth' },
    { prefix: '/api/profile', service: 'auth' },
    { prefix: '/api/transactions', service: 'transaction' },
    { prefix: '/api/budgets', service: 'transaction' },
    { prefix: '/api/categories', service: 'transaction' },
    { prefix: '/api/assets', service: 'asset' },
    { prefix: '/api/loans', service: 'asset' },
    { prefix: '/api/investments', service: 'asset' },
    { prefix: '/api/analytics', service: 'analytics' },
    { prefix: '/api/integrations', service: 'integration' },
    { prefix: '/api/notifications', service: 'notification' },
  ],

  // Các endpoint công khai không bắt buộc phải có Authorization header
  publicEndpoints: [
    { method: 'POST', path: '/api/auth/register' },
    { method: 'POST', path: '/api/auth/login' },
    { method: 'POST', path: '/api/auth/refresh' },
    { method: 'GET', path: '/api/integrations/rates' },
    { method: 'GET', path: '/health' },
    { method: 'GET', path: '/' },
  ],
};
