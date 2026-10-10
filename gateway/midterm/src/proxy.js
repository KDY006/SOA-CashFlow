/**
 * Streaming Reverse Proxy Engine (Native Node.js node:http)
 * Chuyển tiếp request và response dạng stream hiệu năng cao không qua bộ đệm RAM
 */

const http = require('node:http');
const https = require('node:https');
const { URL } = require('node:url');

/**
 * Chuyển tiếp HTTP Request sang Microservice đích
 * @param {http.IncomingMessage} req 
 * @param {http.ServerResponse} res 
 * @param {string} targetBaseUrl 
 * @param {object} context Thông tin inject thêm: { userId, username, role, serviceName }
 */
function forwardRequest(req, res, targetBaseUrl, context = {}) {
  let target;
  try {
    target = new URL(targetBaseUrl);
  } catch (err) {
    res.writeHead(500, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify({
      success: false,
      error: 'CONFIGURATION_ERROR',
      message: `Địa chỉ service không hợp lệ: ${targetBaseUrl}`,
    }));
    return;
  }

  const client = target.protocol === 'https:' ? https : http;

  // Bản sao headers và loại bỏ những header hop-by-hop
  const forwardedHeaders = { ...req.headers };
  delete forwardedHeaders['connection'];
  delete forwardedHeaders['keep-alive'];
  delete forwardedHeaders['proxy-authenticate'];
  delete forwardedHeaders['proxy-authorization'];
  delete forwardedHeaders['te'];
  delete forwardedHeaders['trailers'];
  delete forwardedHeaders['transfer-encoding'];
  delete forwardedHeaders['upgrade'];

  // Cập nhật Host header về service đích
  forwardedHeaders['host'] = target.host;
  forwardedHeaders['x-forwarded-for'] = req.socket.remoteAddress || '127.0.0.1';
  forwardedHeaders['x-forwarded-proto'] = 'http';
  forwardedHeaders['x-forwarded-host'] = req.headers.host || '';

  // Inject định danh người dùng đã xác thực từ JWT
  if (context.userId) {
    forwardedHeaders['x-user-id'] = String(context.userId);
  }
  if (context.username) {
    forwardedHeaders['x-user-name'] = String(context.username);
  }
  if (context.role) {
    forwardedHeaders['x-user-role'] = String(context.role);
  }

  const options = {
    protocol: target.protocol,
    hostname: target.hostname,
    port: target.port || (target.protocol === 'https:' ? 443 : 80),
    method: req.method,
    path: req.url, // giữ nguyên toàn bộ path và query string gốc
    headers: forwardedHeaders,
    timeout: 15000, // Timeout 15 giây
  };

  const proxyReq = client.request(options, (proxyRes) => {
    // Thêm các header CORS chuẩn cho response trả về client
    const resHeaders = { ...proxyRes.headers };
    resHeaders['access-control-allow-origin'] = '*';
    resHeaders['access-control-allow-methods'] = 'GET, POST, PUT, DELETE, PATCH, OPTIONS';
    resHeaders['access-control-allow-headers'] = 'Content-Type, Authorization, X-User-Id';
    resHeaders['x-proxied-by'] = 'CashFlow-Native-Gateway';

    res.writeHead(proxyRes.statusCode, resHeaders);
    proxyRes.pipe(res);
  });

  proxyReq.on('timeout', () => {
    proxyReq.destroy(new Error('Gateway timeout (kết nối vượt quá 15 giây)'));
  });

  proxyReq.on('error', (err) => {
    if (res.headersSent) return;

    res.writeHead(502, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify({
      success: false,
      error: 'BAD_GATEWAY',
      message: `Không thể kết nối tới microservice '${context.serviceName || 'unknown'}': ${err.message}`,
      targetUrl: `${targetBaseUrl}${req.url}`,
    }));
  });

  // Stream toàn bộ request body từ client sang service đích
  req.pipe(proxyReq);
}

module.exports = {
  forwardRequest,
};
