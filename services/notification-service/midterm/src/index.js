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
    'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-User-Id, X-User-Role',
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
      } catch {
        reject(new Error('Định dạng JSON gửi lên không hợp lệ'));
      }
    });
    req.on('error', reject);
  });
}

function headerUserIdFrom(req) {
  const headerUserId = req.headers['x-user-id'];
  return headerUserId && Number(headerUserId) > 0 ? Number(headerUserId) : null;
}

function isAdmin(req) {
  return String(req.headers['x-user-role'] || '').trim().toUpperCase() === 'ADMIN';
}

function resolveUserId(req, query, paramId, bodyUserId) {
  const requested = [paramId, bodyUserId, query.user_id].find((v) => v && Number(v) > 0);
  const headerUserId = headerUserIdFrom(req);
  if (headerUserId) {
    return isAdmin(req) && requested ? Number(requested) : headerUserId;
  }
  return requested ? Number(requested) : 1;
}

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
    sse.broadcastToUser(notif.userId, notif);
  } catch (err) {
    console.error(`[Redis Event Error] ${err.message}`);
  }
});

const server = http.createServer(async (req, res) => {
  if (req.method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-User-Id, X-User-Role',
    });
    return res.end();
  }

  const parsedUrl = parse(req.url, true);
  const pathname = (parsedUrl.pathname || '/').replace(/\/+$/, '') || '/';
  const method = req.method.toUpperCase();
  const query = parsedUrl.query;

  try {
    if (pathname === '/health' || pathname === '/') {
      return sendJson(res, 200, {
        service: 'notification-service',
        status: 'UP',
        technology: 'Native Node.js (Zero-dependency)',
        version: '1.0.0-midterm',
        time: new Date().toISOString(),
        redis: redisClient.getStatus(),
        activeSseClients: sse.getClientCount(),
      });
    }

    if (pathname === '/api/notifications/stream' && method === 'GET') {
      return sse.registerClient(resolveUserId(req, query), req, res);
    }

    const userNotifMatch = pathname.match(/^\/api\/notifications\/user\/(\d+)$/);
    if ((pathname === '/api/notifications' || userNotifMatch) && method === 'GET') {
      const targetUserId = resolveUserId(req, query, userNotifMatch ? userNotifMatch[1] : null);
      const limit = parseInt(query.limit || '50', 10);
      const offset = parseInt(query.offset || '0', 10);
      const unreadOnly = query.unread_only === 'true';

      return sendJson(res, 200, {
        success: true,
        userId: targetUserId,
        ...store.getByUser(targetUserId, { limit, offset, unreadOnly }),
      });
    }

    if ((pathname === '/api/notifications/send' || pathname === '/api/notifications') && method === 'POST') {
      const body = await parseJsonBody(req);
      const userId = resolveUserId(req, query, null, body.userId || body.user_id);
      const { title, message, type = 'SYSTEM', metadata = {} } = body;

      if (!title || !message) {
        return sendJson(res, 400, {
          success: false,
          error: 'VALIDATION_ERROR',
          message: 'Tiêu đề (title) và nội dung (message) là bắt buộc',
        });
      }

      const notification = store.addNotification({ userId, title, message, type, metadata });
      const deliveredCount = sse.broadcastToUser(userId, notification);
      redisClient.publishNotification(notification);

      return sendJson(res, 201, {
        success: true,
        message: 'Tạo và phát thông báo thành công',
        data: notification,
        realtimeDelivered: deliveredCount > 0,
        activeClients: deliveredCount,
      });
    }

    const readMatch = pathname.match(/^\/api\/notifications\/(\d+)\/read$/);
    if (readMatch && method === 'PUT') {
      const notifId = readMatch[1];
      const userId = resolveUserId(req, query);
      const ownerFilter = isAdmin(req) && headerUserIdFrom(req) && !query.user_id ? null : userId;
      const updated = store.markAsRead(notifId, ownerFilter);

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

    if (pathname === '/api/notifications/read-all' && method === 'PUT') {
      const count = store.markAllAsRead(resolveUserId(req, query));
      return sendJson(res, 200, {
        success: true,
        message: `Đã đánh dấu ${count} thông báo là đã đọc`,
        updatedCount: count,
      });
    }

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
  console.log(`[Notification Service Native Node.js] Listening on http://${config.host}:${config.port}`);
});

process.on('SIGTERM', () => {
  server.close(() => process.exit(0));
});
