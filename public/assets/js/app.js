/* DOJ-NPS EMS — shared client helpers. Server-side validation and authorization are authoritative;
   everything here only improves the experience. */
(function () {
  'use strict';

  const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.getAttribute('content') || '';
  const EMS = {
    base: meta('base-url'),
    csrf: meta('csrf-token'),
    PASSWORD_REGEX: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/,
  };

  EMS.url = (p) => EMS.base + '/' + String(p).replace(/^\/+/, '');

  EMS.esc = (v) => String(v ?? '').replace(/[&<>"'`]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' }[c]));

  EMS.pageData = (id = 'page-data') => {
    const el = document.getElementById(id);
    try { return el ? JSON.parse(el.textContent) : {}; } catch { return {}; }
  };

  class ApiError extends Error {
    constructor(message, status, errors) { super(message); this.status = status; this.errors = errors || {}; }
  }
  EMS.ApiError = ApiError;

  /** JSON API call. body may be a plain object (sent as JSON) or FormData. */
  EMS.api = async (method, path, body) => {
    const opts = { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': EMS.csrf, 'X-Requested-With': 'fetch' } };
    if (body instanceof FormData) {
      opts.body = body;
    } else if (body !== undefined && method !== 'GET') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    let res;
    try {
      res = await fetch(EMS.url(path), opts);
    } catch {
      throw new ApiError('Unable to reach the server. Check your connection and try again.', 0);
    }
    let data = null;
    try { data = await res.json(); } catch { /* non-JSON */ }
    if (res.status === 401 && !path.startsWith('api/auth') && !path.startsWith('/api/auth')) {
      window.location.href = EMS.url('/login');
      throw new ApiError('Your session has ended.', 401);
    }
    if (!res.ok || !data || data.ok === false) {
      throw new ApiError(data?.message || `Request failed (${res.status}).`, res.status, data?.errors);
    }
    return data;
  };

  // ---- Feedback ------------------------------------------------------------------
  EMS.toast = (message, type = 'success') => {
    const host = document.getElementById('toasts');
    if (!host || !window.bootstrap) { if (window.Swal) Swal.fire({ icon: type === 'danger' ? 'error' : type, text: message, timer: 2500, showConfirmButton: false }); return; }
    const icons = { success: 'fa-circle-check text-success', danger: 'fa-circle-exclamation text-danger', warning: 'fa-triangle-exclamation text-warning', info: 'fa-circle-info text-primary' };
    const el = document.createElement('div');
    el.className = 'toast align-items-center border-0 shadow';
    el.setAttribute('role', type === 'danger' ? 'alert' : 'status');
    el.innerHTML = `<div class="d-flex"><div class="toast-body d-flex gap-2 align-items-start"><i class="fa-solid ${icons[type] || icons.info} mt-1"></i><span>${EMS.esc(message)}</span></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    host.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: type === 'danger' ? 7000 : 4000 });
    el.addEventListener('hidden.bs.toast', () => el.remove());
    t.show();
  };

  EMS.error = (err) => {
    const msg = err instanceof Error ? err.message : String(err);
    if (document.getElementById('toasts')) EMS.toast(msg, 'danger');
    else Swal.fire({ icon: 'error', title: 'Something went wrong', text: msg, confirmButtonText: 'OK' });
  };

  EMS.confirm = async ({ title, text = '', html, confirmText = 'Confirm', danger = false, icon = 'warning' }) => {
    const r = await Swal.fire({
      title, text, html, icon, showCancelButton: true, confirmButtonText: confirmText, cancelButtonText: 'Cancel',
      reverseButtons: true, focusCancel: danger, customClass: { confirmButton: danger ? 'danger' : '' },
    });
    return r.isConfirmed;
  };

  // ---- Forms ---------------------------------------------------------------------
  EMS.clearErrors = (form) => {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback.js-err').forEach((el) => el.remove());
    form.querySelectorAll('.form-alert').forEach((el) => el.remove());
  };

  EMS.showErrors = (form, errors, message) => {
    EMS.clearErrors(form);
    let first = null;
    Object.entries(errors || {}).forEach(([name, msg]) => {
      const input = form.querySelector(`[name="${CSS.escape(name)}"]`);
      if (!input) return;
      input.classList.add('is-invalid');
      const fb = document.createElement('div');
      fb.className = 'invalid-feedback js-err';
      fb.textContent = msg;
      const anchor = input.closest('.input-group') || input;
      anchor.insertAdjacentElement('afterend', fb);
      if (input.closest('.input-group')) fb.style.display = 'block';
      first = first || input;
    });
    if (message) {
      const a = document.createElement('div');
      a.className = 'alert alert-danger form-alert small py-2';
      a.setAttribute('role', 'alert');
      a.textContent = message;
      form.prepend(a);
    }
    (first || form.querySelector('.form-alert'))?.focus?.();
    first?.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
  };

  EMS.formObject = (form) => {
    const o = {};
    new FormData(form).forEach((v, k) => { if (!(v instanceof File)) o[k] = v; });
    return o;
  };

  EMS.busy = (btn, on, label) => {
    if (!btn) return;
    if (on) {
      btn.dataset.html = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${EMS.esc(label || 'Please wait…')}`;
    } else {
      btn.disabled = false;
      if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
    }
  };

  /** Wire a form to an API endpoint with validation feedback. */
  EMS.ajaxForm = (form, { method = 'POST', path, body, onSuccess, busyLabel, validate }) => {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      EMS.clearErrors(form);
      if (validate) {
        const errs = validate(form);
        if (errs && Object.keys(errs).length) { EMS.showErrors(form, errs); return; }
      }
      const btn = form.querySelector('[type="submit"]');
      EMS.busy(btn, true, busyLabel);
      try {
        const res = await EMS.api(typeof method === 'function' ? method() : method, typeof path === 'function' ? path() : path, body ? body(form) : EMS.formObject(form));
        await onSuccess?.(res, form);
      } catch (err) {
        if (err.errors && Object.keys(err.errors).some((k) => !k.startsWith('_'))) EMS.showErrors(form, err.errors, err.message);
        else EMS.showErrors(form, {}, err.message);
      } finally {
        EMS.busy(btn, false);
      }
    });
  };

  // ---- Password widgets ------------------------------------------------------------
  const RULES = [
    ['len', 'Minimum 10 characters', (p) => p.length >= 10],
    ['upper', 'Contains uppercase letter', (p) => /[A-Z]/.test(p)],
    ['lower', 'Contains lowercase letter', (p) => /[a-z]/.test(p)],
    ['digit', 'Contains number', (p) => /\d/.test(p)],
    ['special', 'Contains special character', (p) => /[^A-Za-z0-9]/.test(p)],
  ];
  EMS.passwordErrors = (p) => RULES.filter(([, , fn]) => !fn(p)).map(([, label]) => label);

  EMS.initPasswordWidgets = (root = document) => {
    root.querySelectorAll('[data-toggle-password]').forEach((btn) => {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.togglePassword);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(show));
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        btn.querySelector('i').className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
      });
    });
    root.querySelectorAll('input[data-password-meter]').forEach((input) => {
      if (input.dataset.bound) return;
      input.dataset.bound = '1';
      const box = document.createElement('div');
      box.innerHTML = `<div class="pw-strength" aria-hidden="true"><span></span></div>
        <ul class="pw-checklist" aria-live="polite">${RULES.map(([k, label]) => `<li data-rule="${k}"><i class="fa-regular fa-circle"></i> ${label}</li>`).join('')}</ul>`;
      (input.closest('.input-group') || input).insertAdjacentElement('afterend', box);
      const bar = box.querySelector('.pw-strength span');
      const update = () => {
        const p = input.value;
        let n = 0;
        RULES.forEach(([k, , fn]) => {
          const ok = fn(p);
          if (ok) n++;
          const li = box.querySelector(`[data-rule="${k}"]`);
          li.classList.toggle('ok', ok);
          li.querySelector('i').className = ok ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
        });
        const pct = (n / RULES.length) * 100;
        bar.style.width = (p ? Math.max(pct, 8) : 0) + '%';
        bar.style.background = n <= 2 ? '#8B1E2D' : n < 5 ? '#D4AF37' : '#1e7a46';
        input.dispatchEvent(new Event('pwcheck'));
      };
      input.addEventListener('input', update);
      update();
    });
    root.querySelectorAll('input[data-match]').forEach((confirm) => {
      if (confirm.dataset.bound) return;
      confirm.dataset.bound = '1';
      const original = document.getElementById(confirm.dataset.match);
      const out = document.createElement('div');
      out.className = 'pw-match';
      out.setAttribute('aria-live', 'polite');
      (confirm.closest('.input-group') || confirm).insertAdjacentElement('afterend', out);
      const update = () => {
        if (!confirm.value) { out.innerHTML = ''; return; }
        const ok = confirm.value === original.value;
        out.innerHTML = ok ? '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Passwords match</span>'
          : '<span class="text-doj-red"><i class="fa-solid fa-circle-xmark"></i> Passwords do not match</span>';
      };
      confirm.addEventListener('input', update);
      original.addEventListener('input', update);
    });
  };

  /** Client-side check mirroring the server rules. */
  EMS.validateNewPassword = (form, pwName = 'password', confirmName = 'confirm_password') => {
    const pw = form.querySelector(`[name="${pwName}"]`).value;
    const cf = form.querySelector(`[name="${confirmName}"]`).value;
    const errs = {};
    if (!EMS.PASSWORD_REGEX.test(pw)) errs[pwName] = 'Password does not meet all requirements: ' + (EMS.passwordErrors(pw).join(', ') || 'check the rules');
    if (pw !== cf) errs[confirmName] = 'Passwords do not match.';
    return errs;
  };

  // ---- Formatting ------------------------------------------------------------------
  EMS.fmtDate = (v) => {
    if (!v) return '';
    const d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? EMS.esc(v) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  };
  EMS.fmtDateTime = (v) => {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? EMS.esc(v) : d.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  };
  EMS.badge = (value, map) => {
    const label = map?.[value] || value;
    return `<span class="badge-status badge-${EMS.esc(value)}">${EMS.esc(label)}</span>`;
  };

  EMS.dtDefaults = {
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    autoWidth: false,
    language: {
      emptyTable: '<div class="empty-state"><i class="fa-regular fa-folder-open"></i><h3>No records yet</h3></div>',
      zeroRecords: '<div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i><h3>No matching records</h3><div>Try a different search or filter.</div></div>',
      search: '', searchPlaceholder: 'Search…',
      loadingRecords: '<span class="spinner-border spinner-border-sm me-2"></span>Loading…',
      processing: '<span class="spinner-border spinner-border-sm me-2"></span>Loading…',
    },
  };

  // ---- Shell behaviour -----------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebarToggle');
    const mobile = () => window.matchMedia('(max-width: 991.98px)').matches;
    try { if (localStorage.getItem('ems.sidebar') === 'collapsed') document.body.classList.add('sidebar-collapsed'); } catch { /* storage unavailable */ }
    toggle?.addEventListener('click', () => {
      if (mobile()) { document.body.classList.toggle('sidebar-open'); return; }
      document.body.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem('ems.sidebar', document.body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open'); } catch { /* ignore */ }
    });
    document.querySelectorAll('[data-sidebar-close]').forEach((el) => el.addEventListener('click', () => document.body.classList.remove('sidebar-open')));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.body.classList.remove('sidebar-open'); });

    document.querySelectorAll('form[data-confirm-logout]').forEach((f) => f.addEventListener('submit', async (e) => {
      if (f.dataset.confirmed) return;
      e.preventDefault();
      if (await EMS.confirm({ title: 'Sign out?', text: 'You will need to sign in again to continue.', confirmText: 'Logout', icon: 'question' })) {
        f.dataset.confirmed = '1';
        f.submit();
      }
    }));

    EMS.initPasswordWidgets();
  });

  window.EMS = EMS;
})();
