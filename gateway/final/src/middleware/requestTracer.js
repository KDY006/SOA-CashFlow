/**
 * Request Tracing & Structured Logging Middleware
 * Gắn X-Request-Id cho mọi request để truy vết liên dịch vụ (Distributed Tracing)
 */
const crypto = require('node:crypto');

function requestTracer(req, res, next) {
  const requestId = req.headers['x-request-id'] || crypto.randomUUID();
  const startHr = process.hrtime.bigint();

  req.requestId = requestId;
  req.headers['x-request-id'] = requestId;
  res.setHeader('X-Request-Id', requestId);

  res.on('finish', () => {
    if (req.path === '/health') return;
    const durationMs = Number(process.hrtime.bigint() - startHr) / 1e6;
    const uid = req.authContext?.userId || '-';
    console.log(
      JSON.stringify({
        ts: new Date().toISOString(),
        level: res.statusCode >= 500 ? 'ERROR' : res.statusCode >= 400 ? 'WARN' : 'INFO',
        requestId,
        method: req.method,
        path: req.originalUrl,
        status: res.statusCode,
        durationMs: Number(durationMs.toFixed(2)),
        userId: uid,
      })
    );
  });

  next();
}

module.exports = requestTracer;
