/* Mount employee-information sections on a page (regular user's own profile, or an admin's employee view). */
(function () {
  'use strict';
  const { EMS } = window;
  const data = EMS.pageData();

  const refreshCompletion = async () => {
    const pct = document.getElementById('completionPct');
    if (!pct) return;
    try {
      const res = await EMS.api('GET', 'api/profile/completion' + (data.userId ? `?user_id=${data.userId}` : ''));
      pct.textContent = res.data.percent + '%';
      Object.entries(res.data.sections).forEach(([slug, s]) => {
        const icon = document.querySelector(`[data-tab-state="${slug}"]`);
        if (icon) icon.className = `fa-solid ${s.complete ? 'fa-circle-check text-success' : (s.required ? 'fa-circle-exclamation text-warning' : '')} ms-1 small`;
      });
    } catch { /* non-critical */ }
  };

  (data.defs || []).forEach((def) => {
    const el = document.querySelector(`[data-section="${def.slug}"]`);
    if (!el) return;
    const opts = { def, userId: data.userId || null, onChange: refreshCompletion };
    if (def.special === 'personal') new EMS.Modules.Personal(el, { ...opts, isAdmin: !!data.isAdmin });
    else new EMS.Modules.Section(el, { ...opts, canDelete: !!data.isAdmin });
  });
})();
