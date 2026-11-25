const http = require('node:http');
const https = require('node:https');
const { URL } = require('node:url');

function forwardRequest(req, res, targetBaseUrl, context = {}) {
  let target;
  try {
    target = new URL(targetBaseUrl);
  } catch {
    res.writeHead(500, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify({
      success: false,
      error: 'CONFIGURATION_ERROR',
      message: `Địa chỉ service không hợp lệ: ${targetBaseUrl}`,
    }));
    return;
  }

  const client = target.protocol === 'https:' ? https : http;
  const forwardedHeaders = { ...req.headers };
  for (const h of ['connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailers', 'transfer-encoding', 'upgrade', 'x-user-id', 'x-user-name', 'x-user-role']) {
    delete forwardedHeaders[h];
  }

  forwardedHeaders['host'] = target.host;
  forwardedHeaders['x-forwarded-for'] = req.socket.remoteAddress || '127.0.0.1';
  forwardedHeaders['x-forwarded-proto'] = 'http';
  forwardedHeaders['x-forwarded-host'] = req.headers.host || '';

  if (context.userId) forwardedHeaders['x-user-id'] = String(context.userId);
  if (context.username) forwardedHeaders['x-user-name'] = String(context.username);
  if (context.role) forwardedHeaders['x-user-role'] = String(context.role);

  const proxyReq = client.request(
    {
      protocol: target.protocol,
      hostname: target.hostname,
      port: target.port || (target.protocol === 'https:' ? 443 : 80),
      method: req.method,
      path: req.url,
      headers: forwardedHeaders,
      timeout: req.url.startsWith('/api/notifications/stream') ? 0 : 15000,
    },
    (proxyRes) => {
      const resHeaders = {
        ...proxyRes.headers,
        'access-control-allow-origin': '*',
        'access-control-allow-methods': 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
        'access-control-allow-headers': 'Content-Type, Authorization, X-User-Id',
        'x-proxied-by': 'CashFlow-Native-Gateway',
      };
      res.writeHead(proxyRes.statusCode, resHeaders);
      proxyRes.pipe(res);
    }
  );

  proxyReq.on('timeout', () => proxyReq.destroy(new Error('Gateway timeout (15s)')));
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

  req.pipe(proxyReq);
}

module.exports = { forwardRequest };
