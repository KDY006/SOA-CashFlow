/**
 * Entrypoint - API Gateway (Final Phase - Express.js)
 * SOA CashFlow Project
 */
const http = require('node:http');
const config = require('./config');
const { app, wsProxies } = require('./app');

const server = http.createServer(app);

// Chuyển tiếp nâng cấp WebSocket (Socket.io -> Notification Service)
for (const proxy of wsProxies) {
  if (typeof proxy.upgrade === 'function') {
    server.on('upgrade', proxy.upgrade);
  }
}

server.listen(config.port, config.host, () => {
  console.log('='.repeat(70));
  console.log(`🚀 [API Gateway Final - Express.js] Đang lắng nghe: http://${config.host}:${config.port}`);
  console.log(`🔒 JWT Guard: ${config.authStrict ? 'STRICT MODE' : 'PERMISSIVE'} | Rate Limit: ${config.rateLimit.max} req/${config.rateLimit.windowMs / 1000}s`);
  console.log(`📚 Swagger UI: http://${config.host}:${config.port}/docs`);
  console.log('📋 Danh sách 6 Microservices kết nối:');
  for (const [name, url] of Object.entries(config.services)) {
    console.log(`   - ${name.padEnd(14)} : ${url}`);
  }
  console.log('='.repeat(70));
});

process.on('SIGTERM', () => {
  console.log('Đang dừng API Gateway Final...');
  server.close(() => process.exit(0));
});

process.on('SIGINT', () => {
  server.close(() => process.exit(0));
});
