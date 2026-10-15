/* Setup, login and registration pages */
(function () {
  'use strict';
  const { EMS } = window;

  const setup = document.getElementById('setupForm');
  if (setup) {
    EMS.ajaxForm(setup, {
      path: 'api/setup',
      busyLabel: 'Creating…',
      validate: (f) => {
        const errs = EMS.validateNewPassword(f);
        if (!/^[A-Za-z0-9][A-Za-z0-9._-]{3,49}$/.test(f.username.value.trim())) errs.username = 'Username must be 4–50 characters: letters, numbers, dot, dash or underscore.';
        return errs;
      },
      onSuccess: async (res) => {
        await Swal.fire({ icon: 'success', title: 'Administrator created', text: res.message, confirmButtonText: 'Go to sign in' });
        window.location.href = res.data.redirect;
      },
    });
  }

  const login = document.getElementById('loginForm');
  if (login) {
    const KEY = 'ems.rememberedUsername';
    const remember = document.getElementById('rememberUsername');
    try {
      const saved = localStorage.getItem(KEY);
      if (saved) { login.username.value = saved; remember.checked = true; login.password.focus(); }
    } catch { /* storage unavailable */ }
    const caps = document.getElementById('capsHint');
    const capsCheck = (e) => { if (caps && e.getModifierState) caps.hidden = !e.getModifierState('CapsLock'); };
    login.password.addEventListener('keyup', capsCheck);
    login.password.addEventListener('keydown', capsCheck);
    login.password.addEventListener('blur', () => { if (caps) caps.hidden = true; });
    document.getElementById('forgotLink')?.addEventListener('click', () => Swal.fire({
      icon: 'info',
      title: 'Forgot your password?',
      text: 'For security, passwords are reset only by the system administrator. Please contact your EMS administrator or HR office to request a reset.',
      confirmButtonText: 'OK',
    }));
    EMS.ajaxForm(login, {
      path: 'api/auth/login',
      busyLabel: 'Signing in…',
      validate: (f) => {
        const e = {};
        if (!f.username.value.trim()) e.username = 'Username is required.';
        if (!f.password.value) e.password = 'Password is required.';
        return e;
      },
      onSuccess: (res, f) => {
        try { remember.checked ? localStorage.setItem(KEY, f.username.value.trim()) : localStorage.removeItem(KEY); } catch { /* ignore */ }
        window.location.href = res.data.redirect;
      },
    });
  }

  const reg = document.getElementById('registerForm');
  if (reg) {
    EMS.ajaxForm(reg, {
      path: 'api/auth/register',
      busyLabel: 'Registering…',
      validate: (f) => {
        const e = EMS.validateNewPassword(f);
        if (!f.doj_id.value.trim()) e.doj_id = 'DOJ ID is required.';
        const cell = f.cellphone.value.replace(/[\s\-().]/g, '');
        if (!/^(\+?63|0)?9\d{9}$/.test(cell)) e.cellphone = 'Enter a valid Philippine mobile number (e.g. 09171234567).';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.value.trim())) e.email = 'Enter a valid email address.';
        if (!/^[A-Za-z0-9][A-Za-z0-9._-]{3,49}$/.test(f.username.value.trim())) e.username = 'Username must be 4–50 characters: letters, numbers, dot, dash or underscore.';
        return e;
      },
      onSuccess: async (res) => {
        await Swal.fire({ icon: 'success', title: 'Registration successful', text: 'Your account has been created. You may now sign in.', confirmButtonText: 'Go to sign in' });
        window.location.href = res.data.redirect;
      },
    });
  }
})();
