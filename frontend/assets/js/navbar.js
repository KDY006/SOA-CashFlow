/* Navbar motion: sliding indicator, hover ghost, ripple, scroll state,
   mouse-follow glow, mobile drawer, page-load progress, live badge. */
(function () {
  'use strict';

  function initNavbar() {
  const header = document.getElementById('appNavbar');
  if (!header || header.dataset.navBound) return;
  header.dataset.navBound = '1';

  const nav = document.getElementById('mainNav');
  const progress = document.getElementById('navProgress');
  const burger = document.getElementById('navBurger');
  const backdrop = document.getElementById('mobileNavBackdrop');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Sliding active indicator + hover ghost ---------- */
  if (nav) {
    const indicator = nav.querySelector('.nav-indicator');
    const ghost = document.createElement('span');
    ghost.className = 'nav-ghost';
    ghost.setAttribute('aria-hidden', 'true');
    nav.insertBefore(ghost, nav.firstChild);

    let current = nav.querySelector('.nav-item.active');

    const place = (el, target) => {
      target.style.setProperty('--x', el.offsetLeft + 'px');
      target.style.setProperty('--w', el.offsetWidth + 'px');
    };

    const snap = (target, el) => {
      const prev = target.style.transition;
      target.style.transition = 'none';
      place(el, target);
      void target.offsetWidth;
      target.style.transition = prev;
    };

    const placeActive = (instant) => {
      if (!current) return;
      instant ? snap(indicator, current) : place(current, indicator);
    };

    if (current) {
      snap(indicator, current);
      requestAnimationFrame(() => nav.classList.add('nav-ready'));
    }

    // Widths change after web fonts load / on resize
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => placeActive(true));
    let rT;
    window.addEventListener('resize', () => {
      clearTimeout(rT);
      rT = setTimeout(() => placeActive(true), 80);
    });

    nav.addEventListener('mouseover', (e) => {
      const item = e.target.closest('.nav-item');
      if (!item || !nav.contains(item)) return;
      if (!nav.classList.contains('is-hovering')) snap(ghost, item);
      else place(item, ghost);
      nav.classList.add('is-hovering');
    });
    nav.addEventListener('mouseleave', () => nav.classList.remove('is-hovering'));

    nav.addEventListener('click', (e) => {
      const item = e.target.closest('.nav-item');
      if (!item || isModified(e)) return;
      if (current) current.classList.remove('active');
      item.classList.add('active');
      current = item;
      placeActive(false);
    });
  }

  /* ---------- Ripple ---------- */
  document.addEventListener('pointerdown', (e) => {
    const el = e.target.closest('.nav-item, .mobile-nav-item');
    if (!el || reduceMotion) return;
    const r = el.getBoundingClientRect();
    const dot = document.createElement('span');
    dot.className = 'nav-ripple';
    dot.style.left = (e.clientX - r.left) + 'px';
    dot.style.top = (e.clientY - r.top) + 'px';
    el.appendChild(dot);
    dot.addEventListener('animationend', () => dot.remove(), { once: true });
  });

  /* ---------- Page-load progress on internal navigation ---------- */
  function isModified(e) {
    return e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0;
  }

  header.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a || isModified(e) || a.target === '_blank') return;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
    if (progress) {
      progress.classList.remove('is-loading');
      void progress.offsetWidth;
      progress.classList.add('is-loading');
    }
  });

  // Returning via back/forward cache: reset transient state
  window.addEventListener('pageshow', (e) => {
    if (e.persisted && progress) progress.classList.remove('is-loading');
    closeMenu();
  });

  /* ---------- Scroll state ---------- */
  let ticking = false;
  const onScroll = () => {
    header.classList.toggle('is-scrolled', window.scrollY > 8);
    ticking = false;
  };
  window.addEventListener('scroll', () => {
    if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
  }, { passive: true });
  onScroll();

  /* ---------- Mouse-follow glow ---------- */
  if (!reduceMotion) {
    header.addEventListener('mousemove', (e) => {
      const r = header.getBoundingClientRect();
      header.style.setProperty('--mx', (e.clientX - r.left) + 'px');
    });
  }

  /* ---------- Mobile drawer ---------- */
  function openMenu() {
    header.classList.add('menu-open');
    document.body.classList.add('nav-menu-open');
    if (burger) { burger.setAttribute('aria-expanded', 'true'); burger.setAttribute('aria-label', 'Đóng menu'); }
  }
  function closeMenu() {
    header.classList.remove('menu-open');
    document.body.classList.remove('nav-menu-open');
    if (burger) { burger.setAttribute('aria-expanded', 'false'); burger.setAttribute('aria-label', 'Mở menu'); }
  }
  if (burger) {
    burger.addEventListener('click', () => {
      header.classList.contains('menu-open') ? closeMenu() : openMenu();
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeMenu);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
  window.addEventListener('resize', () => { if (window.innerWidth > 992) closeMenu(); });

  /* ---------- Live notification badge ---------- */
  const badge = document.getElementById('notifBadge');
  const bell = badge && badge.closest('.bell-btn');
  if (badge && bell && 'MutationObserver' in window) {
    let last = badge.textContent;
    new MutationObserver(() => {
      bell.classList.toggle('has-unread', !badge.hidden);
      const now = badge.textContent;
      if (now === last) return;
      const grew = (parseInt(now, 10) || 0) > (parseInt(last, 10) || 0);
      last = now;
      if (badge.hidden) return;
      badge.classList.remove('bump');
      void badge.offsetWidth;
      badge.classList.add('bump');
      if (grew) {
        bell.classList.remove('ring');
        void bell.offsetWidth;
        bell.classList.add('ring');
        setTimeout(() => bell.classList.remove('ring'), 950);
      }
    }).observe(badge, { childList: true, characterData: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
  }
  }

  window.CF_initNavbar = initNavbar;
  initNavbar();
})();
