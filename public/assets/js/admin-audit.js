/* Administrator: audit log viewer (server-side paging) */
(function () {
  'use strict';
  const { EMS } = window;
  const esc = EMS.esc;
  const form = document.getElementById('auditFilters');

  const dt = new DataTable('#auditTable', {
    ...EMS.dtDefaults,
    serverSide: true,
    processing: true,
    pageLength: 25,
    order: [[0, 'desc']],
    ajax: (data, callback) => {
      const params = new URLSearchParams();
      params.set('draw', data.draw); params.set('start', data.start); params.set('length', data.length);
      params.set('search[value]', data.search.value || '');
      if (data.order?.[0]) { params.set('order[0][column]', data.order[0].column); params.set('order[0][dir]', data.order[0].dir); }
      new FormData(form).forEach((v, k) => { if (v) params.set(k, v); });
      fetch(EMS.url('api/admin/audit-logs?' + params), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((r) => { if (r.status === 401) location.href = EMS.url('/login'); return r.json(); })
        .then(callback)
        .catch(() => { EMS.toast('Could not load audit logs.', 'danger'); callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] }); });
    },
    columns: [
      { data: 'Created_At', title: 'Date / Time', render: (v) => `<span class="small text-nowrap">${EMS.fmtDateTime(v)}</span>` },
      { data: 'Actor_Username', title: 'Actor', render: (v, t, r) => `${esc(v || '—')}${r.Actor_User_ID ? `<div class="small text-muted">ID ${esc(r.Actor_User_ID)}</div>` : ''}` },
      { data: 'Actor_Role', title: 'Role', render: (v) => (v ? `<span class="badge-status badge-${esc(v)}">${v === 'admin' ? 'Admin' : 'User'}</span>` : '—') },
      { data: 'Action', title: 'Action', className: 'code-cell', render: (v) => esc(v) },
      { data: 'Affected_Table', title: 'Table', className: 'code-cell', render: (v) => esc(v || '—') },
      { data: 'Affected_Record_ID', title: 'Record', className: 'code-cell', render: (v) => esc(v || '—') },
      { data: 'Action_Result', title: 'Result', render: (v) => EMS.badge(v, { success: 'Success', failure: 'Failure' }) },
      { data: 'IP_Address', title: 'IP Address', className: 'code-cell', render: (v) => esc(v || '—') },
    ],
  });
  form.addEventListener('submit', (e) => { e.preventDefault(); dt.ajax.reload(); });
  form.addEventListener('reset', () => setTimeout(() => dt.ajax.reload(), 0));
})();
