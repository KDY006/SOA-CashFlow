/**
 * JWT Authentication Guard Middleware (jsonwebtoken)
 * - Xóa mọi header định danh giả mạo từ Client (X-User-Id, X-User-Role, X-User-Name)
 * - Xác thực chữ ký HMAC-SHA256 khớp với Spring Boot Auth Service & ASP.NET Asset Service
 * - Inject X-User-Id, X-User-Name, X-User-Role đã xác thực vào downstream services
 */
const jwt = require('jsonwebtoken');
const config = require('../config');

function isPublicEndpoint(method, pathname) {
  const cleanPath = (pathname || '/').replace(/\/+$/, '') || '/';
  const upperMethod = method.toUpperCase();
  return config.publicEndpoints.some(
    (ep) =>
      ep.method === upperMethod &&
      (cleanPath === ep.path || cleanPath.startsWith(ep.path + '/'))
  );
}

function extractToken(req) {
  const authHeader = req.headers['authorization'];
  if (authHeader && typeof authHeader === 'string') {
    const match = authHeader.match(/^Bearer\s+(.+)$/i);
    if (match) return match[1].trim();
  }
  // Hỗ trợ EventSource (SSE) truyền token qua query ?access_token= hoặc ?token=
  if (req.path === '/api/notifications/stream') {
    const qToken = req.query.access_token || req.query.token;
    if (qToken && typeof qToken === 'string') return qToken.trim();
  }
  return null;
}

function authGuard(req, res, next) {
  // Luôn loại bỏ header giả mạo từ bên ngoài trước khi xác thực
  delete req.headers['x-user-id'];
  delete req.headers['x-user-name'];
  delete req.headers['x-user-role'];

  if (req.method === 'OPTIONS') {
    return next();
  }

  const isPublic = isPublicEndpoint(req.method, req.path);
  const token = extractToken(req);

  if (token) {
    try {
      const payload = jwt.verify(token, config.jwtSecret, { algorithms: ['HS256'] });
      const userId = payload.userId || payload.user_id || payload.id || payload.sub;
      req.authContext = {
        userId: userId ? Number(userId) : null,
        username: payload.username || null,
        role: payload.role || 'USER',
      };

      if (req.authContext.userId) {
        req.headers['x-user-id'] = String(req.authContext.userId);
      }
      if (req.authContext.username) {
        req.headers['x-user-name'] = String(req.authContext.username);
      }
      if (req.authContext.role) {
        req.headers['x-user-role'] = String(req.authContext.role);
      }
      return next();
    } catch (err) {
      if (!isPublic && config.authStrict) {
        return res.status(401).json({
          success: false,
          error: 'UNAUTHORIZED',
          message: `Token xác thực không hợp lệ hoặc đã hết hạn: ${err.message}`,
          requestId: req.requestId,
        });
      }
      return next();
    }
  }

  if (!isPublic && config.authStrict) {
    return res.status(401).json({
      success: false,
      error: 'UNAUTHORIZED',
      message: 'Yêu cầu token xác thực (Authorization: Bearer <token>) để truy cập endpoint này.',
      path: req.path,
      requestId: req.requestId,
    });
  }

  return next();
}

module.exports = authGuard;
