// contact.js

const API_BASE_URL = (window.APP_CONFIG && window.APP_CONFIG.API_BASE_URL) || 'http://localhost:8000';
const MAX_MESSAGE_TOTAL = 1000;

function setStatus(text, isError = false) {
  const el = document.getElementById('contact-status');
  if (!el) return;
  el.textContent = text;
  el.style.color = isError ? 'var(--accent-danger)' : 'var(--accent-success)';
}

function updateCount(text) {
  const el = document.getElementById('message-count');
  if (!el) return;
  const len = String(text || '').length;
  el.textContent = String(Math.min(len, MAX_MESSAGE_TOTAL));
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contact-form');
  const submitBtn = document.getElementById('contact-submit');
  if (!form) return;
  const emailField = document.getElementById('contact-email-field');
  const phoneField = document.getElementById('contact-phone-field');
  const emailEl = form.querySelector('input[name="email"]');
  const phoneEl = form.querySelector('input[name="phone"]');
  const preferredContactEl = form.querySelector('select[name="preferredContact"]');

  const syncPreferredContactValidation = () => {
    const preferred = String(preferredContactEl && preferredContactEl.value ? preferredContactEl.value : 'email');
    const wantsEmail = preferred === 'email';
    const wantsPhone = preferred === 'phone';

    if (emailEl) {
      emailEl.required = wantsEmail;
      emailEl.disabled = !wantsEmail;
    }
    if (phoneEl) {
      phoneEl.required = wantsPhone;
      phoneEl.disabled = !wantsPhone;
    }
    if (emailField) emailField.classList.toggle('hidden', !wantsEmail);
    if (phoneField) phoneField.classList.toggle('hidden', !wantsPhone);
  };

  syncPreferredContactValidation();
  if (preferredContactEl) {
    preferredContactEl.addEventListener('change', syncPreferredContactValidation);
  }

  const messageEl = form.querySelector('textarea[name="message"]');
  if (messageEl) {
    updateCount(messageEl.value);
    messageEl.addEventListener('input', () => updateCount(messageEl.value));
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const fd = new FormData(form);
    const payload = {
      name: String(fd.get('name') || '').trim(),
      email: String(fd.get('email') || '').trim(),
      phone: String(fd.get('phone') || '').trim(),
      subject: String(fd.get('subject') || '').trim(),
      message: String(fd.get('message') || '').trim(),
      mode: 'contact',
      preferredContact: String(fd.get('preferredContact') || 'email').trim().toLowerCase() === 'phone' ? 'phone' : 'email',
    };

    if (!payload.name || !payload.subject || !payload.message) {
      setStatus('Please fill in all required fields.', true);
      return;
    }
    if (payload.preferredContact === 'email' && !payload.email) {
      setStatus('Please provide an email address or switch preferred contact to phone.', true);
      return;
    }
    if (payload.preferredContact === 'phone' && !payload.phone) {
      setStatus('Please provide a phone number or switch preferred contact to email.', true);
      return;
    }

    try {
      setStatus('Sending…');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
      }

      const res = await fetch(`${API_BASE_URL}/contact-messages`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!res.ok) throw new Error(`Failed: ${res.status}`);

      setStatus('Message sent successfully. Thank you for contacting us!');
      form.reset();
      updateCount('');
    } catch (err) {
      console.warn(err);
      setStatus('Something went wrong. Please try again.', true);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send message';
      }
    }
  });
});
