/* Administrator: one information section across all employees */
(function () {
  'use strict';
  const { EMS } = window;
  const esc = EMS.esc;
  const page = EMS.pageData();
  const tables = {};

  page.defs.forEach((def) => {
    const el = document.querySelector(`[data-table="${def.slug}"]`);
    if (def.special === 'personal') {
      const dt = new DataTable(el, {
        ...EMS.dtDefaults, data: [], order: [[1, 'asc']],
        columns: [
          { data: 'DOJ_ID', title: 'DOJ ID', className: 'code-cell', render: (v, t) => (t === 'display' ? esc(v || '—') : v || '') },
          { data: 'Full_Name', title: 'Full Name', render: (v, t, r) => (t === 'display' ? `<span class="fw-semibold">${esc(v)}</span><div class="small text-muted">${esc(r.UserName)}</div>` : v) },
          { data: 'Birthday', title: 'Date of Birth', render: (v, t) => (t === 'display' ? EMS.fmtDate(v) : v) },
          { data: 'Sex', title: 'Sex', render: (v) => esc(v) },
          { data: 'Civil_Status', title: 'Civil Status', render: (v) => esc(v) },
          { data: 'Mobile_No', title: 'Mobile', render: (v) => esc(v) },
          { data: 'Email_Address', title: 'Email', render: (v) => esc(v) },
          { data: null, title: '', orderable: false, searchable: false, className: 'table-actions',
            render: (r) => `<a class="btn btn-sm btn-outline-primary" href="${EMS.url('/admin/employees/' + r.User_ID)}"><i class="fa-solid fa-pen me-1"></i>Open profile</a>` },
        ],
      });
      EMS.api('GET', 'api/admin/records/personal').then((res) => dt.clear().rows.add(res.data.rows).draw()).catch(EMS.error);
      return;
    }
    tables[def.slug] = EMS.Modules.crossTable(el, { def, employees: page.employees });
  });

  document.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', () => tables[b.dataset.add]?.add()));
})();
