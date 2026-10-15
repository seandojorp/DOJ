/* Employee-information sections: dynamic forms, record tables and the personal-information form.
   Field definitions come from the server (App\Services\Modules), which also re-validates everything. */
(function () {
  'use strict';
  const { EMS } = window;
  const esc = EMS.esc;

  // ---- Field rendering -----------------------------------------------------------------
  const today = () => new Date().toISOString().slice(0, 10);

  function fieldInput(name, f, value, { idPrefix = 'f', locked = false } = {}) {
    const id = `${idPrefix}_${name}`;
    const type = f.type || 'text';
    const v = value ?? '';
    const req = ' required aria-required="true"';
    const ro = locked ? ' readonly aria-readonly="true"' : '';
    const ph = f.na ? ' placeholder="Type N/A if not applicable"' : '';
    let html;
    if (type === 'select') {
      const opts = Object.entries(f.options || {}).map(([k, label]) =>
        `<option value="${esc(k)}"${String(k) === String(v) ? ' selected' : ''}>${esc(label)}</option>`).join('');
      html = `<select class="form-select" id="${id}" name="${esc(name)}"${req}${locked ? ' disabled' : ''}><option value="">Select…</option>${opts}</select>`;
    } else {
      let attrs = '';
      let inputType = 'text';
      switch (type) {
        case 'date': inputType = 'date'; attrs = ` min="1900-01-01" max="${f.past ? today() : '2100-12-31'}"`; break;
        case 'int': inputType = 'number'; attrs = ` step="1" min="${f.min ?? 0}" max="${f.maxv ?? 2147483647}" inputmode="numeric"`; break;
        case 'decimal': inputType = 'number'; attrs = ` step="${f.step || '0.01'}" min="${f.min ?? 0}" max="${f.maxv ?? 999.99}" inputmode="decimal"`; break;
        case 'email': inputType = 'email'; attrs = ` maxlength="${f.max || 255}" autocomplete="email"`; break;
        case 'phone': inputType = 'tel'; attrs = ' maxlength="16" placeholder="09XXXXXXXXX" inputmode="tel"'; break;
        case 'zip': attrs = ' maxlength="4" pattern="\\d{4}" inputmode="numeric" placeholder="0000"'; break;
        case 'year': attrs = ' maxlength="4" placeholder="YYYY or N/A"'; break;
        case 'sg': attrs = ` maxlength="${f.max || 4}" placeholder="e.g. 11-2"`; break;
        default: attrs = ` maxlength="${f.max || 255}"${ph}`;
      }
      const list = f.datalist ? ` list="${id}_list"` : '';
      html = `<input type="${inputType}" class="form-control" id="${id}" name="${esc(name)}" value="${esc(v)}"${attrs}${list}${req}${ro}>`;
      if (f.datalist) html += `<datalist id="${id}_list">${f.datalist.map((d) => `<option value="${esc(d)}">`).join('')}</datalist>`;
    }
    if (locked && type === 'select') html += `<input type="hidden" name="${esc(name)}" value="${esc(v)}">`;
    const help = locked ? '<div class="field-lock mt-1"><i class="fa-solid fa-lock me-1"></i>From your registration record — contact the administrator to change.</div>'
      : (f.help ? `<div class="form-text">${esc(f.help)}</div>` : '');
    const col = f.col || (type === 'date' || type === 'int' || type === 'decimal' || type === 'zip' || type === 'year' || type === 'sg' ? 4 : 6);
    return `<div class="col-md-${col}"><label class="form-label" for="${id}">${esc(f.label)}<span class="req" aria-hidden="true">*</span></label>${html}${help}</div>`;
  }

  function renderFields(def, values = {}, opts = {}) {
    let html = '';
    let group = null;
    let open = false;
    Object.entries(def.fields).forEach(([name, f]) => {
      if (f.group !== undefined && f.group !== group) {
        if (open) html += '</div>';
        group = f.group;
        html += `<div class="form-section-title">${esc(group)}</div>`;
        if (opts.groupExtras?.[group]) html += opts.groupExtras[group];
        html += '<div class="row g-3">';
        open = true;
      } else if (!open) {
        html += '<div class="row g-3">';
        open = true;
      }
      html += fieldInput(name, f, values[name], { idPrefix: opts.idPrefix, locked: (opts.locked || []).includes(name) });
    });
    if (open) html += '</div>';
    return html;
  }

  function displayValue(f, v) {
    if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
    switch (f.type) {
      case 'select': return esc(f.options?.[v] ?? v);
      case 'date': return EMS.fmtDate(v);
      case 'decimal': return esc(Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
      default: return esc(v);
    }
  }

  function clientValidate(def, form) {
    const errs = {};
    Object.entries(def.fields).forEach(([name, f]) => {
      const el = form.querySelector(`[name="${CSS.escape(name)}"]`);
      if (!el) return;
      const v = String(el.value || '').trim();
      if (!v) { errs[name] = `${f.label} is required.${f.na ? ' Enter N/A if not applicable.' : ''}`; return; }
      if (el.validity && !el.validity.valid && el.type !== 'text') errs[name] = el.validationMessage || `Enter a valid ${f.label}.`;
      if (f.type === 'zip' && !/^\d{4}$/.test(v)) errs[name] = 'ZIP Code must be 4 digits.';
    });
    Object.entries(def.fields).forEach(([name, f]) => {
      if (!f.after) return;
      const a = form.querySelector(`[name="${f.after}"]`)?.value;
      const b = form.querySelector(`[name="${name}"]`)?.value;
      if (a && b && b < a) errs[name] = `${f.label} cannot be earlier than ${def.fields[f.after].label}.`;
    });
    return errs;
  }

  // ---- Shared modal ------------------------------------------------------------------------
  let modalEl;
  function modal() {
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.tabIndex = -1;
      modalEl.setAttribute('aria-hidden', 'true');
      modalEl.innerHTML = `<div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" novalidate>
        <div class="modal-header"><h2 class="modal-title fs-5"></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save</button></div></form></div>`;
      document.body.appendChild(modalEl);
    }
    return modalEl;
  }

  /** Open a form modal. onSubmit(form) must return a promise; errors are shown in the form. */
  function openFormModal({ title, body, onSubmit, validate, size = 'modal-lg', submitLabel = 'Save' }) {
    const el = modal();
    const dialog = el.querySelector('.modal-dialog');
    dialog.className = `modal-dialog ${size} modal-dialog-scrollable`;
    el.querySelector('.modal-title').textContent = title;
    el.querySelector('.modal-body').innerHTML = body;
    const oldForm = el.querySelector('form');
    const form = oldForm.cloneNode(true); // drop previous listeners
    oldForm.replaceWith(form);
    form.querySelector('.modal-body').innerHTML = body;
    form.querySelector('[type="submit"]').innerHTML = `<i class="fa-solid fa-floppy-disk me-2"></i>${esc(submitLabel)}`;
    const bs = bootstrap.Modal.getOrCreateInstance(el, { backdrop: 'static' });
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      EMS.clearErrors(form);
      const errs = validate ? validate(form) : {};
      if (errs && Object.keys(errs).length) { EMS.showErrors(form, errs); return; }
      const btn = form.querySelector('[type="submit"]');
      EMS.busy(btn, true, 'Saving…');
      try {
        await onSubmit(form);
        bs.hide();
      } catch (err) {
        EMS.showErrors(form, err.errors && !err.errors._identity ? err.errors : {}, err.message);
      } finally {
        EMS.busy(btn, false);
      }
    });
    EMS.initPasswordWidgets(form);
    bs.show();
    el.addEventListener('shown.bs.modal', () => form.querySelector('input:not([type=hidden]):not([readonly]),select')?.focus(), { once: true });
    return form;
  }

  // ---- One module for one employee -------------------------------------------------------
  /**
   * Section(container, { def, userId (admin only), canDelete })
   * Single-record modules show a read view with an inline edit form; multi-record modules show a table.
   */
  class Section {
    constructor(container, { def, userId = null, canDelete = false, onChange = null }) {
      Object.assign(this, { container, def, userId, canDelete, onChange });
      this.listCols = Object.entries(def.fields).filter(([, f]) => f.list);
      this.load();
    }

    q() { return this.userId ? `?user_id=${encodeURIComponent(this.userId)}` : ''; }

    async load() {
      this.container.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>';
      try {
        const res = await EMS.api('GET', `api/records/${this.def.slug}${this.q()}`);
        this.rows = res.data.rows;
        this.meta = res.data;
        this.def.single ? this.renderSingle() : this.renderTable();
      } catch (err) {
        this.container.innerHTML = `<div class="alert alert-danger">${esc(err.message)}</div>`;
      }
    }

    payload(form) {
      const o = EMS.formObject(form);
      if (this.userId) o.user_id = this.userId;
      return o;
    }

    async save(form, id) {
      const res = id
        ? await EMS.api('PUT', `api/records/${this.def.slug}/${id}`, this.payload(form))
        : await EMS.api('POST', `api/records/${this.def.slug}`, this.payload(form));
      EMS.toast(res.message || 'Saved.');
      await this.load();
      this.onChange?.();
    }

    renderSingle() {
      const row = this.rows[0];
      if (!row) {
        this.container.innerHTML = `<div class="empty-state"><i class="fa-solid ${esc(this.def.icon)}"></i><h3>No ${esc(this.def.title)} on file</h3>
          <p>All fields are required. Use N/A for items that do not apply.</p>
          <button type="button" class="btn btn-primary" data-act="add"><i class="fa-solid fa-plus me-2"></i>Add ${esc(this.def.title)}</button></div>`;
        this.container.querySelector('[data-act="add"]').addEventListener('click', () => this.renderSingleForm(null));
        return;
      }
      const groups = {};
      Object.entries(this.def.fields).forEach(([n, f]) => { (groups[f.group || ''] ||= []).push([n, f]); });
      let html = `<div class="d-flex justify-content-end mb-2 gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" data-act="edit"><i class="fa-solid fa-pen me-1"></i>Edit</button>
        ${this.canDelete ? '<button type="button" class="btn btn-outline-danger btn-sm" data-act="delete"><i class="fa-solid fa-trash me-1"></i>Delete</button>' : ''}</div>`;
      Object.entries(groups).forEach(([g, fields]) => {
        if (g) html += `<div class="form-section-title">${esc(g)}</div>`;
        html += '<dl class="dl-grid">' + fields.map(([n, f]) => `<div><dt>${esc(f.label)}</dt><dd>${displayValue(f, row[n])}</dd></div>`).join('') + '</dl>';
      });
      this.container.innerHTML = html;
      this.container.querySelector('[data-act="edit"]').addEventListener('click', () => this.renderSingleForm(row));
      this.container.querySelector('[data-act="delete"]')?.addEventListener('click', () => this.remove(row));
    }

    renderSingleForm(row) {
      const pk = this.def.pk;
      this.container.innerHTML = `<form novalidate>${renderFields(this.def, row || {}, { idPrefix: this.def.slug })}
        <div class="d-flex gap-2 justify-content-end mt-4 border-top pt-3">
          <button type="button" class="btn btn-light" data-act="cancel">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save ${esc(this.def.title)}</button></div></form>`;
      const form = this.container.querySelector('form');
      form.querySelector('[data-act="cancel"]').addEventListener('click', () => this.renderSingle());
      EMS.ajaxForm(form, {
        method: row ? 'PUT' : 'POST',
        path: row ? `api/records/${this.def.slug}/${row[pk]}` : `api/records/${this.def.slug}`,
        body: (f) => this.payload(f),
        busyLabel: 'Saving…',
        validate: (f) => clientValidate(this.def, f),
        onSuccess: async (res) => { EMS.toast(res.message || 'Saved.'); await this.load(); this.onChange?.(); },
      });
      form.querySelector('input,select')?.focus();
    }

    renderTable() {
      const add = `<button type="button" class="btn btn-primary btn-sm" data-act="add"><i class="fa-solid fa-plus me-1"></i>Add ${esc(this.def.title === 'Children' ? 'Child' : 'Record')}</button>`;
      if (!this.rows.length) {
        this.container.innerHTML = `<div class="empty-state"><i class="fa-solid ${esc(this.def.icon)}"></i><h3>No ${esc(this.def.title.toLowerCase())} records yet</h3>
          <p class="mb-3">Add as many entries as apply.</p>${add}</div>`;
      } else {
        const head = this.listCols.map(([, f]) => `<th>${esc(f.label)}</th>`).join('');
        const body = this.rows.map((r) => `<tr>${this.listCols.map(([n, f]) => `<td>${displayValue(f, r[n])}</td>`).join('')}
          <td class="table-actions"><button type="button" class="btn btn-sm btn-outline-primary btn-icon" data-edit="${r[this.def.pk]}" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
          ${this.canDelete ? `<button type="button" class="btn btn-sm btn-outline-danger btn-icon" data-del="${r[this.def.pk]}" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>` : ''}</td></tr>`).join('');
        this.container.innerHTML = `<div class="d-flex justify-content-end mb-2">${add}</div>
          <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr>${head}<th class="text-end">Actions</th></tr></thead><tbody>${body}</tbody></table></div>`;
        this.container.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => this.openModal(this.rows.find((r) => String(r[this.def.pk]) === b.dataset.edit))));
        this.container.querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', () => this.remove(this.rows.find((r) => String(r[this.def.pk]) === b.dataset.del))));
      }
      this.container.querySelector('[data-act="add"]').addEventListener('click', () => this.openModal(null));
    }

    openModal(row) {
      const values = row || (this.def.slug === 'children' ? { Child_Number: this.meta.next_child_number } : {});
      openFormModal({
        title: `${row ? 'Edit' : 'Add'} ${this.def.title === 'Children' ? 'Child' : this.def.title}`,
        body: renderFields(this.def, values, { idPrefix: 'm_' + this.def.slug }),
        validate: (f) => clientValidate(this.def, f),
        onSubmit: (f) => this.save(f, row ? row[this.def.pk] : null),
      });
    }

    async remove(row) {
      if (!(await EMS.confirm({ title: 'Delete this record?', text: 'This permanently removes the record from the employee profile.', confirmText: 'Delete', danger: true }))) return;
      try {
        const res = await EMS.api('DELETE', `api/records/${this.def.slug}/${row[this.def.pk]}`);
        EMS.toast(res.message || 'Deleted.');
        await this.load();
        this.onChange?.();
      } catch (err) { EMS.error(err); }
    }
  }

  // ---- Personal information --------------------------------------------------------------
  class Personal {
    constructor(container, { def, userId = null, isAdmin = false, onChange = null }) {
      Object.assign(this, { container, def, userId, isAdmin, onChange });
      this.load();
    }

    q() { return this.userId ? `?user_id=${encodeURIComponent(this.userId)}` : ''; }

    async load() {
      this.container.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>';
      try {
        const res = await EMS.api('GET', `api/personal${this.q()}`);
        Object.assign(this, res.data);
        this.record ? this.renderView() : this.renderForm();
      } catch (err) {
        this.container.innerHTML = `<div class="alert alert-danger">${esc(err.message)}</div>`;
      }
    }

    renderView() {
      const r = this.record;
      const groups = {};
      Object.entries(this.def.fields).forEach(([n, f]) => { (groups[f.group] ||= []).push([n, f]); });
      let html = `<div class="d-flex flex-wrap gap-4 align-items-start">
        <div class="text-center">${this.photo ? `<img src="${esc(this.photo)}" class="profile-photo" alt="Employee photo">` : '<div class="profile-photo"><i class="fa-solid fa-user"></i></div>'}</div>
        <div class="flex-grow-1" style="min-width:260px">
          <div class="d-flex justify-content-end mb-1"><button type="button" class="btn btn-outline-primary btn-sm" data-act="edit"><i class="fa-solid fa-pen me-1"></i>Edit Personal Information</button></div>`;
      Object.entries(groups).forEach(([g, fields]) => {
        html += `<div class="form-section-title">${esc(g)}</div><dl class="dl-grid">`
          + fields.map(([n, f]) => `<div><dt>${esc(f.label)}</dt><dd>${displayValue(f, r[n])}</dd></div>`).join('') + '</dl>';
      });
      html += '</div></div>';
      this.container.innerHTML = html;
      this.container.querySelector('[data-act="edit"]').addEventListener('click', () => this.renderForm());
    }

    renderForm() {
      const r = { ...(this.record || {}) };
      const locked = [];
      if (this.identity) {
        r.Mobile_No = this.identity.Cellphone_No;
        r.Email_Address = this.identity.Email_Address;
        locked.push('Mobile_No', 'Email_Address');
      }
      const sameBox = `<div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="sameAddress">
        <label class="form-check-label small" for="sameAddress">Same as residential address</label></div>`;
      const needPhoto = !this.record;
      this.container.innerHTML = `<form novalidate enctype="multipart/form-data">
        <div class="row g-4"><div class="col-lg-3">
          <div class="photo-drop">
            ${this.photo ? `<img src="${esc(this.photo)}" alt="Current photo" id="photoPreview">` : '<div class="placeholder" id="photoPlaceholder"><i class="fa-solid fa-user"></i></div><img alt="" id="photoPreview" hidden>'}
            <label class="form-label d-block" for="Photo">${needPhoto ? 'Photo<span class="req">*</span>' : 'Replace photo'}</label>
            <input class="form-control form-control-sm" type="file" id="Photo" name="Photo" accept="image/jpeg,image/png,image/webp"${needPhoto ? ' required' : ''}>
            <div class="form-text">JPEG, PNG or WebP, up to 5 MB. Passport-style photo recommended; it is resized automatically.</div>
          </div></div>
          <div class="col-lg-9">${renderFields(this.def, r, { idPrefix: 'pi', locked, groupExtras: { 'Permanent Address': sameBox } })}</div></div>
        <div class="d-flex gap-2 justify-content-end mt-4 border-top pt-3">
          ${this.record ? '<button type="button" class="btn btn-light" data-act="cancel">Cancel</button>' : ''}
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save Personal Information</button></div></form>`;
      const form = this.container.querySelector('form');
      form.querySelector('[data-act="cancel"]')?.addEventListener('click', () => this.renderView());

      const file = form.querySelector('#Photo');
      file.addEventListener('change', () => {
        const f = file.files[0];
        if (!f) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) { EMS.showErrors(form, { Photo: 'Only JPEG, PNG or WebP images are accepted.' }); file.value = ''; return; }
        if (f.size > 5 * 1024 * 1024) { EMS.showErrors(form, { Photo: 'The photo must be smaller than 5 MB.' }); file.value = ''; return; }
        const img = form.querySelector('#photoPreview');
        img.src = URL.createObjectURL(f);
        img.hidden = false;
        form.querySelector('#photoPlaceholder')?.remove();
      });

      const same = form.querySelector('#sameAddress');
      const parts = ['No', 'Street', 'Sub_Village', 'Barangay', 'City_Municipality', 'Province', 'Zip_Code'];
      same.addEventListener('change', () => {
        parts.forEach((p) => {
          const dst = form.querySelector(`[name="Permanent_${p}"]`);
          if (same.checked) dst.value = form.querySelector(`[name="Residential_${p}"]`).value;
          dst.readOnly = same.checked;
        });
      });

      EMS.ajaxForm(form, {
        path: 'api/personal',
        busyLabel: 'Saving…',
        body: (f) => { const fd = new FormData(f); if (this.userId) fd.append('user_id', this.userId); return fd; },
        validate: (f) => {
          const e = clientValidate(this.def, f);
          if (needPhoto && !file.files.length) e.Photo = 'A photo is required.';
          return e;
        },
        onSuccess: async (res) => { EMS.toast(res.message || 'Saved.'); await this.load(); this.onChange?.(); },
      });
    }
  }

  // ---- Administrator cross-employee table -----------------------------------------------------
  /** All rows of one module across employees, with add/edit/delete (admin). */
  function crossTable(tableEl, { def, employees, onReload }) {
    const listCols = Object.entries(def.fields).filter(([, f]) => f.list);
    const empOptions = (sel) => employees.map((e) => `<option value="${e.User_ID}"${String(e.User_ID) === String(sel) ? ' selected' : ''}>${esc((e.Full_Name || e.UserName) + (e.DOJ_ID ? ' — ' + e.DOJ_ID : ' (admin)'))}</option>`).join('');
    const empField = (sel, disabled) => `<div class="mb-3"><label class="form-label" for="x_user">Employee<span class="req">*</span></label>
      <select class="form-select" id="x_user" name="user_id" required${disabled ? ' disabled' : ''}><option value="">Select employee…</option>${empOptions(sel)}</select>
      ${disabled ? `<input type="hidden" name="user_id" value="${esc(sel)}">` : ''}</div>`;

    const columns = [
      { data: null, title: 'Employee', render: (r) => `<a href="${EMS.url('/admin/employees/' + r.User_ID)}" class="fw-semibold">${esc(r.Employee_Name || r.UserName)}</a><div class="small text-muted">${esc(r.DOJ_ID || r.UserName)}</div>` },
      ...listCols.map(([n, f]) => ({ data: n, title: esc(f.label), render: (v, t) => (t === 'display' ? displayValue(f, v) : v) })),
      { data: null, orderable: false, searchable: false, className: 'table-actions', title: 'Actions',
        render: (r) => `<button type="button" class="btn btn-sm btn-outline-primary btn-icon" data-edit="${r[def.pk]}" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="btn btn-sm btn-outline-danger btn-icon" data-del="${r[def.pk]}" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>` },
    ];
    let rows = [];
    const dt = new DataTable(tableEl, { ...EMS.dtDefaults, data: [], columns, order: [] });
    const reload = async () => {
      try {
        const res = await EMS.api('GET', `api/admin/records/${def.slug}`);
        rows = res.data.rows;
        dt.clear().rows.add(rows).draw(false);
        onReload?.(rows);
      } catch (err) { EMS.error(err); }
    };
    const open = (row) => openFormModal({
      title: `${row ? 'Edit' : 'Add'} ${def.title === 'Children' ? 'Child' : def.title}`,
      body: empField(row?.User_ID, !!row) + renderFields(def, row || {}, { idPrefix: 'x_' + def.slug }),
      validate: (f) => { const e = clientValidate(def, f); if (!f.querySelector('[name="user_id"]').value) e.user_id = 'Select an employee.'; return e; },
      onSubmit: async (f) => {
        const body = EMS.formObject(f);
        const res = row ? await EMS.api('PUT', `api/records/${def.slug}/${row[def.pk]}`, body) : await EMS.api('POST', `api/records/${def.slug}`, body);
        EMS.toast(res.message || 'Saved.');
        await reload();
      },
    });
    tableEl.addEventListener('click', async (e) => {
      const ed = e.target.closest('[data-edit]');
      const de = e.target.closest('[data-del]');
      if (ed) open(rows.find((r) => String(r[def.pk]) === ed.dataset.edit));
      if (de) {
        const row = rows.find((r) => String(r[def.pk]) === de.dataset.del);
        if (!(await EMS.confirm({ title: 'Delete this record?', text: `This permanently removes the ${def.title.toLowerCase()} record of ${row.Employee_Name || row.UserName}.`, confirmText: 'Delete', danger: true }))) return;
        try { const res = await EMS.api('DELETE', `api/records/${def.slug}/${row[def.pk]}`); EMS.toast(res.message); await reload(); } catch (err) { EMS.error(err); }
      }
    });
    reload();
    return { reload, add: () => open(null) };
  }

  EMS.Modules = { renderFields, displayValue, clientValidate, openFormModal, Section, Personal, crossTable };
})();
