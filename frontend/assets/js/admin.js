// admin.js

const API_BASE_URL = (window.APP_CONFIG && window.APP_CONFIG.API_BASE_URL) || 'http://localhost:8000';
const TOKEN_KEY = 'restaurant_jwt';
const PROFILE_CACHE_KEY = 'restaurant_profile_cache';

const CATEGORY_ORDER = [
  'Mezze',
  'Salads',
  'Grills',
  'Wraps & Sandwiches',
  'Main Dishes',
  'Desserts',
  'Beverages',
];

let state = {
  items: [],
  messages: [],
  visitors: [],
  chatLogs: [],
  profile: null,
  editingId: null,
  selectedCategory: 'all',
  deleteId: null,
};

function normalizeListInput(value) {
  if (Array.isArray(value)) {
    return value.map((item) => String(item || '').trim()).filter(Boolean);
  }
  return String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

function requireAuthOrRedirect() {
  const token = getToken();
  if (!token) {
    window.location.href = 'admin-login.html';
    return null;
  }
  return token;
}

async function authFetch(url, options) {
  const token = requireAuthOrRedirect();
  if (!token) return null;

  const res = await fetch(url, {
    ...options,
    headers: {
      ...(options && options.headers ? options.headers : {}),
      Authorization: `Bearer ${token}`,
    },
  });

  if (res.status === 401 || res.status === 403) {
    localStorage.removeItem(TOKEN_KEY);
    window.location.href = 'admin-login.html';
    return null;
  }

  return res;
}

function bannerBox(type, text) {
  const box = document.createElement('div');
  if (type === 'error') {
    box.className = 'rounded-md border border-red-500/40 bg-red-500/5 px-3 py-2 text-xs text-red-500';
  } else {
    box.className = 'rounded-md border border-emerald-500/40 bg-emerald-500/5 px-3 py-2 text-xs text-emerald-400';
  }
  box.textContent = text;
  return box;
}

function setBanners({ error = null, info = null } = {}) {
  const host = document.getElementById('admin-banners');
  if (!host) return;
  host.innerHTML = '';

  if (error) host.appendChild(bannerBox('error', error));
  if (info && !error) host.appendChild(bannerBox('info', info));
}

function formatSince(createdAt) {
  try {
    return new Date(createdAt).toLocaleDateString();
  } catch {
    return '';
  }
}

function formatDateTime(value) {
  try {
    return new Date(value).toLocaleString();
  } catch {
    return '';
  }
}

function escapeHtml(value) {
  return String(value == null ? '' : value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function categoryOrderIndex(cat) {
  const idx = CATEGORY_ORDER.indexOf(cat);
  return idx === -1 ? 999 : idx;
}

function getFilteredSortedItems() {
  const selected = state.selectedCategory;
  return [...state.items]
    .filter((item) => selected === 'all' || item.category === selected)
    .sort((a, b) => {
      const ia = categoryOrderIndex(a.category);
      const ib = categoryOrderIndex(b.category);
      if (ia !== ib) return ia - ib;
      return String(a.name || '').localeCompare(String(b.name || ''));
    });
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = text || '';
}

function setCountBadge(id, count) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = count || 0;
}

function showAdminPanel(panelId) {
  document.querySelectorAll('.admin-panel').forEach((p) => p.classList.add('hidden'));
  document.querySelectorAll('.admin-nav-item').forEach((b) => b.classList.remove('admin-nav-item--active'));

  const panel = document.getElementById(panelId);
  if (panel) panel.classList.remove('hidden');

  const btn = document.querySelector(`.admin-nav-item[data-panel="${panelId}"]`);
  if (btn) btn.classList.add('admin-nav-item--active');
}

function setHidden(id, hidden) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.toggle('hidden', Boolean(hidden));
}

function readCachedProfile() {
  try {
    const raw = localStorage.getItem(PROFILE_CACHE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : null;
  } catch {
    return null;
  }
}

function writeCachedProfile(profile) {
  try {
    if (!profile) return;
    localStorage.setItem(PROFILE_CACHE_KEY, JSON.stringify(profile));
  } catch {
    // ignore
  }
}

function renderProfileCard() {
  const profile = state.profile;
  const card = document.getElementById('admin-profile-card');
  if (!card) return;

  // Always show the box immediately (prevents the "appears after 2s" flicker).
  card.classList.remove('hidden');

  // This card starts as `display:none` and also has `.reveal`; make it visible immediately
  // instead of waiting on IntersectionObserver timing.
  card.classList.add('is-visible');

  if (!profile) {
    // Skeleton state
    const avatar = document.getElementById('admin-profile-avatar');
    if (avatar) avatar.textContent = '…';

    setText('admin-profile-name', 'Loading…');
    setText('admin-profile-email', '');
    setText('admin-profile-role-since', '');

    const bioEl = document.getElementById('admin-profile-bio');
    if (bioEl) {
      bioEl.textContent = '';
      bioEl.classList.add('hidden');
    }

    return;
  }

  const avatar = document.getElementById('admin-profile-avatar');
  if (avatar) {
    const initial = String(profile.name || profile.email || '?').charAt(0).toUpperCase() || '?';
    avatar.textContent = initial;
  }

  setText('admin-profile-name', profile.name || 'Admin');
  setText('admin-profile-email', profile.email || '');
  setText(
    'admin-profile-role-since',
    profile.role && profile.createdAt ? `${profile.role} • since ${formatSince(profile.createdAt)}` : '',
  );

  const bioEl = document.getElementById('admin-profile-bio');
  if (bioEl) {
    if (profile.bio) {
      bioEl.textContent = profile.bio;
      bioEl.classList.remove('hidden');
    } else {
      bioEl.textContent = '';
      bioEl.classList.add('hidden');
    }
  }
}

function renderCategoryFilters() {
  const host = document.getElementById('admin-category-filters');
  if (!host) return;

  host.innerHTML = '';

  const allBtn = document.createElement('button');
  allBtn.type = 'button';
  allBtn.className = `category-pill ${state.selectedCategory === 'all' ? 'category-pill--active' : ''}`;
  allBtn.textContent = `All (${state.items.length})`;
  allBtn.addEventListener('click', () => {
    state.selectedCategory = 'all';
    rerenderItemsOnly();
  });
  host.appendChild(allBtn);

  CATEGORY_ORDER.forEach((cat) => {
    const count = state.items.filter((i) => i.category === cat).length;
    if (count === 0) return;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `category-pill ${state.selectedCategory === cat ? 'category-pill--active' : ''}`;
    btn.textContent = `${cat} (${count})`;
    btn.addEventListener('click', () => {
      state.selectedCategory = cat;
      rerenderItemsOnly();
    });
    host.appendChild(btn);
  });
}

function renderItemsList() {
  const host = document.getElementById('admin-items');
  if (!host) return;

  host.innerHTML = '';

  if (!state.items || state.items.length === 0) {
    const p = document.createElement('p');
    p.className = 'text-xs text-muted-soft';
    p.textContent = 'No items yet. Add your first dish using the form above.';
    host.appendChild(p);
    return;
  }

  const list = document.createElement('div');
  list.className = 'mt-3 flex flex-col gap-3';

  getFilteredSortedItems().forEach((item, index) => {
    const article = document.createElement('article');
    article.className =
      'surface-card flex flex-col gap-3 rounded-2xl border border-subtle p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between';

    const left = document.createElement('div');

    const head = document.createElement('div');
    head.className = 'flex items-center gap-3';

    const badge = document.createElement('span');
    badge.className =
      'inline-flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-emerald-700 text-xs font-semibold text-white';
    badge.textContent = String(item.category || '?').charAt(0).toUpperCase() || '?';

    const meta = document.createElement('div');

    const name = document.createElement('p');
    name.className = 'text-sm font-medium text-[var(--text-main)]';
    name.textContent = item.name || '';

    const subtitle = document.createElement('p');
    subtitle.className = 'text-xs text-muted-soft';
    subtitle.textContent = `${item.category || 'Other'} • $${Number(item.price).toFixed(2)}`;

    meta.appendChild(name);
    meta.appendChild(subtitle);

    head.appendChild(badge);
    head.appendChild(meta);
    left.appendChild(head);

    if (item.description) {
      const desc = document.createElement('p');
      desc.className = 'mt-2 max-w-xl text-xs text-muted-soft';
      desc.textContent = item.description;
      left.appendChild(desc);
    }
    const extraMetaParts = [];
    if (Array.isArray(item.ingredients) && item.ingredients.length > 0) {
      extraMetaParts.push(`Ingredients: ${item.ingredients.join(', ')}`);
    }
    if (Array.isArray(item.allergens) && item.allergens.length > 0) {
      extraMetaParts.push(`Allergens: ${item.allergens.join(', ')}`);
    }
    if (Array.isArray(item.dietaryTags) && item.dietaryTags.length > 0) {
      extraMetaParts.push(`Dietary: ${item.dietaryTags.join(', ')}`);
    }
    if (extraMetaParts.length > 0) {
      const extra = document.createElement('p');
      extra.className = 'mt-2 max-w-xl text-[11px] text-muted-soft';
      extra.textContent = extraMetaParts.join(' • ');
      left.appendChild(extra);
    }

    const actions = document.createElement('div');
    actions.className = 'flex items-center gap-2 self-end sm:self-auto flex-wrap';

    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = `btn btn-xs btn-pill ${item.isAvailable ? 'btn-outline' : 'btn-primary'}`;
    toggleBtn.textContent = item.isAvailable ? 'Mark as unavailable' : 'Mark as available';
    toggleBtn.addEventListener('click', () => toggleAvailable(item));

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'btn btn-xs btn-pill btn-outline';
    editBtn.textContent = 'Edit';
    editBtn.addEventListener('click', () => startEdit(item));

    const delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.className = 'btn btn-xs btn-pill bg-red-500/80 text-white hover:bg-red-500';
    delBtn.textContent = 'Delete';
    delBtn.addEventListener('click', () => openDeleteModal(item.id));

    actions.appendChild(toggleBtn);
    actions.appendChild(editBtn);
    actions.appendChild(delBtn);

    article.appendChild(left);
    article.appendChild(actions);

    article.classList.add('reveal');

     list.appendChild(article);
  });

  host.appendChild(list);

  if (typeof window.bindRevealOnScroll === 'function') {
    window.bindRevealOnScroll();
  }
}

function renderMessagesList() {
  const host = document.getElementById('admin-messages');
  if (!host) return;

  host.innerHTML = '';

  if (!state.messages || state.messages.length === 0) {
    const p = document.createElement('p');
    p.className = 'text-xs text-muted-soft';
    p.textContent = 'No contact messages yet.';
    host.appendChild(p);
    return;
  }

  const list = document.createElement('div');
  list.className = 'mt-3 flex flex-col gap-3';

  state.messages.forEach((message) => {
    const card = document.createElement('article');
    card.className = 'rounded-2xl border border-subtle bg-[var(--bg-elevated)] p-4 shadow-sm';

    const top = document.createElement('div');
    top.className = 'flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between';

    const identity = document.createElement('p');
    identity.className = 'text-sm font-semibold text-[var(--text-main)]';
    const preferred = message.preferredContact === 'phone' ? 'Phone' : 'Email';
    const preferredValue = message.preferredContact === 'phone'
      ? (message.phone || 'No phone')
      : (message.email || 'No email');
    identity.textContent = `${message.name || 'Unknown'} • ${preferred}: ${preferredValue}`;

    const meta = document.createElement('p');
    meta.className = 'text-xs text-muted-soft';
    meta.textContent = formatDateTime(message.createdAt);

    top.appendChild(identity);
    top.appendChild(meta);
    card.appendChild(top);

    const contactInfo = document.createElement('p');
    contactInfo.className = 'mt-2 text-xs text-muted-soft';
    contactInfo.textContent = `Email: ${message.email || 'N/A'} • Phone: ${message.phone || 'N/A'} • Preferred: ${preferred}`;
    card.appendChild(contactInfo);

    const subject = document.createElement('p');
    subject.className = 'mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-muted-soft';
    subject.textContent = `Subject: ${message.subject || '(No subject)'}`;
    card.appendChild(subject);

    const body = document.createElement('p');
    body.className = 'mt-2 whitespace-pre-wrap text-sm text-muted-soft';
    body.textContent = message.message || '';
    card.appendChild(body);

    list.appendChild(card);
  });

  host.appendChild(list);
}

function renderVisitorsTable() {
  const host = document.getElementById('admin-visitors');
  if (!host) return;

  if (!state.visitors || state.visitors.length === 0) {
    host.innerHTML = '<p class="text-xs text-muted-soft">No visitors tracked yet.</p>';
    return;
  }

  const rows = state.visitors.map((v) => `
    <tr class="border-b border-subtle">
      <td class="px-3 py-2">${escapeHtml(v.ipAddress || '')}</td>
      <td class="px-3 py-2 break-all">${escapeHtml(v.userAgent || '')}</td>
      <td class="px-3 py-2">${escapeHtml(formatDateTime(v.visitedAt))}</td>
    </tr>
  `).join('');

  host.innerHTML = `
    <div class="overflow-x-auto rounded-xl border border-subtle">
      <table class="min-w-full text-xs">
        <thead class="bg-[var(--bg-elevated)] text-muted-soft uppercase tracking-[0.12em]">
          <tr>
            <th class="px-3 py-2 text-left">ip_address</th>
            <th class="px-3 py-2 text-left">user_agent</th>
            <th class="px-3 py-2 text-left">visited_at</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
  `;
}

function renderChatLogsTable() {
  const host = document.getElementById('admin-chat-logs');
  if (!host) return;

  if (!state.chatLogs || state.chatLogs.length === 0) {
    host.innerHTML = '<p class="text-xs text-muted-soft">No chat logs yet.</p>';
    return;
  }

  const rows = state.chatLogs.map((log) => `
    <tr class="border-b border-subtle">
      <td class="px-3 py-2">${escapeHtml(log.question || '')}</td>
      <td class="px-3 py-2">${escapeHtml(log.response || '')}</td>
      <td class="px-3 py-2">${escapeHtml(log.visitorIp || (log.visitorId ?? ''))}</td>
      <td class="px-3 py-2">${escapeHtml(formatDateTime(log.createdAt))}</td>
    </tr>
  `).join('');

  host.innerHTML = `
    <div class="overflow-x-auto rounded-xl border border-subtle">
      <table class="min-w-full text-xs">
        <thead class="bg-[var(--bg-elevated)] text-muted-soft uppercase tracking-[0.12em]">
          <tr>
            <th class="px-3 py-2 text-left">question</th>
            <th class="px-3 py-2 text-left">response</th>
            <th class="px-3 py-2 text-left">visitor_id / visitor_ip</th>
            <th class="px-3 py-2 text-left">created_at</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
  `;
}

function setFormMode(editing) {
  const title = document.getElementById('admin-form-title');
  const sub = document.getElementById('admin-form-sub');
  const submit = document.getElementById('admin-form-submit');
  const cancel = document.getElementById('admin-form-cancel');

  if (title) title.textContent = editing ? 'Edit menu item' : 'Add new menu item';
  if (sub) {
    sub.textContent = editing
      ? 'Update the details below to modify this dish.'
      : 'Fill in the details below to add a new dish to the menu.';
  }
  if (submit) submit.textContent = editing ? 'Update item' : 'Add item';
  if (cancel) cancel.classList.toggle('hidden', !editing);
}

function clearForm() {
  const form = document.getElementById('admin-item-form');
  if (!form) return;
  form.reset();
  const id = form.querySelector('input[name="id"]');
  if (id) id.value = '';

  // default available = true
  const isAvailable = form.querySelector('input[name="isAvailable"]');
  if (isAvailable) isAvailable.checked = true;

  state.editingId = null;
  setFormMode(false);
}

function startEdit(item) {
  const form = document.getElementById('admin-item-form');
  if (!form) return;

  state.editingId = item.id;
  setFormMode(true);

  const set = (name, value) => {
    const el = form.querySelector(`[name="${name}"]`);
    if (!el) return;
    if (el.type === 'checkbox') el.checked = Boolean(value);
    else el.value = value == null ? '' : String(value);
  };

  set('id', item.id);
  set('name', item.name || '');
  set('category', item.category || '');
  set('description', item.description || '');
  set('ingredients', Array.isArray(item.ingredients) ? item.ingredients.join(', ') : '');
  set('allergens', Array.isArray(item.allergens) ? item.allergens.join(', ') : '');
  set('dietaryTags', Array.isArray(item.dietaryTags) ? item.dietaryTags.join(', ') : '');
  set('price', item.price);
  set('imageUrl', item.imageUrl || '');
  set('isAvailable', item.isAvailable !== false);

  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function openDeleteModal(id) {
  state.deleteId = id;
  const modal = document.getElementById('delete-modal');
  if (modal) modal.classList.remove('hidden');
}

function closeDeleteModal() {
  state.deleteId = null;
  const modal = document.getElementById('delete-modal');
  if (modal) modal.classList.add('hidden');
}

async function fetchMenuItems() {
  const res = await fetch(`${API_BASE_URL}/menu`, { cache: 'no-store' });
  if (!res.ok) throw new Error('Failed to fetch menu items');
  const data = await res.json();
  return Array.isArray(data) ? data : [];
}

async function fetchProfile() {
  const res = await authFetch(`${API_BASE_URL}/auth/me`, { method: 'GET' });
  if (!res) return null;
  if (!res.ok) throw new Error('Failed to fetch profile');
  const profile = await res.json();
  writeCachedProfile(profile);
  return profile;
}

async function fetchContactMessages() {
  const res = await authFetch(`${API_BASE_URL}/contact-messages`, { method: 'GET' });
  if (!res) return [];
  if (!res.ok) throw new Error('Failed to fetch contact messages');

  const data = await res.json();
  if (Array.isArray(data)) return data;
  if (data && Array.isArray(data.data)) return data.data;
  return [];
}

async function fetchVisitors() {
  const res = await authFetch(`${API_BASE_URL}/visitors`, { method: 'GET' });
  if (!res) return [];
  if (!res.ok) throw new Error('Failed to fetch visitors');
  const data = await res.json();
  return Array.isArray(data) ? data : [];
}

async function fetchChatLogs() {
  const res = await authFetch(`${API_BASE_URL}/chat-logs`, { method: 'GET' });
  if (!res) return [];
  if (!res.ok) throw new Error('Failed to fetch chat logs');
  const data = await res.json();
  return Array.isArray(data) ? data : [];
}

async function saveItem({ id, body }) {
  const isEditing = id != null;
  const url = isEditing ? `${API_BASE_URL}/menu/${id}` : `${API_BASE_URL}/menu`;
  const method = isEditing ? 'PATCH' : 'POST';

  const res = await authFetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });

  if (!res) return;

  const text = await res.text();
  if (!res.ok) throw new Error(text || 'Request failed');
}

async function toggleAvailable(item) {
  try {
    setBanners({ info: 'Saving…' });
    await saveItem({ id: item.id, body: { isAvailable: !item.isAvailable } });
    await reload();
    setBanners({ info: 'Item updated.' });
  } catch (err) {
    console.warn(err);
    setBanners({ error: err && err.message ? err.message : 'Failed to update item' });
  }
}

async function confirmDelete() {
  if (state.deleteId == null) return;
  const id = state.deleteId;
  closeDeleteModal();

  try {
    setBanners({ info: 'Deleting…' });
    const res = await authFetch(`${API_BASE_URL}/menu/${id}`, { method: 'DELETE' });
    if (!res) return;
    if (!res.ok) throw new Error('Failed to delete item');
    await reload();
    setBanners({ info: 'Item deleted.' });
  } catch (err) {
    console.warn(err);
    setBanners({ error: err && err.message ? err.message : 'Failed to delete item' });
  }
}

function rerenderItemsOnly() {
  renderCategoryFilters();
  renderItemsList();
}

async function reload() {
  const token = requireAuthOrRedirect();
  if (!token) return;

  setBanners();

  const [items, profile, messages, visitors, chatLogs] = await Promise.all([
    fetchMenuItems(),
    fetchProfile(),
    fetchContactMessages(),
    fetchVisitors(),
    fetchChatLogs(),
  ]);
  state.items = items;
  state.profile = profile;
  state.messages = messages;
  state.visitors = visitors;
  state.chatLogs = chatLogs;

  setCountBadge('count-messages', state.messages.length);
  setCountBadge('count-visitors', state.visitors.length);
  setCountBadge('count-chat', state.chatLogs.length);

  renderProfileCard();
  rerenderItemsOnly();
  renderMessagesList();
  renderVisitorsTable();
  renderChatLogsTable();

  if (typeof window.bindRevealOnScroll === 'function') {
    window.bindRevealOnScroll();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  requireAuthOrRedirect();

  // Show cached profile instantly (if available) while the network request runs.
  const cached = readCachedProfile();
  if (cached) {
    state.profile = cached;
  }
  renderProfileCard();

  const url = new URL(window.location.href);
  if (url.searchParams.get('profileUpdated') === '1') {
    setBanners({ info: 'Profile updated successfully.' });
    window.setTimeout(() => {
      if (typeof window.navigateWithTransition === 'function') {
        window.navigateWithTransition('admin.html');
      } else {
        window.location.href = 'admin.html';
      }
    }, 1200);
  }

  const logout = document.getElementById('admin-logout');
  if (logout) {
    logout.addEventListener('click', () => {
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem(PROFILE_CACHE_KEY);
      if (typeof window.navigateWithTransition === 'function') {
        window.navigateWithTransition('home.html');
      } else {
        window.location.href = 'home.html';
      }
    });
  }

  const form = document.getElementById('admin-item-form');
  const cancelBtn = document.getElementById('admin-form-cancel');
  if (cancelBtn) cancelBtn.addEventListener('click', clearForm);

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const fd = new FormData(form);
      const idRaw = String(fd.get('id') || '').trim();
      const id = idRaw ? Number(idRaw) : null;

      const price = Number(fd.get('price'));
      if (!fd.get('price') || Number.isNaN(price)) {
        setBanners({ error: 'Please enter a valid price.' });
        return;
      }

      const body = {
        name: String(fd.get('name') || '').trim(),
        description: String(fd.get('description') || '').trim() || undefined,
        ingredients: normalizeListInput(fd.get('ingredients')),
        allergens: normalizeListInput(fd.get('allergens')),
        dietaryTags: normalizeListInput(fd.get('dietaryTags')),
        imageUrl: String(fd.get('imageUrl') || '').trim() || undefined,
        price,
        category: String(fd.get('category') || '').trim(),
        isAvailable: Boolean(fd.get('isAvailable')),
      };

      try {
        setBanners({ info: 'Saving…' });
        await saveItem({ id, body });
        clearForm();
        await reload();
        setBanners({ info: id ? 'Menu item updated successfully.' : 'Menu item created successfully.' });
      } catch (err) {
        console.warn(err);
        setBanners({ error: err && err.message ? err.message : 'Failed to save item' });
      }
    });
  }

  // Modal bindings
  const modalCancel = document.getElementById('delete-modal-cancel');
  const modalConfirm = document.getElementById('delete-modal-confirm');
  const modalBackdrop = document.getElementById('delete-modal-backdrop');

  if (modalCancel) modalCancel.addEventListener('click', closeDeleteModal);
  if (modalBackdrop) modalBackdrop.addEventListener('click', closeDeleteModal);
  if (modalConfirm) modalConfirm.addEventListener('click', () => void confirmDelete());

  // Admin nav panel toggles
  document.querySelectorAll('.admin-nav-item').forEach((btn) => {
    btn.addEventListener('click', () => {
      const panelId = btn.dataset.panel;
      const isActive = btn.classList.contains('admin-nav-item--active');
      if (isActive) {
        btn.classList.remove('admin-nav-item--active');
        const panel = document.getElementById(panelId);
        if (panel) panel.classList.add('hidden');
      } else {
        showAdminPanel(panelId);
      }
    });
  });

  // initial mode
  setFormMode(false);

  reload().catch((err) => {
    console.warn(err);
    setBanners({ error: 'Failed to load admin dashboard.' });
  });
});
