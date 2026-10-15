/* Administrator dashboard */
(function () {
  'use strict';
  const { EMS } = window;
  const esc = EMS.esc;
  const NAVY = '#12304A', RED = '#8B1E2D', GOLD = '#D4AF37', GREEN = '#1e7a46', GRAY = '#c4ccd7';

  async function load() {
    let d;
    try { d = (await EMS.api('GET', 'api/admin/stats')).data; } catch (err) { EMS.error(err); return; }
    document.querySelectorAll('[data-stat]').forEach((el) => { el.textContent = Number(d[el.dataset.stat] ?? 0).toLocaleString(); });

    if (window.Chart) {
      Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
      Chart.defaults.color = '#5b6878';
      new Chart(document.getElementById('chartRegistration'), {
        type: 'doughnut',
        data: { labels: ['Registered', 'Not registered'], datasets: [{ data: [d.registered, d.unregistered], backgroundColor: [NAVY, GOLD], borderWidth: 0 }] },
        options: { maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom' } } },
      });
      new Chart(document.getElementById('chartMonthly'), {
        type: 'bar',
        data: { labels: d.by_month.map((m) => m.label), datasets: [{ label: 'Registrations', data: d.by_month.map((m) => m.count), backgroundColor: NAVY, borderRadius: 4 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
      });
      new Chart(document.getElementById('chartCompletion'), {
        type: 'bar',
        data: { labels: Object.keys(d.completion), datasets: [{ label: 'Employees', data: Object.values(d.completion), backgroundColor: [GRAY, RED, GOLD, GREEN], borderRadius: 4 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } },
      });
    }

    const recent = document.getElementById('recentBody');
    recent.innerHTML = d.recent.length ? d.recent.map((r) => `<tr>
        <td><a class="fw-semibold" href="${EMS.url('/admin/employees/' + r.User_ID)}">${esc(r.Full_Name || r.UserName)}</a><div class="small text-muted">${esc(r.UserName)}</div></td>
        <td class="code-cell">${esc(r.DOJ_ID)}</td><td>${EMS.badge(r.Account_Status, { active: 'Active', inactive: 'Inactive' })}</td>
        <td class="small">${EMS.fmtDateTime(r.Created_At)}</td></tr>`).join('')
      : '<tr><td colspan="4"><div class="empty-state py-3"><i class="fa-solid fa-user-plus"></i><h3>No registrations yet</h3><div>Employees appear here after they register.</div></div></td></tr>';

    const act = document.getElementById('activityList');
    act.innerHTML = d.activity.length ? d.activity.map((a) => `<li class="list-group-item d-flex gap-3 align-items-start">
        <i class="fa-solid ${a.Action_Result === 'success' ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-doj-red'} mt-1"></i>
        <div class="flex-grow-1 small"><div class="fw-semibold code-cell">${esc(a.Action)}</div>
        <div class="text-muted">${esc(a.Actor_Username || 'anonymous')}${a.Affected_Table ? ' · ' + esc(a.Affected_Table) : ''}${a.Affected_Record_ID ? ' #' + esc(a.Affected_Record_ID) : ''}</div></div>
        <span class="small text-muted text-nowrap">${EMS.fmtDateTime(a.Created_At)}</span></li>`).join('')
      : '<li class="list-group-item"><div class="empty-state py-3"><i class="fa-solid fa-clock-rotate-left"></i><h3>No activity yet</h3></div></li>';
  }
  load();
})();
