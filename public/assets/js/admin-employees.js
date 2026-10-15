/* Administrator: employee search */
(function () {
  'use strict';
  const { EMS } = window;
  const esc = EMS.esc;
  const page = EMS.pageData();
  const filter = document.getElementById('completionFilter');

  const dt = new DataTable('#employeesTable', {
    ...EMS.dtDefaults,
    data: [],
    order: [[1, 'asc']],
    search: { search: page.q || '' },
    columns: [
      { data: 'DOJ_ID', title: 'DOJ ID', className: 'code-cell', render: (v, t) => (t === 'display' ? esc(v || '—') : v || '') },
      { data: null, title: 'Employee', render: (r, t) => (t === 'display'
        ? `<a class="fw-semibold" href="${EMS.url('/admin/employees/' + r.User_ID)}">${esc(r.Full_Name || '(no personal information yet)')}</a><div class="small text-muted">${esc(r.UserName)}${r.Role === 'admin' ? ' · administrator' : ''}</div>`
        : `${r.Full_Name || ''} ${r.UserName} ${r.Email_Address || ''} ${r.Cellphone_No || ''}`) },
      { data: 'Current_Position', title: 'Position / Office', render: (v, t, r) => (t === 'display' ? (v ? `${esc(v)}<div class="small text-muted">${esc(r.Original_Office || '')}</div>` : '<span class="text-muted">—</span>') : `${v || ''} ${r.Original_Office || ''}`) },
      { data: 'completion', title: 'Profile', render: (v, t) => (t === 'display'
        ? `<div class="d-flex align-items-center gap-2" style="min-width:120px"><div class="progress flex-grow-1"><div class="progress-bar ${v === 100 ? 'done' : ''}" style="width:${v}%"></div></div><span class="small">${v}%</span></div>` : v) },
      { data: 'Account_Status', title: 'Status', render: (v, t) => (t === 'display' ? EMS.badge(v, { active: 'Active', inactive: 'Inactive' }) : v) },
      { data: null, title: '', orderable: false, searchable: false, className: 'table-actions',
        render: (r) => `<a class="btn btn-sm btn-outline-primary" href="${EMS.url('/admin/employees/' + r.User_ID)}"><i class="fa-solid fa-folder-open me-1"></i>Open</a>` },
    ],
  });
  DataTable.ext.search.push((s, d, i) => {
    if (s.nTable.id !== 'employeesTable' || !filter.value) return true;
    const c = dt.row(i).data().completion;
    return filter.value === 'complete' ? c === 100 : c < 100;
  });
  filter.addEventListener('change', () => dt.draw());

  EMS.api('GET', 'api/admin/employees').then((res) => {
    dt.clear().rows.add(res.data.rows.map((r) => ({ ...r, completion: Number(r.completion) }))).draw();
  }).catch(EMS.error);
})();
