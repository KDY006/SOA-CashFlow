/**
 * Cấu hình API Gateway (Phiên bản Cuối kỳ - Express.js Framework)
 * SOA CashFlow Project - Final Phase
 */
require('dotenv').config();

const isDocker = Boolean(
  process.env.APP_ENV === 'production' ||
  process.env.APP_ENV === 'final' ||
  process.env.IS_DOCKER
);

module.exports = {
  env: process.env.APP_ENV || process.env.NODE_ENV || 'production',
  port: parseInt(process.env.GATEWAY_PORT || process.env.PORT || '8080', 10),
  host: process.env.GATEWAY_HOST || '0.0.0.0',

  jwtSecret:
    process.env.JWT_SECRET ||
    process.env.AUTH_JWT_SECRET ||
    'cashflow-final-production-jwt-secret-key-2026-tdtu',

  // Cho phép bật/tắt chế độ kiểm tra JWT nghiêm ngặt
  authStrict: process.env.GATEWAY_AUTH_STRICT !== 'false',

  // Rate Limiting (Sliding window qua express-rate-limit)
  rateLimit: {
    windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS || '60000', 10),
    max: parseInt(process.env.RATE_LIMIT_MAX || '600', 10),
  },

  // Địa chỉ 6 Microservices nội bộ
  services: {
    auth:
      process.env.AUTH_SERVICE_URL ||
      (isDocker ? 'http://auth-service:8081' : 'http://127.0.0.1:8081'),
    transaction:
      process.env.TRANSACTION_SERVICE_URL ||
      (isDocker ? 'http://transaction-service:8082' : 'http://127.0.0.1:8082'),
    asset:
      process.env.ASSET_SERVICE_URL ||
      (isDocker ? 'http://asset-service:8083' : 'http://127.0.0.1:8083'),
    analytics:
      process.env.ANALYTICS_SERVICE_URL ||
      (isDocker ? 'http://analytics-service:8084' : 'http://127.0.0.1:8084'),
    integration:
      process.env.INTEGRATION_SERVICE_URL ||
      (isDocker ? 'http://integration-service:8085' : 'http://127.0.0.1:8085'),
    notification:
      process.env.NOTIFICATION_SERVICE_URL ||
      (isDocker ? 'http://notification-service:8086' : 'http://127.0.0.1:8086'),
  },

  // Bảng ánh xạ prefix -> microservice
  serviceRoutes: [
    { service: 'auth', prefixes: ['/api/auth', '/api/profile', '/api/admin'], healthPath: '/actuator/health' },
    { service: 'transaction', prefixes: ['/api/transactions', '/api/budgets', '/api/categories', '/api/balance'], healthPath: '/api/health' },
    { service: 'asset', prefixes: ['/api/assets', '/api/loans', '/api/investments'], healthPath: '/health' },
    { service: 'analytics', prefixes: ['/api/analytics'], healthPath: '/health' },
    { service: 'integration', prefixes: ['/api/integrations'], healthPath: '/health' },
    { service: 'notification', prefixes: ['/api/notifications', '/api/otp', '/socket.io'], ws: true, healthPath: '/health' },
  ],

  // Các endpoint công khai không yêu cầu JWT
  publicEndpoints: [
    { method: 'POST', path: '/api/auth/register' },
    { method: 'POST', path: '/api/auth/login' },
    { method: 'POST', path: '/api/auth/refresh' },
    { method: 'GET', path: '/api/integrations/rates' },
    { method: 'POST', path: '/api/integrations/webhooks/banking' },
    { method: 'GET', path: '/health' },
    { method: 'GET', path: '/health/services' },
    { method: 'GET', path: '/' },
    { method: 'GET', path: '/docs' },
    { method: 'GET', path: '/swagger' },
    { method: 'GET', path: '/openapi.json' },
    { method: 'GET', path: '/openapi' },
    { method: 'GET', path: '/socket.io' },
    { method: 'POST', path: '/socket.io' },
  ],
};
