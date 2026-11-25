/**
 * Express API Gateway Application Setup (Final Phase)
 * Tích hợp: CORS, Rate Limiting, Distributed Tracing (X-Request-Id),
 * JWT Auth Guard, Swagger UI và Streaming Reverse Proxy (http-proxy-middleware)
 */
const express = require('express');
const cors = require('cors');
const rateLimit = require('express-rate-limit');
const { createProxyMiddleware } = require('http-proxy-middleware');
const config = require('./config');
const requestTracer = require('./middleware/requestTracer');
const authGuard = require('./middleware/authGuard');
const docsRoutes = require('./routes/docsRoutes');

const app = express();
app.set('trust proxy', 1);

// 1. CORS toàn cục
app.use(
  cors({
    origin: '*',
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization', 'X-User-Id', 'X-User-Role', 'X-Request-Id', 'Idempotency-Key', 'X-Signature'],
    exposedHeaders: ['X-Request-Id', 'X-Proxied-By'],
  })
);

// 2. Request Tracing & Logging (X-Request-Id)
app.use(requestTracer);

// 3. Rate Limiting (express-rate-limit)
app.use(
  rateLimit({
    windowMs: config.rateLimit.windowMs,
    max: config.rateLimit.max,
    standardHeaders: true,
    legacyHeaders: false,
    skip: (req) => req.path === '/health' || req.path === '/api/notifications/stream',
    handler: (req, res) => {
      res.status(429).json({
        success: false,
        error: 'RATE_LIMIT_EXCEEDED',
        message: 'Bạn đã gửi quá nhiều yêu cầu trong thời gian ngắn. Vui lòng thử lại sau.',
        requestId: req.requestId,
      });
    },
  })
);

// 4. Gateway Info & Health Check
app.get(['/', '/health'], (req, res) => {
  res.json({
    service: 'cashflow-api-gateway',
    status: 'UP',
    architecture: 'Polyglot Microservices (SOA CashFlow)',
    framework: 'Express.js + http-proxy-middleware + JWT + RateLimit',
    version: '2.0.0-final',
    env: config.env,
    time: new Date().toISOString(),
    uptimeSeconds: Math.floor(process.uptime()),
    routes: config.serviceRoutes.map((r) => ({
      service: r.service,
      prefixes: r.prefixes,
      target: config.services[r.service],
    })),
  });
});

// 5. Aggregated Downstream Health Check (Kiểm tra trạng thái toàn bộ 6 microservices)
app.get('/health/services', async (req, res) => {
  const checks = await Promise.all(
    config.serviceRoutes.map(async (r) => {
      const target = config.services[r.service];
      const url = `${target}${r.healthPath || '/health'}`;
      const start = Date.now();
      try {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 3000);
        const resp = await fetch(url, { signal: controller.signal });
        clearTimeout(timer);
        return {
          service: r.service,
          target,
          status: resp.ok ? 'UP' : 'DEGRADED',
          httpStatus: resp.status,
          latencyMs: Date.now() - start,
        };
      } catch (err) {
        return {
          service: r.service,
          target,
          status: 'DOWN',
          error: err.message,
          latencyMs: Date.now() - start,
        };
      }
    })
  );

  const allUp = checks.every((c) => c.status === 'UP');
  res.status(allUp ? 200 : 503).json({
    success: allUp,
    gateway: 'UP',
    overallStatus: allUp ? 'ALL_SERVICES_UP' : 'DEGRADED',
    timestamp: new Date().toISOString(),
    services: checks,
  });
});

// 6. Swagger UI & OpenAPI Spec Routes
app.use(docsRoutes);

// 7. JWT Authentication Guard (bảo vệ toàn bộ các API route trừ whitelist)
app.use(authGuard);

// 8. Dynamic Reverse Proxy cho từng Microservice (KHÔNG gắn express.json() trước proxy để stream nguyên vẹn body)
const wsProxies = [];

for (const route of config.serviceRoutes) {
  const target = config.services[route.service];
  if (!target) continue;

  const proxy = createProxyMiddleware({
    target,
    changeOrigin: true,
    ws: Boolean(route.ws),
    pathFilter: (pathname) =>
      route.prefixes.some((prefix) => pathname === prefix || pathname.startsWith(prefix + '/')),
    proxyTimeout: route.ws ? 0 : 20000,
    timeout: route.ws ? 0 : 20000,
    on: {
      proxyReq: (proxyReq, req) => {
        if (req.requestId) {
          proxyReq.setHeader('X-Request-Id', req.requestId);
        }
        if (req.authContext?.userId) {
          proxyReq.setHeader('X-User-Id', String(req.authContext.userId));
        }
        if (req.authContext?.username) {
          proxyReq.setHeader('X-User-Name', String(req.authContext.username));
        }
        if (req.authContext?.role) {
          proxyReq.setHeader('X-User-Role', String(req.authContext.role));
        }
      },
      proxyRes: (proxyRes) => {
        proxyRes.headers['x-proxied-by'] = 'CashFlow-Express-Gateway-Final';
      },
      error: (err, req, res) => {
        if (res.headersSent) return;
        res.status(502).json({
          success: false,
          error: 'BAD_GATEWAY',
          message: `Không thể kết nối tới microservice '${route.service}': ${err.message}`,
          targetUrl: `${target}${req.originalUrl}`,
          requestId: req.requestId,
        });
      },
    },
  });

  app.use(proxy);
  if (route.ws) {
    wsProxies.push(proxy);
  }
}

// 9. Fallback 404
app.use((req, res) => {
  res.status(404).json({
    success: false,
    error: 'ROUTE_NOT_FOUND',
    message: `Đường dẫn '${req.method} ${req.originalUrl}' không khớp với bất kỳ microservice nào qua Gateway.`,
    availablePrefixes: config.serviceRoutes.flatMap((r) => r.prefixes),
    requestId: req.requestId,
  });
});

// 10. Global Error Handler
app.use((err, req, res, next) => {
  console.error(`[Gateway Error] ${err.stack || err.message}`);
  res.status(500).json({
    success: false,
    error: 'INTERNAL_SERVER_ERROR',
    message: err.message || 'Lỗi nội bộ API Gateway',
    requestId: req.requestId,
  });
});

module.exports = { app, wsProxies };
