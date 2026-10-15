<?php App\Core\View::share('scripts', ['modules.js', 'admin-preload.js']); ?>
<div class="page-head">
  <div>
    <h1>Preloaded Users</h1>
    <p class="sub">Employee identity records from HR. Employees can register only when their DOJ ID, cellphone and email match one record here.</p>
  </div>
  <button type="button" class="btn btn-primary" id="btnAddPreload"><i class="fa-solid fa-plus me-2"></i>Add Preloaded User</button>
</div>
<div class="row g-3 mb-4">
  <div class="col-sm-4"><div class="stat"><span class="icon icon-navy"><i class="fa-solid fa-address-book"></i></span>
    <span><span class="value d-block" id="statTotal"><?= (int) $stats['total'] ?></span><span class="label">Total preloaded</span></span></div></div>
  <div class="col-sm-4"><div class="stat"><span class="icon icon-green"><i class="fa-solid fa-user-check"></i></span>
    <span><span class="value d-block" id="statRegistered"><?= (int) $stats['registered'] ?></span><span class="label">Registered</span></span></div></div>
  <div class="col-sm-4"><div class="stat"><span class="icon icon-gold"><i class="fa-solid fa-user-clock"></i></span>
    <span><span class="value d-block" id="statUnregistered"><?= (int) $stats['unregistered'] ?></span><span class="label">Not registered</span></span></div></div>
</div>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <span>Preloaded employee records</span>
    <div class="d-flex align-items-center gap-2">
      <label for="statusFilter" class="small text-muted text-nowrap">Registration status</label>
      <select class="form-select form-select-sm" id="statusFilter" style="width:auto">
        <option value="">All</option><option value="registered">Registered</option><option value="unregistered">Not Registered</option>
      </select>
    </div>
  </div>
  <div class="card-body">
    <table class="table table-hover w-100" id="preloadTable"></table>
  </div>
</div>
