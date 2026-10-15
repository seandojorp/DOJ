<?php App\Core\View::share('scripts', ['admin-employees.js']); ?>
<div class="page-head">
  <div>
    <h1>Employee Management</h1>
    <p class="sub">Search registered employees and open a profile to view or edit every section of their record.</p>
  </div>
  <a class="btn btn-outline-primary" href="<?= e(url('/admin/preload-users')) ?>"><i class="fa-solid fa-address-book me-2"></i>Preloaded Users</a>
</div>
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <span>Registered employees</span>
    <select class="form-select form-select-sm" id="completionFilter" aria-label="Filter by profile completion" style="width:auto">
      <option value="">Any completion</option><option value="incomplete">Incomplete profiles</option><option value="complete">Complete profiles</option>
    </select>
  </div>
  <div class="card-body"><table class="table table-hover w-100" id="employeesTable"></table></div>
</div>
<script type="application/json" id="page-data"><?= json_for_html(['q' => $q]) ?></script>
