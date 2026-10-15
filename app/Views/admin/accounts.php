<?php App\Core\View::share('scripts', ['modules.js', 'admin-accounts.js']); ?>
<div class="page-head">
  <div>
    <h1>Registered User Accounts</h1>
    <p class="sub">Manage sign-in accounts, roles, registration identity and access. Accounts with employee records are deactivated rather than deleted.</p>
  </div>
  <button type="button" class="btn btn-primary" id="btnAddAccount"><i class="fa-solid fa-user-plus me-2"></i>Create Account</button>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat"><span class="icon icon-navy"><i class="fa-solid fa-users"></i></span><span><span class="value d-block" data-s="total"><?= (int) $stats['total'] ?></span><span class="label">Accounts</span></span></div></div>
  <div class="col-6 col-md-3"><div class="stat"><span class="icon icon-green"><i class="fa-solid fa-toggle-on"></i></span><span><span class="value d-block" data-s="active"><?= (int) $stats['active'] ?></span><span class="label">Active</span></span></div></div>
  <div class="col-6 col-md-3"><div class="stat"><span class="icon icon-red"><i class="fa-solid fa-toggle-off"></i></span><span><span class="value d-block" data-s="inactive"><?= (int) $stats['inactive'] ?></span><span class="label">Inactive</span></span></div></div>
  <div class="col-6 col-md-3"><div class="stat"><span class="icon icon-gold"><i class="fa-solid fa-user-shield"></i></span><span><span class="value d-block" data-s="admins"><?= (int) $stats['admins'] ?></span><span class="label">Administrators</span></span></div></div>
</div>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <span>Accounts</span>
    <div class="d-flex gap-2">
      <select class="form-select form-select-sm" id="roleFilter" aria-label="Filter by role" style="width:auto"><option value="">All roles</option><option value="admin">Administrators</option><option value="user">Users</option></select>
      <select class="form-select form-select-sm" id="statusFilter" aria-label="Filter by status" style="width:auto"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
  </div>
  <div class="card-body"><table class="table table-hover w-100" id="accountsTable"></table></div>
</div>
<script type="application/json" id="page-data"><?= json_for_html(['me' => $me['User_ID']]) ?></script>
