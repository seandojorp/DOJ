/* Administrator: Preloaded Users CRUD */
(function () {
  'use strict';
  const { EMS } = window;
  const esc = EMS.esc;
  const STATUS = { registered: 'Registered', unregistered: 'Not Registered' };
  let rows = [];

  const filter = document.getElementById('statusFilter');
  const initial = new URLSearchParams(location.search).get('status');
  if (initial && STATUS[initial]) filter.value = initial;

  const dt = new DataTable('#preloadTable', {
    ...EMS.dtDefaults,
    data: [],
    order: [[0, 'asc']],
    columns: [
      { data: 'DOJ_ID', title: 'DOJ ID', className: 'code-cell fw-semibold', render: (v, t) => (t === 'display' ? esc(v) : v) },
      { data: 'Cellphone_No', title: 'Cellphone Number', render: (v, t) => (t === 'display' ? esc(v) : v) },
      { data: 'Email_Address', title: 'Email Address', render: (v, t) => (t === 'display' ? esc(v) : v) },
      { data: 'Registration_Status', title: 'Registration Status', render: (v, t) => (t === 'display' ? EMS.badge(v, STATUS) : STATUS[v]) },
      { data: null, title: 'Actions', orderable: false, searchable: false, className: 'table-actions',
        render: (r) => `<button type="button" class="btn btn-sm btn-light btn-icon" data-view="${r.Preload_Users_ID}" aria-label="View ${esc(r.DOJ_ID)}"><i class="fa-solid fa-eye"></i></button>
          <button type="button" class="btn btn-sm btn-outline-primary btn-icon" data-edit="${r.Preload_Users_ID}" aria-label="Edit ${esc(r.DOJ_ID)}"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn btn-sm btn-outline-danger btn-icon" data-del="${r.Preload_Users_ID}" aria-label="Delete ${esc(r.DOJ_ID)}"><i class="fa-solid fa-trash"></i></button>` },
    ],
  });

  async function reload() {
    try {
      const res = await EMS.api('GET', 'api/admin/preload' + (filter.value ? `?status=${filter.value}` : ''));
      rows = res.data.rows;
      dt.clear().rows.add(rows).draw(false);
      document.getElementById('statTotal').textContent = res.data.stats.total;
      document.getElementById('statRegistered').textContent = res.data.stats.registered;
      document.getElementById('statUnregistered').textContent = res.data.stats.unregistered;
    } catch (err) { EMS.error(err); }
  }
  filter.addEventListener('change', reload);

  const formBody = (r = {}) => `<div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="p_doj">DOJ ID<span class="req">*</span></label>
      <input class="form-control" id="p_doj" name="DOJ_ID" maxlength="50" required value="${esc(r.DOJ_ID)}"></div>
    <div class="col-md-4"><label class="form-label" for="p_cell">Cellphone Number<span class="req">*</span></label>
      <input class="form-control" id="p_cell" name="Cellphone_No" type="tel" maxlength="16" placeholder="09XXXXXXXXX" required value="${esc(r.Cellphone_No)}"></div>
    <div class="col-md-4"><label class="form-label" for="p_email">Email Address<span class="req">*</span></label>
      <input class="form-control" id="p_email" name="Email_Address" type="email" maxlength="50" required value="${esc(r.Email_Address)}"></div></div>
    <p class="form-text mt-3 mb-0"><i class="fa-solid fa-circle-info me-1"></i>Cellphone numbers are stored as 09XXXXXXXXX and emails in lowercase. Each value must be unique.</p>
    ${r.Registration_Status === 'registered' ? '<div class="alert alert-gold small mt-3 mb-0"><i class="fa-solid fa-triangle-exclamation me-1"></i>This employee has a registered account. Changes here also update the identity shown on their account and profile.</div>' : ''}`;

  const validate = (f) => {
    const e = {};
    if (!f.DOJ_ID.value.trim()) e.DOJ_ID = 'DOJ ID is required.';
    if (!/^(\+?63|0)?9\d{9}$/.test(f.Cellphone_No.value.replace(/[\s\-().]/g, ''))) e.Cellphone_No = 'Enter a valid Philippine mobile number.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.Email_Address.value.trim())) e.Email_Address = 'Enter a valid email address.';
    else if (f.Email_Address.value.trim().length > 50) e.Email_Address = 'Email address must not exceed 50 characters.';
    return e;
  };

  const open = (r) => EMS.Modules.openFormModal({
    title: r ? `Edit Preloaded User — ${r.DOJ_ID}` : 'Add Preloaded User',
    body: formBody(r || {}),
    validate,
    onSubmit: async (f) => {
      const body = EMS.formObject(f);
      const res = r ? await EMS.api('PUT', `api/admin/preload/${r.Preload_Users_ID}`, body) : await EMS.api('POST', 'api/admin/preload', body);
      EMS.toast(res.message);
      await reload();
    },
  });

  async function view(r) {
    let d;
    try { d = (await EMS.api('GET', `api/admin/preload/${r.Preload_Users_ID}`)).data; } catch (err) { EMS.error(err); return; }
    Swal.fire({
      title: esc(d.DOJ_ID),
      html: `<dl class="dl-grid text-start mt-2">
        <div><dt>Cellphone</dt><dd>${esc(d.Cellphone_No)}</dd></div><div><dt>Email</dt><dd>${esc(d.Email_Address)}</dd></div>
        <div><dt>Registration</dt><dd>${EMS.badge(d.Registration_Status, STATUS)}</dd></div>
        ${d.User_ID ? `<div><dt>Username</dt><dd>${esc(d.UserName)}</dd></div><div><dt>Account status</dt><dd>${EMS.badge(d.Account_Status)}</dd></div>
        <div><dt>Registered on</dt><dd>${EMS.fmtDateTime(d.Registered_At)}</dd></div><div><dt>Last login</dt><dd>${EMS.fmtDateTime(d.Last_Login)}</dd></div>` : ''}</dl>
        ${d.User_ID ? `<a class="btn btn-sm btn-outline-primary mt-3" href="${EMS.url('/admin/employees/' + d.User_ID)}">Open employee profile</a>` : ''}`,
      confirmButtonText: 'Close', width: 640,
    });
  }

  async function remove(r) {
    if (r.Registration_Status === 'registered') {
      const res = await Swal.fire({
        icon: 'warning', title: 'Linked to a registered account',
        html: `<p><strong>${esc(r.DOJ_ID)}</strong> is linked to the account <strong>${esc(r.UserName)}</strong>.</p>
          <p class="small text-muted mb-0">Linked records cannot be deleted, so the account and its employee records stay intact. You can deactivate the account instead.</p>`,
        showCancelButton: true, confirmButtonText: r.Account_Status === 'active' ? 'Deactivate account' : 'Close', cancelButtonText: 'Cancel',
        showConfirmButton: r.Account_Status === 'active', reverseButtons: true,
      });
      if (res.isConfirmed && r.Account_Status === 'active') {
        try { const out = await EMS.api('POST', `api/admin/accounts/${r.User_ID}/status`, { status: 'inactive' }); EMS.toast(out.message); await reload(); }
        catch (err) { EMS.error(err); }
      }
      return;
    }
    if (!(await EMS.confirm({ title: `Delete ${r.DOJ_ID}?`, text: 'This preloaded employee has not registered. Deleting removes their record so they can no longer register.', confirmText: 'Delete', danger: true }))) return;
    try { const res = await EMS.api('DELETE', `api/admin/preload/${r.Preload_Users_ID}`); EMS.toast(res.message); await reload(); }
    catch (err) { EMS.error(err); }
  }

  document.getElementById('preloadTable').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    const find = (id) => rows.find((r) => String(r.Preload_Users_ID) === id);
    if (b.dataset.view) view(find(b.dataset.view));
    if (b.dataset.edit) open(find(b.dataset.edit));
    if (b.dataset.del) remove(find(b.dataset.del));
  });
  document.getElementById('btnAddPreload').addEventListener('click', () => open(null));
  reload();
})();
