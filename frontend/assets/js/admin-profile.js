// admin-profile.js

const API_BASE_URL = (window.APP_CONFIG && window.APP_CONFIG.API_BASE_URL) || 'http://localhost:8000';
const TOKEN_KEY = 'restaurant_jwt';
const PROFILE_CACHE_KEY = 'restaurant_profile_cache';

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

function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = text || '';
}

function setError(text) {
  const el = document.getElementById('admin-profile-error');
  if (!el) return;
  if (!text) {
    el.textContent = '';
    el.classList.add('hidden');
    return;
  }
  el.textContent = text;
  el.classList.remove('hidden');
}

function setStatus(text, isError = false) {
  const el = document.getElementById('admin-profile-status');
  if (!el) return;
  el.textContent = text || '';
  el.style.color = isError ? 'var(--accent-danger)' : 'var(--accent-success)';
}

async function authFetch(url, options) {
  const token = getToken();
  if (!token) {
    window.location.href = 'admin-login.html';
    return null;
  }

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

function formatSince(createdAt) {
  try {
    return new Date(createdAt).toLocaleDateString();
  } catch {
    return '';
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

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('admin-profile-form');
  if (!form) return;

  const submitBtn = document.getElementById('admin-profile-submit');

  const nameInput = form.querySelector('input[name="name"]');
  const avatarInput = form.querySelector('input[name="avatarUrl"]');
  const bioInput = form.querySelector('textarea[name="bio"]');

  // Paint immediately from cache (prevents the 1s "Loading…" flash)
  const cached = readCachedProfile();
  if (cached) {
    if (nameInput) nameInput.value = cached.name ? cached.name : '';
    if (bioInput) bioInput.value = cached.bio ? cached.bio : '';
    if (avatarInput) avatarInput.value = cached.avatarUrl ? cached.avatarUrl : '';

    setText('profile-email', cached.email ? cached.email : '');
    setText(
      'profile-role-since',
      cached.role && cached.createdAt ? `${cached.role} • since ${formatSince(cached.createdAt)}` : '',
    );

    const initial = (cached.name || cached.email ? String(cached.name || cached.email)[0] : '?') || '?';
    setText('profile-avatar', String(initial).toUpperCase());
  }

  try {
    setError('');
    // Only show Loading… if we truly have nothing to display yet.
    setStatus(cached ? '' : 'Loading…');

    const res = await authFetch(`${API_BASE_URL}/auth/me`, { method: 'GET' });
    if (!res) return;
    if (!res.ok) throw new Error('Failed to fetch profile');

    const profile = await res.json();

    if (nameInput) nameInput.value = profile && profile.name ? profile.name : '';
    if (bioInput) bioInput.value = profile && profile.bio ? profile.bio : '';
    if (avatarInput) avatarInput.value = profile && profile.avatarUrl ? profile.avatarUrl : '';

    setText('profile-email', profile && profile.email ? profile.email : '');
    setText(
      'profile-role-since',
      profile && profile.role && profile.createdAt
        ? `${profile.role} • since ${formatSince(profile.createdAt)}`
        : '',
    );

    const initial = (profile && (profile.name || profile.email) ? String(profile.name || profile.email)[0] : '?') || '?';
    setText('profile-avatar', String(initial).toUpperCase());

    // Cache so admin dashboard can show instantly after navigation.
    writeCachedProfile(profile);

    setStatus('');
  } catch (err) {
    console.warn(err);
    setError(err && err.message ? err.message : 'Failed to load');
    setStatus('');
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const payload = {
      name: nameInput ? String(nameInput.value || '').trim() || undefined : undefined,
      bio: bioInput ? String(bioInput.value || '').trim() || undefined : undefined,
      avatarUrl: avatarInput ? String(avatarInput.value || '').trim() || undefined : undefined,
    };

    try {
      setError('');
      setStatus('Saving…');

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
      }

      const res = await authFetch(`${API_BASE_URL}/auth/profile`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!res) return;
      if (!res.ok) throw new Error('Failed to update profile');

      // Update cache immediately so the dashboard reflects changes without waiting.
      let prev = null;
      try {
        prev = JSON.parse(localStorage.getItem(PROFILE_CACHE_KEY) || 'null');
      } catch {
        prev = null;
      }

      const patch = {};
      if (payload.name !== undefined) patch.name = payload.name;
      if (payload.bio !== undefined) patch.bio = payload.bio;
      if (payload.avatarUrl !== undefined) patch.avatarUrl = payload.avatarUrl;

      const merged = {
        ...(prev && typeof prev === 'object' ? prev : {}),
        ...patch,
      };

      writeCachedProfile(merged);

      setStatus('Saved. Redirecting…');
      setTimeout(() => {
        if (typeof window.navigateWithTransition === 'function') {
          window.navigateWithTransition('admin.html');
        } else {
          window.location.href = 'admin.html';
        }
      }, 800);
    } catch (err) {
      console.warn(err);
      setError(err && err.message ? err.message : 'Save failed');
      setStatus('');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save changes';
      }
    }
  });
});
