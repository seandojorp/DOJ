<?php App\Core\View::share('scripts', ['admin-dashboard.js']); App\Core\View::share('withCharts', true); ?>
<div class="page-head">
  <div>
    <h1>Administrator Dashboard</h1>
    <p class="sub">Overview of preloaded employees, registered accounts and recent activity.</p>
  </div>
  <form class="d-flex gap-2" action="<?= e(url('/admin/employees')) ?>" method="get" role="search" style="min-width:min(100%,360px)">
    <label class="visually-hidden" for="dashSearch">Search employees</label>
    <input class="form-control" type="search" id="dashSearch" name="q" placeholder="Search employees by name, DOJ ID, username…">
    <button class="btn btn-primary" type="submit" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
  </form>
</div>

<div class="row g-3 mb-4" id="statCards">
  <?php foreach ([
      ['preloaded', 'Total Preloaded Employees', 'fa-address-book', 'icon-navy', '/admin/preload-users'],
      ['registered', 'Registered Accounts', 'fa-user-check', 'icon-green', '/admin/preload-users?status=registered'],
      ['unregistered', 'Unregistered Employees', 'fa-user-clock', 'icon-gold', '/admin/preload-users?status=unregistered'],
      ['active', 'Active Accounts', 'fa-toggle-on', 'icon-green', '/admin/accounts'],
      ['inactive', 'Inactive Accounts', 'fa-toggle-off', 'icon-red', '/admin/accounts'],
      ['admins', 'Administrators', 'fa-user-shield', 'icon-gray', '/admin/accounts'],
  ] as [$key, $label, $icon, $cls, $href]): ?>
  <div class="col-6 col-md-4 col-xxl-2">
    <a class="stat text-decoration-none" href="<?= e(url($href)) ?>">
      <span class="icon <?= $cls ?>"><i class="fa-solid <?= $icon ?>"></i></span>
      <span><span class="value d-block" data-stat="<?= $key ?>"><span class="placeholder col-6"></span></span><span class="label"><?= e($label) ?></span></span>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-header">Registration status</div>
      <div class="card-body"><div style="height:240px"><canvas id="chartRegistration" aria-label="Registered versus unregistered employees" role="img"></canvas></div></div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-header">New registrations (6 months)</div>
      <div class="card-body"><div style="height:240px"><canvas id="chartMonthly" aria-label="Registrations per month" role="img"></canvas></div></div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-header">Employee profile completion</div>
      <div class="card-body"><div style="height:240px"><canvas id="chartCompletion" aria-label="Profile completion distribution" role="img"></canvas></div></div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fa-solid fa-user-plus me-2"></i>Recently registered users</span>
        <a href="<?= e(url('/admin/accounts')) ?>" class="btn btn-sm btn-light">All accounts</a></div>
      <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Employee</th><th>DOJ ID</th><th>Status</th><th>Registered</th></tr></thead>
        <tbody id="recentBody"><tr><td colspan="4" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</td></tr></tbody>
      </table></div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fa-solid fa-clock-rotate-left me-2"></i>Recent system activity</span>
        <a href="<?= e(url('/admin/audit-logs')) ?>" class="btn btn-sm btn-light">Audit logs</a></div>
      <ul class="list-group list-group-flush" id="activityList"><li class="list-group-item text-muted text-center py-4">Loading…</li></ul>
    </div>
  </div>
</div>
