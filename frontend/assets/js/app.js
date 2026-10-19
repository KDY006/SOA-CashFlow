/**
 * CashFlow - tương tác phía trình duyệt
 * Modal, toast, thanh tiến độ, SSE realtime, helper gọi API qua proxy PHP (api.php)
 */
(function () {
  'use strict';

  /* ---------------- Modal ---------------- */
  function openModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.add('open');
    document.body.style.overflow = 'hidden';
    const first = m.querySelector('input:not([type=hidden]):not([type=radio]), select, textarea');
    if (first) setTimeout(() => first.focus(), 120);
  }
  function closeModal(m) {
    if (typeof m === 'string') m = document.getElementById(m);
    if (!m) return;
    m.classList.remove('open');
    document.body.style.overflow = '';
  }
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-open]');
    if (opener) {
      e.preventDefault();
      openModal(opener.dataset.open);
      return;
    }
    if (e.target.closest('[data-close]') || e.target.classList.contains('modal')) {
      closeModal(e.target.closest('.modal'));
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('.modal.open').forEach(closeModal);
  });

  /* ---------------- Toast ---------------- */
  const ICONS = { info: 'fa-bell', success: 'fa-circle-check', warning: 'fa-triangle-exclamation', danger: 'fa-circle-exclamation' };
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }
  function toast(title, message = '', type = 'info', ms = 5500) {
    const stack = document.getElementById('toastStack');
    if (!stack) return;
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.innerHTML = `<span class="t-icon"><i class="fa-solid ${ICONS[type] || ICONS.info}"></i></span>
      <div><strong>${esc(title)}</strong>${message ? `<p>${esc(message)}</p>` : ''}</div>`;
    stack.appendChild(el);
    const kill = () => { el.classList.add('out'); setTimeout(() => el.remove(), 350); };
    el.addEventListener('click', kill);
    setTimeout(kill, ms);
  }

  /* ---------------- API proxy ---------------- */
  async function api(method, path, body) {
    const res = await fetch('api.php?path=' + encodeURIComponent(path), {
      method,
      headers: { 'Content-Type': 'application/json', 'X-CSRF': window.CASHFLOW_CSRF || '' },
      body: body ? JSON.stringify(body) : undefined,
    });
    let data = null;
    try { data = await res.json(); } catch (_) { /* ignore */ }
    if (res.status === 401 && data && data.relogin) {
      location.replace('login.php?expired=1');
    }
    if (!res.ok) {
      const msg = (data && (data.message || data.error)) || ('HTTP ' + res.status);
      throw new Error(typeof msg === 'string' ? msg : 'Yêu cầu thất bại');
    }
    return data && Object.prototype.hasOwnProperty.call(data, 'data') ? data.data : data;
  }

  function busy(btn, on, label) {
    if (!btn) return;
    if (on) {
      btn.dataset.html = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner"></span> ${label || 'Đang xử lý...'}`;
    } else {
      btn.disabled = false;
      if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
    }
  }

  const vnd = (n) => new Intl.NumberFormat('vi-VN').format(Math.round(Number(n) || 0)) + ' ₫';

  /* ---------------- Misc UI ---------------- */
  function animateBars() {
    document.querySelectorAll('[data-width]').forEach((el) => {
      requestAnimationFrame(() => { el.style.width = Math.min(100, parseFloat(el.dataset.width) || 0) + '%'; });
    });
  }

  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f.dataset.confirm && !confirm(f.dataset.confirm)) {
      e.preventDefault();
      return;
    }
    const btn = f.querySelector('button[type=submit]:not([data-nobusy])');
    if (btn && !f.dataset.ajax) busy(btn, true);
  });

  function autoHideAlerts() {
    document.querySelectorAll('[data-autohide]').forEach((el) => {
      setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 6000);
    });
  }

  /* ---------------- Realtime SSE (Notification Service) ---------------- */
  function initRealtime() {
    const url = window.CASHFLOW && window.CASHFLOW.sseUrl;
    if (!url || !window.EventSource) return;
    if (location.protocol === 'https:' && url.startsWith('http://')) return;
    let es;
    try { es = new EventSource(url); } catch (_) { return; }

    const handle = (raw) => {
      let n;
      try { n = JSON.parse(raw); } catch (_) { return; }
      if (!n || !n.title) return;
      const type = /BUDGET|ANOMALY|EXCEED/.test(n.type || '') ? 'warning' : 'info';
      toast(n.title, n.message, type, 8000);
      const badge = document.getElementById('notifBadge');
      if (badge) {
        badge.hidden = false;
        badge.textContent = (parseInt(badge.textContent || '0', 10) || 0) + 1;
      }
      document.dispatchEvent(new CustomEvent('cashflow:notification', { detail: n }));
    };
    es.addEventListener('notification', (e) => handle(e.data));
    es.onmessage = (e) => handle(e.data);
    es.onerror = () => { /* trình duyệt tự kết nối lại; không làm phiền người dùng */ };
  }

  /* ---------------- Chart defaults ---------------- */
  if (window.Chart) {
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.color = '#64748b';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(3,17,36,.95)';
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
    Chart.defaults.plugins.tooltip.titleFont = { weight: '700' };
  }

  /* ---------------- Count-up cho số liệu thống kê & số dư thẻ ---------------- */
  function countUp() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll('.stat-value .amt-num, .card-bal-val .amt-num, .lt-amount .amt-num, .dash-bank-bal .amt-num').forEach((el) => {
      const txt = el.textContent.trim();
      const dec = (txt.split(',')[1] || '').length;
      const target = parseFloat(txt.replace(/\./g, '').replace(',', '.'));
      if (!isFinite(target) || target === 0) return;
      const fmt = (v) => v.toLocaleString('vi-VN', { minimumFractionDigits: dec, maximumFractionDigits: dec });
      el.style.minWidth = el.offsetWidth + 'px'; // giữ độ rộng cuối, không giật layout
      const dur = 1150;
      const t0 = performance.now();
      const step = (now) => {
        const p = Math.min(1, (now - t0) / dur);
        const eased = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);
        el.textContent = fmt(target * eased);
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = txt;
      };
      el.textContent = fmt(0);
      requestAnimationFrame(step);
    });
  }

  /* ---------------- 3D Perspective Tilt & Cursor Spotlight (FinTech Luxury) ---------------- */
  function initLuxuryMotion() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // 3D Tilt & Spotlight trên thẻ ngân hàng và Hero Card
    document.querySelectorAll('.bank-card, .realistic-bank-card').forEach((card) => {
      let rafId = null;
      card.addEventListener('pointermove', (e) => {
        if (e.pointerType === 'touch') return;
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const px = Math.max(0, Math.min(1, x / rect.width));
        const py = Math.max(0, Math.min(1, y / rect.height));
        const rx = ((0.5 - py) * 7.5).toFixed(2);
        const ry = ((px - 0.5) * 9.5).toFixed(2);

        if (rafId) cancelAnimationFrame(rafId);
        rafId = requestAnimationFrame(() => {
          card.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
          card.style.setProperty('--my', (py * 100).toFixed(1) + '%');
          card.style.setProperty('--rx', rx + 'deg');
          card.style.setProperty('--ry', ry + 'deg');
          card.classList.add('is-tilting');
        });
      });

      const resetTilt = () => {
        if (rafId) cancelAnimationFrame(rafId);
        card.classList.remove('is-tilting');
        card.style.setProperty('--rx', '0deg');
        card.style.setProperty('--ry', '0deg');
      };
      card.addEventListener('pointerleave', resetTilt);
      card.addEventListener('pointercancel', resetTilt);
    });

    // Cursor Spotlight trên các ô KPI (.stat) và Quick Action (.action-tile-btn)
    document.querySelectorAll('.stat, .action-tile-btn').forEach((box) => {
      box.addEventListener('pointermove', (e) => {
        if (e.pointerType === 'touch') return;
        const r = box.getBoundingClientRect();
        box.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        box.style.setProperty('--my', (e.clientY - r.top) + 'px');
      });
    });
  }

  /* ---------------- Staggered Cascade Entrance cho mọi danh sách & bảng ---------------- */
  function initStaggerCascade() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const groups = [
      '.tx-list .tx-row',
      '.table tbody tr',
      '.panel .rate-row',
      '.panel .budget-item',
      '.dash-bank-list .dash-bank-row',
      '.grid .budget-card',
      '.kv > div'
    ];
    groups.forEach((sel) => {
      document.querySelectorAll(sel).forEach((el, idx) => {
        el.style.setProperty('--row-i', Math.min(idx, 14));
        el.classList.add('luxury-cascade-item');
      });
    });
  }

  function reinit() {
    animateBars();
    countUp();
    initLuxuryMotion();
    initStaggerCascade();
    autoHideAlerts();
    if (typeof window.CF_initNavbar === 'function') window.CF_initNavbar();
  }

  document.addEventListener('DOMContentLoaded', () => {
    reinit();
    initRealtime();
  });

  window.CF = { openModal, closeModal, toast, api, busy, vnd, esc, reinit };
})();
