/**
 * SOA CashFlow - Notification Service (Native Node.js / Zero-dependency)
 * Phụ trách: Nhóm trưởng - Phiên bản Giữa kỳ (Midterm)
 */

const http = require('node:http');
const { parse } = require('node:url');
const config = require('./config');
const store = require('./store');
const sse = require('./sse');
const redisClient = require('./redisClient');

function sendJson(res, statusCode, data) {
  const payload = JSON.stringify(data, null, 2);
  res.writeHead(statusCode, {
    'Content-Type': 'application/json; charset=utf-8',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-User-Id',
    'Content-Length': Buffer.byteLength(payload),
  });
  res.end(payload);
}

function parseJsonBody(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.on('data', (chunk) => {
      body += chunk;
      if (body.length > 1_000_000) {
        req.destroy();
        reject(new Error('Kích thước Payload vượt quá 1MB'));
      }
    });
    req.on('end', () => {
      if (!body.trim()) return resolve({});
      try {
        resolve(JSON.parse(body));
      } catch (err) {
        reject(new Error('Định dạng JSON gửi lên không hợp lệ'));
      }
    });
    req.on('error', reject);
  });
}

function resolveUserId(req, query, paramId) {
  if (paramId && Number(paramId) > 0) return Number(paramId);
  if (query.user_id && Number(query.user_id) > 0) return Number(query.user_id);
  const headerUserId = req.headers['x-user-id'];
  if (headerUserId && Number(headerUserId) > 0) return Number(headerUserId);
  return 1; // Mặc định user 1 cho demo
}

// Khởi chạy lắng nghe Redis Pub/Sub
redisClient.initRedis();
redisClient.onNotification((rawMessage) => {
  try {
    const data = JSON.parse(rawMessage);
    const notif = store.addNotification({
      userId: data.userId || data.user_id || 1,
      title: data.title || 'Thông báo mới',
      message: data.message || '',
      type: data.type || 'SYSTEM',
      metadata: data.metadata || {},
    });
    const deliveredCount = sse.broadcastToUser(notif.userId, notif);
    console.log(`[Redis Event] Đã tiếp nhận thông báo cho User ${notif.userId} -> Đẩy realtime tới ${deliveredCount} client(s)`);
  } catch (err) {
    console.error(`[Redis Event Error] Không thể xử lý message: ${err.message}`);
  }
});

// Khởi tạo HTTP Server thuần
const server = http.createServer(async (req, res) => {
  // 1. CORS Preflight
  if (req.method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-User-Id',
    });
    return res.end();
  }

  const parsedUrl = parse(req.url, true);
  const pathname = (parsedUrl.pathname || '/').replace(/\/+$/, '') || '/';
  const method = req.method.toUpperCase();
  const query = parsedUrl.query;

  try {
    // 2. Health Check
    if (pathname === '/health' || pathname === '/') {
      return sendJson(res, 200, {
        service: 'notification-service',
        status: 'UP',
        technology: 'Native Node.js (Zero-dependency)',
        version: '1.0.0-midterm',
        time: new Date().toISOString(),
        redis: redisClient.getStatus(),
        activeSseClients: sse.getClientCount(),
        endpoints: [
          'GET  /api/notifications/stream?user_id=1 (Realtime SSE Stream)',
          'GET  /api/notifications/user/:userId (Lịch sử thông báo)',
          'POST /api/notifications/send (Gửi thông báo mới)',
          'PUT  /api/notifications/:id/read (Đánh dấu đã đọc)',
        ],
      });
    }

    // 3. Kênh Realtime Server-Sent Events (SSE)
    if (pathname === '/api/notifications/stream' && method === 'GET') {
      const userId = resolveUserId(req, query);
      return sse.registerClient(userId, req, res);
    }

    // 4. Lấy danh sách thông báo của người dùng
    // Khớp GET /api/notifications hoặc GET /api/notifications/user/:userId
    const userNotifMatch = pathname.match(/^\/api\/notifications\/user\/(\d+)$/);
    if ((pathname === '/api/notifications' || userNotifMatch) && method === 'GET') {
      const targetUserId = resolveUserId(req, query, userNotifMatch ? userNotifMatch[1] : null);
      const limit = parseInt(query.limit || '50', 10);
      const offset = parseInt(query.offset || '0', 10);
      const unreadOnly = query.unread_only === 'true';

      const data = store.getByUser(targetUserId, { limit, offset, unreadOnly });
      return sendJson(res, 200, {
        success: true,
        userId: targetUserId,
        ...data,
      });
    }

    // 5. Gửi thông báo mới (POST /api/notifications/send hoặc POST /api/notifications)
    if ((pathname === '/api/notifications/send' || pathname === '/api/notifications') && method === 'POST') {
      const body = await parseJsonBody(req);
      const userId = body.userId || body.user_id || resolveUserId(req, query);
      const { title, message, type = 'SYSTEM', metadata = {} } = body;

      if (!title || !message) {
        return sendJson(res, 400, {
          success: false,
          error: 'VALIDATION_ERROR',
          message: 'Tiêu đề (title) và nội dung (message) là bắt buộc',
        });
      }

      // Lưu trữ vào Store
      const notification = store.addNotification({ userId, title, message, type, metadata });

      // Phát realtime qua SSE
      const deliveredCount = sse.broadcastToUser(userId, notification);

      // Phát qua Redis Pub/Sub cho các instance khác
      redisClient.publishNotification(notification);

      return sendJson(res, 201, {
        success: true,
        message: 'Tạo và phát thông báo thành công',
        data: notification,
        realtimeDelivered: deliveredCount > 0,
        activeClients: deliveredCount,
      });
    }

    // 6. Đánh dấu đã đọc một thông báo (PUT /api/notifications/:id/read)
    const readMatch = pathname.match(/^\/api\/notifications\/(\d+)\/read$/);
    if (readMatch && method === 'PUT') {
      const notifId = readMatch[1];
      const userId = resolveUserId(req, query);
      const updated = store.markAsRead(notifId, userId);

      if (!updated) {
        return sendJson(res, 404, {
          success: false,
          message: `Không tìm thấy thông báo ID ${notifId} của người dùng ${userId}`,
        });
      }

      return sendJson(res, 200, {
        success: true,
        message: 'Đã đánh dấu thông báo là đã đọc',
        data: updated,
      });
    }

    // 7. Đánh dấu đã đọc tất cả (PUT /api/notifications/read-all)
    if (pathname === '/api/notifications/read-all' && method === 'PUT') {
      const userId = resolveUserId(req, query);
      const count = store.markAllAsRead(userId);
      return sendJson(res, 200, {
        success: true,
        message: `Đã đánh dấu ${count} thông báo là đã đọc`,
        updatedCount: count,
      });
    }

    // Route không khớp
    return sendJson(res, 404, {
      success: false,
      error: 'NOT_FOUND',
      message: `Đường dẫn ${method} ${pathname} không tồn tại trên Notification Service`,
    });
  } catch (err) {
    console.error(`[Server Error] ${err.message}`);
    return sendJson(res, 500, {
      success: false,
      error: 'INTERNAL_SERVER_ERROR',
      message: err.message,
    });
  }
});

server.listen(config.port, config.host, () => {
  console.log('='.repeat(65));
  console.log(`🔔 [Notification Service Native Node.js] Đang lắng nghe: http://${config.host}:${config.port}`);
  console.log(`📡 Kênh Realtime SSE: http://${config.host}:${config.port}/api/notifications/stream`);
  console.log(`🔄 Redis Pub/Sub: ${config.redis.host}:${config.redis.port} (Channel: ${config.redis.channel})`);
  console.log('='.repeat(65));
});

process.on('SIGTERM', () => {
  console.log('Đang dừng Notification Service...');
  server.close(() => process.exit(0));
});
