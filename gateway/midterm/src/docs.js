/**
 * API Docs (Swagger UI) cho toàn bộ hệ thống SOA CashFlow
 * - Không phụ thuộc FastAPI hay framework nào: mỗi service có 1 file OpenAPI 3 (src/openapi/*.json)
 * - Gateway phục vụ:
 *     GET /docs                     -> Swagger UI (chọn service ở góc phải trên)
 *     GET /docs/<service>           -> Swagger UI mở sẵn service đó
 *     GET /openapi.json             -> Spec gộp tất cả service
 *     GET /openapi/<service>.json   -> Spec riêng từng service
 * - "Try it out" gửi request thật qua Gateway (cùng origin, có JWT Bearer).
 */

const fs = require('node:fs');
const path = require('node:path');

const SPEC_DIR = path.join(__dirname, 'openapi');

// Thứ tự hiển thị trong dropdown
const SERVICES = [
  { key: 'auth', label: '1. Auth Service (Java · 8081)' },
  { key: 'transaction', label: '2. Transaction Service (PHP · 8082)' },
  { key: 'asset', label: '3. Asset Service (C# .NET · 8083)' },
  { key: 'analytics', label: '4. Analytics Service (Python · 8084)' },
  { key: 'integration', label: '5. Integration Service (Go · 8085)' },
  { key: 'notification', label: '6. Notification Service (Node.js · 8086)' },
];

function loadSpec(key) {
  const file = path.join(SPEC_DIR, `${key}.json`);
  if (!fs.existsSync(file)) return null;
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8').replace(/^\uFEFF/, ''));
  } catch (err) {
    console.error(`[docs] Spec lỗi ${file}: ${err.message}`);
    return null;
  }
}

function availableServices() {
  return SERVICES.filter((s) => fs.existsSync(path.join(SPEC_DIR, `${s.key}.json`)));
}

// Gộp tất cả spec thành 1 (tag theo tên service)
function buildMergedSpec() {
  const merged = {
    openapi: '3.0.3',
    info: {
      title: 'SOA CashFlow — Toàn bộ API (qua Gateway :8080)',
      version: '1.0.0-midterm',
      description: '6 microservices · 6 ngôn ngữ. Đăng nhập bằng `POST /api/auth/login` → token được tự động gắn vào nút **Authorize**.',
    },
    servers: [{ url: '/', description: 'Qua API Gateway' }],
    tags: [],
    paths: {},
    components: {
      securitySchemes: { bearerAuth: { type: 'http', scheme: 'bearer', bearerFormat: 'JWT' } },
      schemas: {},
    },
    security: [{ bearerAuth: [] }],
  };

  for (const svc of availableServices()) {
    const spec = loadSpec(svc.key);
    if (!spec) continue;
    const tagName = svc.label.replace(/^\d+\.\s*/, '');
    merged.tags.push({ name: tagName, description: (spec.info && spec.info.description) || '' });

    for (const [p, ops] of Object.entries(spec.paths || {})) {
      merged.paths[p] = merged.paths[p] || {};
      for (const [method, op] of Object.entries(ops)) {
        if (typeof op !== 'object' || op === null || !/^(get|post|put|patch|delete|options|head)$/i.test(method)) {
          merged.paths[p][method] = op;
          continue;
        }
        const clone = JSON.parse(JSON.stringify(op));
        clone.tags = [tagName];
        if (clone.operationId) clone.operationId = `${svc.key}_${clone.operationId}`;
        merged.paths[p][method] = clone;
      }
    }

    const comps = spec.components || {};
    for (const [section, items] of Object.entries(comps)) {
      if (section === 'securitySchemes') continue;
      merged.components[section] = merged.components[section] || {};
      Object.assign(merged.components[section], items);
    }
  }
  return merged;
}

function swaggerHtml(primaryName) {
  const urls = [{ url: '/openapi.json', name: '★ Tất cả service (gộp)' }].concat(
    availableServices().map((s) => ({ url: `/openapi/${s.key}.json`, name: s.label }))
  );
  const primary = urls.find((u) => u.url === `/openapi/${primaryName}.json`);

  return `<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CashFlow API Docs · Swagger UI</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
  <style>
    body { margin: 0; background: #fafafa; }
    .cf-bar { display:flex; align-items:center; gap:12px; padding:10px 24px; background:#0b1324; color:#e2e8f0;
              font-family: system-ui, -apple-system, Segoe UI, sans-serif; font-size:13.5px; flex-wrap:wrap; }
    .cf-bar strong { font-size:15.5px; color:#fff; margin-right:4px; }
    .cf-bar .pill { background:#1e293b; padding:5px 12px; border-radius:999px; font-size:12.5px; border:1px solid #334155; }
    .cf-bar .pill.active { background:rgba(16,185,129,.14); border-color:rgba(16,185,129,.45); color:#d1fae5; }
    .cf-bar .ok { color:#34d399; font-weight:700; }
    .cf-bar select, .cf-bar input { background:#1e293b; color:#f8fafc; border:1px solid #334155; padding:5px 10px; border-radius:6px; font-size:12.5px; outline:none; }
    .cf-bar button { background:#10b981; color:#fff; border:0; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:600; font-size:12.5px; }
    .cf-bar button.ghost { background:#334155; }
    .swagger-ui .topbar { background:#111827; }
  </style>
</head>
<body>
  <div class="cf-bar">
    <strong>💸 CashFlow API Docs</strong>
    <span class="pill">Gateway :8080</span>
    <span class="pill" id="cfAuth">🔓 Chưa đăng nhập</span>

    <div style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <select id="cfUserSelect" title="Chọn nhanh tài khoản trong DB">
        <option value="admin">#1 · admin (ADMIN)</option>
        <option value="member1">#2 · member1 (USER)</option>
        <option value="nguyenvana">#3 · nguyenvana (USER)</option>
      </select>
      <input type="password" id="cfPass" value="password123" placeholder="Mật khẩu" style="width:105px" title="Mật khẩu của tài khoản">
      <button id="cfQuick">⚡ Đăng nhập TK này</button>
      <button id="cfLogout" class="ghost">Xoá token</button>
    </div>
  </div>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
  <script>
    const TOKEN_KEY = 'cf_swagger_token';

    function decodeJwt(token) {
      try {
        const p = token.split('.')[1];
        return JSON.parse(decodeURIComponent(atob(p.replace(/-/g, '+').replace(/_/g, '/')).split('').map(c =>
          '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
        ).join('')));
      } catch (_) {
        return null;
      }
    }

    function findToken(obj, depth) {
      if (!obj || typeof obj !== 'object' || depth > 4) return null;
      for (const k of ['accessToken', 'access_token', 'token', 'jwt']) {
        if (typeof obj[k] === 'string' && obj[k].split('.').length === 3) return obj[k];
      }
      for (const v of Object.values(obj)) {
        const t = findToken(v, (depth || 0) + 1);
        if (t) return t;
      }
      return null;
    }

    async function refreshUserDropdown(token) {
      if (!token) return;
      try {
        const r = await fetch('/api/admin/users', { headers: { 'Authorization': 'Bearer ' + token } });
        if (!r.ok) return;
        const j = await r.json();
        const users = j.data || j.users || [];
        if (!Array.isArray(users) || !users.length) return;
        const sel = document.getElementById('cfUserSelect');
        const cur = sel.value;
        sel.innerHTML = users.map(u =>
          '<option value="' + u.username + '">#' + u.id + ' · ' + u.username + ' (' + u.role + ')</option>'
        ).join('');
        if ([...sel.options].some(o => o.value === cur)) sel.value = cur;
      } catch (_) {}
    }

    function setToken(token) {
      const el = document.getElementById('cfAuth');
      if (token) {
        localStorage.setItem(TOKEN_KEY, token);
        window.ui && window.ui.preauthorizeApiKey('bearerAuth', token);
        const c = decodeJwt(token) || {};
        const uid = c.userId || c.sub || '?';
        const uname = c.username || 'user';
        const role = c.role || 'USER';
        el.className = 'pill active';
        el.innerHTML = '<span class="ok">🔐 Đang ở TK:</span> <b>User #' + uid + ' · ' + uname + '</b> (' + role + ')';
        const sel = document.getElementById('cfUserSelect');
        if ([...sel.options].some(o => o.value === uname)) sel.value = uname;
        refreshUserDropdown(token);
      } else {
        localStorage.removeItem(TOKEN_KEY);
        window.ui && window.ui.authActions.logout(['bearerAuth']);
        el.className = 'pill';
        el.textContent = '🔓 Chưa đăng nhập';
      }
    }

    window.ui = SwaggerUIBundle({
      urls: ${JSON.stringify(urls)},
      ${primary ? `'urls.primaryName': ${JSON.stringify(primary.name)},` : ''}
      dom_id: '#swagger-ui',
      deepLinking: true,
      persistAuthorization: true,
      displayRequestDuration: true,
      tryItOutEnabled: true,
      filter: true,
      docExpansion: 'list',
      defaultModelsExpandDepth: 0,
      presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
      layout: 'StandaloneLayout',
      responseInterceptor: (res) => {
        try {
          if (/\\/api\\/auth\\/(login|refresh)/.test(res.url) && res.ok) {
            const body = res.obj || JSON.parse(res.text || '{}');
            const t = findToken(body, 0);
            if (t) setToken(t);
          }
        } catch (_) {}
        return res;
      },
      onComplete: () => {
        const saved = localStorage.getItem(TOKEN_KEY);
        if (saved) setToken(saved);
      },
    });

    document.getElementById('cfQuick').onclick = async () => {
      const username = document.getElementById('cfUserSelect').value;
      const password = document.getElementById('cfPass').value || 'password123';
      const r = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password }),
      });
      const j = await r.json().catch(() => ({}));
      const t = findToken(j, 0);
      if (t) setToken(t); else alert('Đăng nhập thất bại (' + username + '): ' + (j.message || JSON.stringify(j)));
    };
    document.getElementById('cfLogout').onclick = () => setToken(null);
  </script>
</body>
</html>`;
}

/**
 * Xử lý các route tài liệu. Trả về true nếu đã xử lý.
 */
function handleDocs(pathname, res) {
  const send = (status, type, body) => {
    res.writeHead(status, {
      'Content-Type': type,
      'Access-Control-Allow-Origin': '*',
      'Cache-Control': 'no-cache',
    });
    res.end(body);
  };

  if (pathname === '/docs' || pathname === '/swagger' || pathname.startsWith('/docs/')) {
    const svc = pathname.startsWith('/docs/') ? pathname.slice(6) : '';
    send(200, 'text/html; charset=utf-8', swaggerHtml(svc));
    return true;
  }

  if (pathname === '/openapi.json') {
    send(200, 'application/json; charset=utf-8', JSON.stringify(buildMergedSpec(), null, 2));
    return true;
  }

  const m = pathname.match(/^\/openapi\/([a-z-]+)\.json$/);
  if (m) {
    const spec = loadSpec(m[1]);
    if (!spec) {
      send(404, 'application/json; charset=utf-8', JSON.stringify({ success: false, error: 'SPEC_NOT_FOUND', service: m[1] }));
    } else {
      send(200, 'application/json; charset=utf-8', JSON.stringify(spec, null, 2));
    }
    return true;
  }

  return false;
}

module.exports = { handleDocs };
