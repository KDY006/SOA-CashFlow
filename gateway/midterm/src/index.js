/**
 * SOA CashFlow - API Gateway (Native Node.js / Zero-dependency)
 * Nhóm trưởng đảm nhiệm - Phiên bản Giữa kỳ (Midterm)
 */

const http = require('node:http');
const { parse } = require('node:url');
const config = require('./config');
const { verifyJwt, extractBearerToken } = require('./jwt');
const { forwardRequest } = require('./proxy');

// Bộ đếm Rate Limiting trong bộ nhớ (In-memory IP Sliding Window)
const rateLimitMap = new Map();
const RATE_LIMIT_WINDOW_MS = 60 * 1000; // 1 phút
const RATE_LIMIT_MAX_REQUESTS = 300;     // Tối đa 300 requests/phút/IP

function isRateLimited(clientIp) {
  const now = Date.now();
  let clientRecord = rateLimitMap.get(clientIp);

  if (!clientRecord || now - clientRecord.startTime > RATE_LIMIT_WINDOW_MS) {
    clientRecord = { count: 1, startTime: now };
    rateLimitMap.set(clientIp, clientRecord);
    return false;
  }

  clientRecord.count += 1;
  return clientRecord.count > RATE_LIMIT_MAX_REQUESTS;
}

// Dọn dẹp rateLimitMap mỗi 5 phút tránh phình bộ nhớ
setInterval(() => {
  const now = Date.now();
  for (const [ip, record] of rateLimitMap.entries()) {
    if (now - record.startTime > RATE_LIMIT_WINDOW_MS) {
      rateLimitMap.delete(ip);
    }
  }
}, 5 * 60 * 1000).unref();

function sendJson(res, statusCode, data) {
  const payload = JSON.stringify(data, null, 2);
  res.writeHead(statusCode, {
    'Content-Type': 'application/json; charset=utf-8',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-User-Id',
    'Content-Length': Buffer.byteLength(payload),
  });
  res.end(payload);
}

// Khởi tạo máy chủ HTTP Gateway thuần
const server = http.createServer((req, res) => {
  const clientIp = req.socket.remoteAddress || '127.0.0.1';

  // 1. Kiểm soát Rate Limiting
  if (isRateLimited(clientIp)) {
    return sendJson(res, 429, {
      success: false,
      error: 'RATE_LIMIT_EXCEEDED',
      message: 'Bạn đã gửi quá nhiều yêu cầu trong thời gian ngắn. Vui lòng thử lại sau 1 phút.',
    });
  }

  // 2. Xử lý CORS Preflight Request toàn cục
  if (req.method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-User-Id',
      'Access-Control-Max-Age': '86400',
    });
    return res.end();
  }

  const parsedUrl = parse(req.url, true);
  const pathname = (parsedUrl.pathname || '/').replace(/\/+$/, '') || '/';
  const method = req.method.toUpperCase();

  // 3. Endpoint thông tin Gateway & Health Check
  if (pathname === '/' || pathname === '/health') {
    return sendJson(res, 200, {
      service: 'cashflow-api-gateway',
      status: 'UP',
      architecture: 'Microservices (SOA CashFlow)',
      technology: 'Native Node.js (Zero-dependency)',
      version: '1.0.0-midterm',
      time: new Date().toISOString(),
      routes: config.routeMap.map((r) => ({
        prefix: r.prefix,
        service: r.service,
        target: config.services[r.service],
      })),
    });
  }

  // 4. Tìm kiếm Microservice tương ứng với URL prefix
  const matchedRoute = config.routeMap.find((route) =>
    pathname === route.prefix || pathname.startsWith(route.prefix + '/')
  );

  if (!matchedRoute) {
    return sendJson(res, 404, {
      success: false,
      error: 'ROUTE_NOT_FOUND',
      message: `Đường dẫn '${method} ${pathname}' không khớp với bất kỳ microservice nào qua Gateway.`,
      availablePrefixes: config.routeMap.map((r) => r.prefix),
    });
  }

  const serviceName = matchedRoute.service;
  const targetBaseUrl = config.services[serviceName];

  if (!targetBaseUrl) {
    return sendJson(res, 500, {
      success: false,
      error: 'SERVICE_UNCONFIGURED',
      message: `Dịch vụ '${serviceName}' chưa được cấu hình địa chỉ URL.`,
    });
  }

  // 5. Kiểm tra Endpoint Công khai vs Endpoint Yêu cầu Xác thực (JWT Auth Guard)
  const isPublic = config.publicEndpoints.some(
    (ep) => ep.method === method && (pathname === ep.path || pathname.startsWith(ep.path + '/'))
  );

  const authHeader = req.headers['authorization'];
  const token = extractBearerToken(authHeader);
  let authContext = { serviceName };

  if (token) {
    const verified = verifyJwt(token, config.jwtSecret);
    if (verified.valid) {
      authContext = {
        serviceName,
        userId: verified.payload.userId,
        username: verified.payload.username,
        role: verified.payload.role,
      };
    } else if (!isPublic && config.authStrict) {
      return sendJson(res, 401, {
        success: false,
        error: 'UNAUTHORIZED',
        message: `Token xác thực không hợp lệ: ${verified.error}`,
      });
    }
  } else if (!isPublic && config.authStrict) {
    return sendJson(res, 401, {
      success: false,
      error: 'UNAUTHORIZED',
      message: 'Yêu cầu token xác thực (Authorization: Bearer <token>) để truy cập endpoint này.',
      path: pathname,
    });
  }

  // 6. Chuyển tiếp Request qua Streaming Reverse Proxy
  forwardRequest(req, res, targetBaseUrl, authContext);
});

// Khởi chạy Gateway
server.listen(config.port, config.host, () => {
  console.log('='.repeat(65));
  console.log(`🚀 [API Gateway Native Node.js] Đang lắng nghe trên: http://${config.host}:${config.port}`);
  console.log(`🔒 Xác thực JWT: ${config.authStrict ? 'BẬT (Strict Mode)' : 'TẮT (Permissive)'}`);
  console.log('📋 Danh sách Microservices kết nối:');
  for (const [name, url] of Object.entries(config.services)) {
    console.log(`   - ${name.padEnd(14)} : ${url}`);
  }
  console.log('='.repeat(65));
});

process.on('SIGTERM', () => {
  console.log('Đang dừng API Gateway...');
  server.close(() => process.exit(0));
});
