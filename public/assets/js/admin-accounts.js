/* Administrator: Registered Accounts */
(function () {
  'use strict';
  const { EMS } = window;
  const esc = EMS.esc;
  const me = EMS.pageData().me;
  const ROLE = { admin: 'Administrator', user: 'User' };
  const STATUS = { active: 'Active', inactive: 'Inactive' };
  let rows = [];
  let unregistered = [];

  const dt = new DataTable('#accountsTable', {
    ...EMS.dtDefaults,
    data: [],
    order: [[6, 'desc']],
    columns: [
      { data: 'User_ID', title: 'User ID', className: 'code-cell' },
      { data: 'DOJ_ID', title: 'DOJ ID', className: 'code-cell', render: (v, t) => (t === 'display' ? (v ? esc(v) : '<span class="text-muted">—</span>') : v || '') },
      { data: 'UserName', title: 'Username', render: (v, t, r) => (t === 'display' ? `<span class="fw-semibold">${esc(v)}</span>${r.Full_Name ? `<div class="small text-muted">${esc(r.Full_Name)}</div>` : ''}` : `${v} ${r.Full_Name || ''}`) },
      { data: 'Email_Address', title: 'Email / Cellphone', render: (v, t, r) => (t === 'display'
        ? (v ? `${esc(v)}<div class="small text-muted">${esc(r.Cellphone_No)}</div>` : '<span class="text-muted">—</span>') : `${v || ''} ${r.Cellphone_No || ''}`) },
      { data: 'Role', title: 'Role', render: (v, t) => (t === 'display' ? `<span class="badge-status badge-${esc(v)}">${ROLE[v]}</span>` : v) },
      { data: 'Account_Status', title: 'Status', render: (v, t) => (t === 'display' ? EMS.badge(v, STATUS) : v) },
      { data: 'Created_At', title: 'Registered', render: (v, t) => (t === 'display' ? `<span class="small text-nowrap" title="${esc(EMS.fmtDateTime(v))}">${EMS.fmtDate(v)}</span>` : v) },
      { data: null, title: 'Actions', orderable: false, searchable: false, className: 'table-actions', render: (r) => {
        const self = r.User_ID === me;
        return `<div class="dropdown d-inline-block">
          <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions for ${esc(r.UserName)}">Manage</button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><button class="dropdown-item" data-a="view" data-id="${r.User_ID}"><i class="fa-solid fa-eye me-2"></i>View details</button></li>
            <li><button class="dropdown-item" data-a="edit" data-id="${r.User_ID}"><i class="fa-solid fa-pen me-2"></i>Edit account</button></li>
            <li><button class="dropdown-item" data-a="reset" data-id="${r.User_ID}"><i class="fa-solid fa-key me-2"></i>Reset password</button></li>
            ${r.Role === 'user' || r.Preload_Users_ID ? `<li><a class="dropdown-item" href="${EMS.url('/admin/employees/' + r.User_ID)}"><i class="fa-solid fa-id-card me-2"></i>Employee profile</a></li>` : ''}
            <li><hr class="dropdown-divider"></li>
            ${self ? '<li><span class="dropdown-item-text small text-muted">This is your account</span></li>' : `
            <li><button class="dropdown-item" data-a="status" data-id="${r.User_ID}"><i class="fa-solid ${r.Account_Status === 'active' ? 'fa-toggle-off' : 'fa-toggle-on'} me-2"></i>${r.Account_Status === 'active' ? 'Deactivate' : 'Activate'}</button></li>
            <li><button class="dropdown-item text-doj-red" data-a="delete" data-id="${r.User_ID}"><i class="fa-solid fa-trash me-2"></i>Delete account</button></li>`}
          </ul></div>`;
      } },
    ],
  });

  const roleF = document.getElementById('roleFilter');
  const statusF = document.getElementById('statusFilter');
  DataTable.ext.search.push((settings, data, idx) => {
    if (settings.nTable.id !== 'accountsTable') return true;
    const r = dt.row(idx).data();
    return (!roleF.value || r.Role === roleF.value) && (!statusF.value || r.Account_Status === statusF.value);
  });
  [roleF, statusF].forEach((s) => s.addEventListener('change', () => dt.draw()));

  async function reload() {
    try {
      const res = await EMS.api('GET', 'api/admin/accounts');
      rows = res.data.rows.map((r) => ({ ...r, User_ID: Number(r.User_ID) }));
      unregistered = res.data.unregistered;
      dt.clear().rows.add(rows).draw(false);
      Object.entries(res.data.stats).forEach(([k, v]) => { const el = document.querySelector(`[data-s="${k}"]`); if (el) el.textContent = v; });
    } catch (err) { EMS.error(err); }
  }

  const pwFields = () => `<div class="row g-3"><div class="col-md-6"><label class="form-label" for="a_pw">Password<span class="req">*</span></label>
      <div class="input-group"><input type="password" class="form-control" id="a_pw" name="password" maxlength="72" autocomplete="new-password" data-password-meter required>
      <button class="btn btn-toggle-pw border" type="button" data-toggle-password="a_pw" aria-label="Show password"><i class="fa-solid fa-eye"></i></button></div></div>
    <div class="col-md-6"><label class="form-label" for="a_pw2">Confirm Password<span class="req">*</span></label>
      <div class="input-group"><input type="password" class="form-control" id="a_pw2" name="confirm_password" maxlength="72" autocomplete="new-password" data-match="a_pw" required>
      <button class="btn btn-toggle-pw border" type="button" data-toggle-password="a_pw2" aria-label="Show password"><i class="fa-solid fa-eye"></i></button></div></div></div>`;

  function createAccount() {
    const opts = unregistered.map((u) => `<option value="${u.id}">${esc(u.label)}</option>`).join('');
    const form = EMS.Modules.openFormModal({
      title: 'Create Account',
      body: `<div class="row g-3 mb-3">
          <div class="col-md-4"><label class="form-label" for="a_role">Role<span class="req">*</span></label>
            <select class="form-select" id="a_role" name="role"><option value="user">User (employee)</option><option value="admin">Administrator</option></select></div>
          <div class="col-md-8"><label class="form-label" for="a_pre">Preloaded employee<span class="req" id="preReq">*</span></label>
            <select class="form-select" id="a_pre" name="preload_id"><option value="">${unregistered.length ? 'Select an unregistered employee…' : 'No unregistered employees available'}</option>${opts}</select>
            <div class="form-text" id="preHelp">User accounts must be linked to an unregistered preloaded employee.</div></div>
          <div class="col-12"><label class="form-label" for="a_user">Username<span class="req">*</span></label>
            <input class="form-control" id="a_user" name="username" maxlength="50" autocomplete="off" required></div></div>${pwFields()}`,
      validate: (f) => {
        const e = EMS.validateNewPassword(f);
        if (!/^[A-Za-z0-9][A-Za-z0-9._-]{3,49}$/.test(f.username.value.trim())) e.username = 'Username must be 4–50 characters: letters, numbers, dot, dash or underscore.';
        if (f.role.value === 'user' && !f.preload_id.value) e.preload_id = 'Select the preloaded employee this account belongs to.';
        return e;
      },
      onSubmit: async (f) => { const res = await EMS.api('POST', 'api/admin/accounts', EMS.formObject(f)); EMS.toast(res.message); await reload(); },
      submitLabel: 'Create Account',
    });
    const role = form.querySelector('#a_role');
    role.addEventListener('change', () => {
      const admin = role.value === 'admin';
      form.querySelector('#preReq').hidden = admin;
      form.querySelector('#preHelp').textContent = admin ? 'Optional: link the administrator to their own employee record.' : 'User accounts must be linked to an unregistered preloaded employee.';
    });
  }

  function editAccount(r) {
    const linked = !!r.Preload_Users_ID;
    EMS.Modules.openFormModal({
      title: `Edit Account — ${r.UserName}`,
      body: `<div class="row g-3">
        <div class="col-md-8"><label class="form-label" for="e_user">Username<span class="req">*</span></label>
          <input class="form-control" id="e_user" name="username" maxlength="50" required value="${esc(r.UserName)}"></div>
        <div class="col-md-4"><label class="form-label" for="e_role">Role<span class="req">*</span></label>
          <select class="form-select" id="e_role" name="role"${r.User_ID === me ? ' disabled' : ''}>
            <option value="user"${r.Role === 'user' ? ' selected' : ''}${linked ? '' : ' disabled'}>User</option>
            <option value="admin"${r.Role === 'admin' ? ' selected' : ''}>Administrator</option></select>
          ${r.User_ID === me ? `<input type="hidden" name="role" value="${esc(r.Role)}"><div class="form-text">You cannot change your own role.</div>` : ''}</div>
        </div>
        <div class="form-section-title">Registration identity (preloaded record)</div>
        ${linked ? `<div class="row g-3">
          <div class="col-md-4"><label class="form-label" for="e_doj">DOJ ID<span class="req">*</span></label><input class="form-control" id="e_doj" name="doj_id" maxlength="50" required value="${esc(r.DOJ_ID)}"></div>
          <div class="col-md-4"><label class="form-label" for="e_cell">Cellphone Number<span class="req">*</span></label><input class="form-control" id="e_cell" name="cellphone" type="tel" maxlength="16" required value="${esc(r.Cellphone_No)}"></div>
          <div class="col-md-4"><label class="form-label" for="e_mail">Email Address<span class="req">*</span></label><input class="form-control" id="e_mail" name="email" type="email" maxlength="50" required value="${esc(r.Email_Address)}"></div></div>
          <p class="form-text mt-2 mb-0">The account stays linked to the same preloaded record; the employee's personal-information contact fields are updated to match.</p>`
        : '<p class="text-muted small mb-0"><i class="fa-solid fa-circle-info me-1"></i>This administrator is not linked to a preloaded employee record.</p>'}`,
      validate: (f) => {
        const e = {};
        if (!/^[A-Za-z0-9][A-Za-z0-9._-]{3,49}$/.test(f.username.value.trim())) e.username = 'Username must be 4–50 characters: letters, numbers, dot, dash or underscore.';
        if (linked) {
          if (!f.doj_id.value.trim()) e.doj_id = 'DOJ ID is required.';
          if (!/^(\+?63|0)?9\d{9}$/.test(f.cellphone.value.replace(/[\s\-().]/g, ''))) e.cellphone = 'Enter a valid Philippine mobile number.';
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.value.trim())) e.email = 'Enter a valid email address.';
        }
        return e;
      },
      onSubmit: async (f) => { const res = await EMS.api('PUT', `api/admin/accounts/${r.User_ID}`, EMS.formObject(f)); EMS.toast(res.message); await reload(); },
    });
  }

  function resetPassword(r) {
    EMS.Modules.openFormModal({
      title: `Reset Password — ${r.UserName}`,
      size: '',
      body: `<p class="small text-muted">Set a new temporary password and share it with the user through a secure channel. All of the user's active sessions will be signed out.</p>${pwFields()}`,
      validate: (f) => EMS.validateNewPassword(f),
      onSubmit: async (f) => { const res = await EMS.api('POST', `api/admin/accounts/${r.User_ID}/password`, EMS.formObject(f)); EMS.toast(res.message); },
      submitLabel: 'Reset Password',
    });
  }

  async function view(r) {
    let d;
    try { d = (await EMS.api('GET', `api/admin/accounts/${r.User_ID}`)).data; } catch (err) { EMS.error(err); return; }
    const linked = Object.entries(d.linked_records || {});
    Swal.fire({
      title: esc(d.UserName), width: 680, confirmButtonText: 'Close',
      html: `<dl class="dl-grid text-start mt-2">
        <div><dt>User ID</dt><dd>${esc(d.User_ID)}</dd></div><div><dt>Role</dt><dd>${ROLE[d.Role]}</dd></div>
        <div><dt>Status</dt><dd>${EMS.badge(d.Account_Status, STATUS)}</dd></div><div><dt>Full name</dt><dd>${esc(d.Full_Name || '—')}</dd></div>
        <div><dt>DOJ ID</dt><dd>${esc(d.DOJ_ID || '—')}</dd></div><div><dt>Cellphone</dt><dd>${esc(d.Cellphone_No || '—')}</dd></div>
        <div><dt>Email</dt><dd>${esc(d.Email_Address || '—')}</dd></div><div><dt>Registered</dt><dd>${EMS.fmtDateTime(d.Created_At)}</dd></div>
        <div><dt>Last updated</dt><dd>${EMS.fmtDateTime(d.Updated_At)}</dd></div><div><dt>Last login</dt><dd>${EMS.fmtDateTime(d.Last_Login)}</dd></div></dl>
        <div class="text-start small mt-3"><strong>Linked employee records:</strong> ${linked.length ? linked.map(([t, c]) => `${esc(t)} (${c})`).join(', ') : 'none'}</div>`,
    });
  }

  async function toggleStatus(r) {
    const deactivate = r.Account_Status === 'active';
    if (!(await EMS.confirm({
      title: `${deactivate ? 'Deactivate' : 'Activate'} ${r.UserName}?`,
      text: deactivate ? 'The user will be signed out immediately and will not be able to sign in. Their records are kept.' : 'The user will be able to sign in again.',
      confirmText: deactivate ? 'Deactivate' : 'Activate', danger: deactivate,
    }))) return;
    try { const res = await EMS.api('POST', `api/admin/accounts/${r.User_ID}/status`, { status: deactivate ? 'inactive' : 'active' }); EMS.toast(res.message); await reload(); }
    catch (err) { EMS.error(err); }
  }

  async function remove(r) {
    if (!(await EMS.confirm({
      title: `Delete account ${r.UserName}?`,
      html: 'Only accounts <strong>without employee records</strong> can be deleted. The preloaded employee record is kept and will show as <em>Not Registered</em>. If the account has records, deactivate it instead.',
      confirmText: 'Delete account', danger: true,
    }))) return;
    try { const res = await EMS.api('DELETE', `api/admin/accounts/${r.User_ID}`); EMS.toast(res.message); await reload(); }
    catch (err) {
      if (err.status === 409 && r.Account_Status === 'active') {
        const go = await EMS.confirm({ title: 'Cannot delete this account', text: `${err.message}`, confirmText: 'Deactivate instead', icon: 'info' });
        if (go) { try { const res = await EMS.api('POST', `api/admin/accounts/${r.User_ID}/status`, { status: 'inactive' }); EMS.toast(res.message); await reload(); } catch (e2) { EMS.error(e2); } }
      } else EMS.error(err);
    }
  }

  document.getElementById('accountsTable').addEventListener('click', (e) => {
    const b = e.target.closest('[data-a]');
    if (!b) return;
    const r = rows.find((x) => String(x.User_ID) === b.dataset.id);
    ({ view, edit: editAccount, reset: resetPassword, status: toggleStatus, delete: remove })[b.dataset.a]?.(r);
  });
  document.getElementById('btnAddAccount').addEventListener('click', createAccount);
  reload();
})();
