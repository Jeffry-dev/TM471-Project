// admin-forgot-password.js

const API_BASE_URL = (window.APP_CONFIG && window.APP_CONFIG.API_BASE_URL) || 'http://localhost:8000';

function setStatus(text, isError = false) {
  const el = document.getElementById('admin-forgot-status');
  if (!el) return;
  el.textContent = text;
  el.style.color = isError ? 'var(--accent-danger)' : 'var(--accent-success)';
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('admin-forgot-form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const fd = new FormData(form);
    const payload = { email: String(fd.get('email') || '').trim() };

    try {
      setStatus('Sending…');
      const res = await fetch(`${API_BASE_URL}/auth/forgot-password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!res.ok) throw new Error('Could not send reset email');

      setStatus("If an account exists for this email, you’ll receive a reset link shortly.");
    } catch (err) {
      console.warn(err);
      setStatus(err && err.message ? err.message : 'Request failed', true);
    }
  });
});
