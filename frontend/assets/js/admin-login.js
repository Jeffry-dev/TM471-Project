// admin-login.js

const API_BASE_URL = (window.APP_CONFIG && window.APP_CONFIG.API_BASE_URL) || 'http://localhost:8000';
const TOKEN_KEY = 'restaurant_jwt';
const PROFILE_CACHE_KEY = 'restaurant_profile_cache';

function writeCachedProfile(profile) {
  try {
    if (!profile) return;
    localStorage.setItem(PROFILE_CACHE_KEY, JSON.stringify(profile));
  } catch {
    // ignore
  }
}

function setBox(id, text) {
  const el = document.getElementById(id);
  if (!el) return;
  if (!text) {
    el.textContent = '';
    el.classList.add('hidden');
    return;
  }
  el.textContent = text;
  el.classList.remove('hidden');
}


document.addEventListener('DOMContentLoaded', () => {
  const backToSiteBtn = document.getElementById('back-to-site');

  if (backToSiteBtn) {
    backToSiteBtn.addEventListener('click', () => {
      window.location.href = 'home.html';
    });
  }

  const loginForm = document.getElementById('admin-login-form');
  const loginSubmit = document.getElementById('admin-login-submit');

  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const fd = new FormData(loginForm);
      const payload = {
        email: String(fd.get('email') || '').trim(),
        password: String(fd.get('password') || ''),
      };

      try {
        setBox('admin-login-error', '');
        setBox('admin-login-info', '');

        if (loginSubmit) {
          loginSubmit.disabled = true;
          loginSubmit.textContent = 'Signing in...';
        }

        const res = await fetch(`${API_BASE_URL}/auth/login`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        if (!res.ok) throw new Error('Invalid credentials');

        const data = await res.json();
        const token = data && (data.accessToken || data.token);
        if (!token) throw new Error('Missing token in response');

        localStorage.setItem(TOKEN_KEY, token);

        // Preload profile into cache so the admin dashboard card can show immediately.
        try {
          fetch(`${API_BASE_URL}/auth/me`, {
            method: 'GET',
            headers: {
              Authorization: `Bearer ${token}`,
            },
          })
            .then((r) => (r && r.ok ? r.json() : null))
            .then((profile) => {
              if (profile) writeCachedProfile(profile);
            })
            .catch(() => {
              // ignore
            });
        } catch {
          // ignore
        }

        setBox('admin-login-info', 'Login successful. Redirecting to dashboard...');

        setTimeout(() => {
          if (typeof window.navigateWithTransition === 'function') {
            window.navigateWithTransition('admin.html');
          } else {
            window.location.href = 'admin.html';
          }
        }, 1000);
      } catch (err) {
        console.warn(err);
        setBox('admin-login-error', err && err.message ? err.message : 'Login failed');
      } finally {
        if (loginSubmit) {
          loginSubmit.disabled = false;
          loginSubmit.textContent = 'Sign in';
        }
      }
    });
  }

});
