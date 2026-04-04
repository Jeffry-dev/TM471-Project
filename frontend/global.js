// global.js

// Configure API base in one place.
let savedApiBase = null;
try {
  savedApiBase = localStorage.getItem('api_base_url');
} catch {
  savedApiBase = null;
}

function isPublicRestaurantPage() {
  const path = (location.pathname.split('/').pop() || '').toLowerCase();
  return ['home.html', 'menu.html', 'about.html', 'contact.html', ''].includes(path);
}

async function pingVisitorSession() {
  try {
    await fetch(`${window.APP_CONFIG.API_BASE_URL}/visitor/ping`, {
      method: 'GET',
      credentials: 'include',
      cache: 'no-store',
    });
  } catch (err) {
    console.warn(err);
  }
}

function formatTimeLabel(iso) {
  try {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  } catch {
    return '';
  }
}

function readChatHistory() {
  try {
    const raw = sessionStorage.getItem(CHAT_STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function saveChatHistory(history) {
  try {
    sessionStorage.setItem(CHAT_STORAGE_KEY, JSON.stringify(history.slice(-20)));
  } catch {
    // ignore
  }
}

function buildChatWidget() {
  const root = document.createElement('section');
  root.className = 'cedars-chat';
  root.id = 'cedars-chat-widget';
  root.innerHTML = `
    <button type="button" class="cedars-chat__fab" id="cedars-chat-toggle" aria-expanded="false" aria-controls="cedars-chat-panel">
      <span class="cedars-chat__fab-label">AI Assistant</span>
    </button>
    <div class="cedars-chat__panel hidden" id="cedars-chat-panel" role="dialog" aria-label="Cedars of Lebanon AI Assistant">
      <header class="cedars-chat__header">
        <div>
          <p class="cedars-chat__title">Cedars Assistant</p>
          <p class="cedars-chat__subtitle">Menu, allergens, dietary, hours, location</p>
        </div>
        <button type="button" class="cedars-chat__close" id="cedars-chat-close" aria-label="Close chat">×</button>
      </header>
      <div class="cedars-chat__messages" id="cedars-chat-messages"></div>
      <form class="cedars-chat__composer" id="cedars-chat-form">
        <input
          id="cedars-chat-input"
          type="text"
          maxlength="1000"
          placeholder="Ask about menu, allergens, dietary options..."
          required
        />
        <button type="submit">Send</button>
      </form>
    </div>
  `;

  document.body.appendChild(root);

  const toggle = document.getElementById('cedars-chat-toggle');
  const panel = document.getElementById('cedars-chat-panel');
  const closeBtn = document.getElementById('cedars-chat-close');
  const form = document.getElementById('cedars-chat-form');
  const input = document.getElementById('cedars-chat-input');
  const messages = document.getElementById('cedars-chat-messages');

  let history = readChatHistory();
  let isWaiting = false;

  const addMessage = (role, content, createdAt = new Date().toISOString()) => {
    const article = document.createElement('article');
    article.className = `cedars-chat__msg cedars-chat__msg--${role}`;
    article.innerHTML = `
      <p class="cedars-chat__msg-text"></p>
      <time class="cedars-chat__msg-time">${formatTimeLabel(createdAt)}</time>
    `;
    article.querySelector('.cedars-chat__msg-text').textContent = content;
    messages.appendChild(article);
    messages.scrollTop = messages.scrollHeight;
  };

  const renderHistory = () => {
    messages.innerHTML = '';
    if (history.length === 0) {
      addMessage('assistant', 'Hello! Ask me about Cedars of Lebanon menu items, ingredients, allergens, dietary options, opening hours, or location.');
      return;
    }
    history.forEach((entry) => addMessage(entry.role, entry.content, entry.createdAt));
  };

  const setTyping = (enabled) => {
    const id = 'cedars-chat-typing';
    const existing = document.getElementById(id);
    if (!enabled) {
      if (existing) existing.remove();
      return;
    }
    if (existing) return;
    const article = document.createElement('article');
    article.id = id;
    article.className = 'cedars-chat__msg cedars-chat__msg--assistant';
    article.innerHTML = '<p class="cedars-chat__msg-text">Typing...</p>';
    messages.appendChild(article);
    messages.scrollTop = messages.scrollHeight;
  };

  const pushAndPersist = (role, content) => {
    const entry = { role, content, createdAt: new Date().toISOString() };
    history.push(entry);
    saveChatHistory(history);
    addMessage(role, content, entry.createdAt);
  };

  if (toggle && panel) {
    toggle.addEventListener('click', () => {
      const open = panel.classList.contains('hidden');
      panel.classList.toggle('hidden', !open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open && input) input.focus();
    });
  }
  if (closeBtn && panel && toggle) {
    closeBtn.addEventListener('click', () => {
      panel.classList.add('hidden');
      toggle.setAttribute('aria-expanded', 'false');
    });
  }

  if (form && input) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (isWaiting) return;

      const message = String(input.value || '').trim();
      if (!message) return;

      isWaiting = true;
      input.value = '';
      pushAndPersist('user', message);
      setTyping(true);

      try {
        const payloadHistory = history
          .slice(-12)
          .map((entry) => ({ role: entry.role, content: entry.content }));

        const response = await fetch(`${window.APP_CONFIG.API_BASE_URL}/ai/chat`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            message,
            history: payloadHistory,
          }),
        });

        const body = await response.json().catch(() => ({}));
        const reply = String(body.reply || '').trim() || 'Sorry, I’m having trouble right now. Please try again in a moment.';
        setTyping(false);
        pushAndPersist('assistant', reply);
      } catch (err) {
        console.warn(err);
        setTyping(false);
        pushAndPersist('assistant', 'Sorry, I’m having trouble right now. Please try again in a moment.');
      } finally {
        isWaiting = false;
      }
    });
  }

  renderHistory();
}

window.APP_CONFIG = window.APP_CONFIG || {
  API_BASE_URL: savedApiBase || 'http://localhost:8000',
};

// Fallback HTML so navigation/footer still show even when running pages via file://
// (fetch() often fails in that case).
const FALLBACK_NAV_HTML = `
<header id="motion-header" class="sticky top-0 z-20 border-b border-subtle transition-all duration-500 bg-transparent">
  <nav id="motion-nav" class="mx-auto flex max-w-6xl items-center justify-between px-4 transition-all duration-500 py-4">
    <a href="home.html" class="text-lg font-semibold tracking-tight text-[var(--text-main)]" aria-label="Home">
      <span class="rounded-full bg-[var(--accent)] px-2 py-0.5 text-xs font-bold uppercase tracking-[0.2em] text-[var(--bg)] mr-2">🌲</span>
      Cedars of Lebanon
    </a>
    <div class="flex items-center gap-3 text-sm font-medium text-muted-soft">
      <a class="nav-item" data-nav-link href="home.html">Home</a>
      <a class="nav-item" data-nav-link href="menu.html">Menu</a>
      <a class="nav-item" data-nav-link href="about.html">About</a>
      <a class="nav-item" data-nav-link href="contact.html">Contact</a>
      <span id="auth-slot"></span>
    </div>
  </nav>
</header>
`;

const FALLBACK_FOOTER_HTML = `
<footer class="border-t border-subtle surface-subtle backdrop-blur-sm">
  <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-muted-soft">
    <span>© <span id="year"></span> Cedars of Lebanon. All rights reserved.</span>
    <span class="flex items-center gap-1">
      <span class="h-1 w-1 rounded-full bg-[var(--accent-secondary)]"></span>
      <span>Crafted with Laravel & SQLite.</span>
    </span>
  </div>
</footer>
`;

function setFooterYear() {
  const el = document.getElementById('year');
  if (el) el.textContent = String(new Date().getFullYear());
}

async function injectPartial(targetId, url, fallbackHtml = '') {
  const el = document.getElementById(targetId);
  if (!el) return;

  try {
    const res = await fetch(url, { cache: 'no-cache' });
    if (!res.ok) throw new Error(`Failed to load ${url}: ${res.status}`);
    el.innerHTML = await res.text();
  } catch (err) {
    console.warn(err);
    if (fallbackHtml) el.innerHTML = fallbackHtml;
  }
}

function setActiveNavLink() {
  const path = (location.pathname.split('/').pop() || '').toLowerCase();

  document.querySelectorAll('[data-nav-link]').forEach((a) => {
    const href = (a.getAttribute('href') || '').toLowerCase();
    const isHome = href === 'home.html' && (path === '' || path === 'home.html');
    const active = isHome || href === path;
    a.classList.toggle('nav-item--active', active);
  });
}

function bindHeaderScroll() {
  const header = document.getElementById('motion-header');
  const nav = document.getElementById('motion-nav');
  if (!header || !nav) return;

  const onScroll = () => {
    const scrolled = window.scrollY > 50;

    header.classList.toggle('surface-subtle', scrolled);
    header.classList.toggle('backdrop-blur-md', scrolled);
    header.classList.toggle('bg-transparent', !scrolled);

    nav.classList.toggle('py-3', scrolled);
    nav.classList.toggle('py-4', !scrolled);
  };

  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
}

function base64UrlDecode(input) {
  const padded = input.replace(/-/g, '+').replace(/_/g, '/');
  const padLength = (4 - (padded.length % 4)) % 4;
  const withPadding = padded + '='.repeat(padLength);
  return atob(withPadding);
}

function decodeJwtPayload(token) {
  const parts = String(token || '').split('.');
  if (parts.length < 2) return null;

  try {
    const json = base64UrlDecode(parts[1]);
    return JSON.parse(json);
  } catch {
    return null;
  }
}

function isJwtExpired(token, skewSeconds = 30) {
  const payload = decodeJwtPayload(token);
  if (!payload || !payload.exp) return false;
  const nowSeconds = Math.floor(Date.now() / 1000);
  return payload.exp <= nowSeconds + skewSeconds;
}

function renderAuthLinks() {
  const slot = document.getElementById('auth-slot');
  if (!slot) return;

  const TOKEN_KEY = 'restaurant_jwt';
  const token = localStorage.getItem(TOKEN_KEY);
  const hasToken = Boolean(token && !isJwtExpired(token));

  if (!hasToken) {
    slot.innerHTML = `<a class="nav-item" data-nav-link href="admin-login.html">Login</a>`;
    return;
  }

  // Logged in => show profile icon + actions (same behavior as before).
  slot.innerHTML = `
    <div class="relative" id="auth-menu">
      <button
        type="button"
        class="nav-item"
        id="auth-toggle"
        aria-haspopup="menu"
        aria-expanded="false"
        aria-label="Account menu"
      >
        <span aria-hidden class="text-base">👤</span>
      </button>

      <div
        role="menu"
        id="auth-dropdown"
        class="nav-menu absolute right-0 mt-2 w-48 overflow-hidden rounded-xl border border-subtle bg-[var(--bg-elevated)] shadow-2xl hidden"
      >
        <a href="admin.html" role="menuitem" class="nav-menu-item">
          <span aria-hidden>📊</span><span>Dashboard</span>
        </a>
        <a href="admin-profile.html" role="menuitem" class="nav-menu-item">
          <span aria-hidden>✏️</span><span>Edit profile</span>
        </a>
        <div class="my-1 h-px w-full bg-[var(--border-subtle)]"></div>
        <button type="button" role="menuitem" id="auth-logout" class="nav-menu-item nav-menu-item--danger">
          <span aria-hidden>🚪</span><span>Logout</span>
        </button>
      </div>
    </div>
  `;

  const toggle = document.getElementById('auth-toggle');
  const dropdown = document.getElementById('auth-dropdown');
  const logout = document.getElementById('auth-logout');

  const close = () => {
    if (!dropdown || !toggle) return;
    dropdown.classList.add('hidden');
    toggle.setAttribute('aria-expanded', 'false');
  };

  const open = () => {
    if (!dropdown || !toggle) return;
    dropdown.classList.remove('hidden');
    toggle.setAttribute('aria-expanded', 'true');
  };

  if (toggle && dropdown) {
    toggle.addEventListener('click', () => {
      if (dropdown.classList.contains('hidden')) open();
      else close();
    });

    window.addEventListener('pointerdown', (e) => {
      const menu = document.getElementById('auth-menu');
      if (!menu) return;
      if (!menu.contains(e.target)) close();
    });
  }

  if (logout) {
    logout.addEventListener('click', () => {
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem('restaurant_profile_cache');
      close();
      navigateWithTransition('home.html');
    });
  }
}

// Cache-busting / versioning for partial fetches.
const ASSET_VERSION = '20260131-1';

// The original Next app includes a PageCurtain component, but in practice the UX you want
// for this static build is the smooth blur/scale transition without a large teal overlay.
// Leave this disabled unless you explicitly want the curtain effect.
const ENABLE_PAGE_CURTAIN = false;

function ensureCurtain() {
  if (!ENABLE_PAGE_CURTAIN) return;
  if (!document.body) return;
  if (document.getElementById('page-curtain')) return;
  const el = document.createElement('div');
  el.id = 'page-curtain';
  el.innerHTML = '<div class="curtain left"></div><div class="curtain right"></div>';
  document.body.appendChild(el);
}

function showCurtain() {
  if (!ENABLE_PAGE_CURTAIN) return;

  // Curtains are hidden by default and only animate IN (close) during navigation.
  ensureCurtain();
  const el = document.getElementById('page-curtain');
  if (!el) return;

  el.classList.add('is-active', 'is-closing');
}

const PAGE_TRANSITION_KEY = '__page_transition__';
const CHAT_STORAGE_KEY = 'cedars_ai_chat_history_v1';

function navigateWithTransition(href, delayMs = 300) {
  // PageTransition exit (approximation) + PageCurtain.
  try {
    sessionStorage.setItem(PAGE_TRANSITION_KEY, '1');
  } catch {
    // ignore
  }

  document.body.classList.add('page-exit');
  showCurtain();

  window.setTimeout(() => {
    window.location.href = href;
  }, delayMs);
}

function bindPageTransitions() {
  ensureCurtain();

  document.addEventListener('click', (e) => {
    const a = e.target && e.target.closest ? e.target.closest('a') : null;
    if (!a) return;

    const href = a.getAttribute('href') || '';
    const target = a.getAttribute('target');

    // Only intercept internal .html navigations.
    const isHtml = href.endsWith('.html');
    const isExternal = href.startsWith('http://') || href.startsWith('https://') || href.startsWith('mailto:');
    if (!isHtml || isExternal || target === '_blank') return;

    e.preventDefault();

    navigateWithTransition(href, 470);
  });
}

function bindPageEnterTransition() {
  // PageTransition enter (approximation).
  // IMPORTANT: start the transition synchronously to avoid "stuck blurred" states
  // when other scripts are busy (menu rendering, image decode, etc.).
  document.body.classList.add('page-enter');

  // Force reflow so the browser commits the initial state before enabling transitions.
  void document.body.offsetHeight;

  document.body.classList.add('page-enter-active');

  // Remove the pre-paint helper class once the transition is running.
  document.documentElement.classList.remove('page-transition-enter');

  window.setTimeout(() => {
    document.body.classList.remove('page-enter', 'page-enter-active');
    document.documentElement.classList.remove('page-transition-enter');
  }, 420);
}

function bindRevealOnScroll() {
  const nodes = Array.from(document.querySelectorAll('.reveal'));
  if (nodes.length === 0) return;

  if (!('IntersectionObserver' in window)) {
    nodes.forEach((el) => el.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.15 },
  );

  nodes.forEach((el) => {
    if (el.dataset.revealBound === '1') return;
    el.dataset.revealBound = '1';
    observer.observe(el);
  });
}

async function injectSharedLayout() {
  await Promise.all([
    injectPartial('navbar', `partials/navbar.html?v=${ASSET_VERSION}`, FALLBACK_NAV_HTML),
    injectPartial('footer', `partials/footer.html?v=${ASSET_VERSION}`, FALLBACK_FOOTER_HTML),
  ]);

  // After navbar/footer loads
  renderAuthLinks();
  setActiveNavLink();
  bindHeaderScroll();
  setFooterYear();
}

// Expose for pages that render content dynamically
window.setActiveNavLink = setActiveNavLink;
window.bindRevealOnScroll = bindRevealOnScroll;
window.navigateWithTransition = navigateWithTransition;

function initAppShell() {
  document.body.classList.add('antialiased', 'app-shell');

  // Head inline script sets this class before first paint.
  const shouldAnimateEnter = document.documentElement.classList.contains('page-transition-enter');

  // Best-effort cleanup (in case the page was opened without our JS).
  try {
    sessionStorage.removeItem(PAGE_TRANSITION_KEY);
  } catch {
    // ignore
  }

  if (shouldAnimateEnter) {
    bindPageEnterTransition();
  }

  injectSharedLayout();
  bindPageTransitions();
  bindRevealOnScroll();
  if (isPublicRestaurantPage()) {
    void pingVisitorSession();
    buildChatWidget();
  }
}

// If this script is loaded at the end of <body>, DOM may already be ready.
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAppShell);
} else {
  initAppShell();
}
