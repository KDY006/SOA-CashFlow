/**
 * Server Entrypoint - Notification Service (Final Phase)
 * Khởi động HTTP Server, Socket.io WebSocket & Redis Event Consumer
 */
const http = require('node:http');
const config = require('./config');
const app = require('./app');
const redis = require('./redis');
const socket = require('./socket');
const sse = require('./sse');
const store = require('./store');

// 1. Tạo HTTP Server bọc Express app
const server = http.createServer(app);

// 2. Khởi tạo Socket.io WebSocket trên HTTP server
socket.initSocket(server);

// 3. Khởi tạo kết nối Redis Pub/Sub
redis.initRedis();

// Lắng nghe sự kiện từ Redis channel
redis.onMessage((channel, rawMessage) => {
  try {
    const data = JSON.parse(rawMessage);
    console.log(`[Redis Event] Nhận sự kiện từ channel '${channel}':`, data.title || data.type || 'Event');

    const notif = store.addNotification({
      userId: data.userId || data.user_id || 1,
      title: data.title || 'Thông báo mới từ hệ thống',
      message: data.message || '',
      type: data.type || 'SYSTEM',
      metadata: data.metadata || {},
    });

    // Phát sóng realtime đồng thời qua cả SSE và Socket.io
    const sseCount = sse.broadcastToUser(notif.userId, notif);
    const socketCount = socket.emitToUser(notif.userId, 'notification:new', notif);

    console.log(`[Realtime Broadcast] Đã phát tới User ${notif.userId} (SSE: ${sseCount}, Socket: ${socketCount})`);
  } catch (err) {
    console.error(`[Redis Event Parse Error] ${err.message}`);
  }
});

// 4. Lắng nghe trên cổng chỉ định
server.listen(config.port, config.host, () => {
  console.log('='.repeat(70));
  console.log(`🔔 [Notification Service Final - Express & Socket.io]`);
  console.log(`🚀 Máy chủ HTTP đang lắng nghe: http://${config.host}:${config.port}`);
  console.log(`📡 WebSocket Socket.io Endpoint: ws://${config.host}:${config.port}/socket.io/`);
  console.log(`🌊 Server-Sent Events Endpoint : http://${config.host}:${config.port}/api/notifications/stream`);
  console.log(`🔐 OTP Verification Endpoints : /api/notifications/otp/generate & /verify`);
  console.log(`🔄 Redis Message Broker       : ${config.redis.host}:${config.redis.port}`);
  console.log('='.repeat(70));
});

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('Đang dừng Notification Service (SIGTERM)...');
  server.close(() => {
    console.log('Máy chủ Notification Service đã dừng an toàn.');
    process.exit(0);
  });
});

process.on('SIGINT', () => {
  server.close(() => process.exit(0));
});
