<?php App\Core\View::share('scripts', ['admin-audit.js']); ?>
<div class="page-head">
  <div>
    <h1>Audit Logs</h1>
    <p class="sub">Security and data-change events. Logs record who did what and when — never passwords or field values.</p>
  </div>
</div>
<div class="card">
  <div class="card-header">
    <form class="row g-2 align-items-end" id="auditFilters">
      <div class="col-sm-6 col-lg-3"><label class="form-label small" for="fAction">Action</label>
        <select class="form-select form-select-sm" id="fAction" name="action"><option value="">All actions</option>
          <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"><?= e($a) ?></option><?php endforeach; ?></select></div>
      <div class="col-sm-6 col-lg-2"><label class="form-label small" for="fResult">Result</label>
        <select class="form-select form-select-sm" id="fResult" name="result"><option value="">All</option><option value="success">Success</option><option value="failure">Failure</option></select></div>
      <div class="col-sm-6 col-lg-2"><label class="form-label small" for="fFrom">From</label><input type="date" class="form-control form-control-sm" id="fFrom" name="date_from"></div>
      <div class="col-sm-6 col-lg-2"><label class="form-label small" for="fTo">To</label><input type="date" class="form-control form-control-sm" id="fTo" name="date_to"></div>
      <div class="col-lg-3 d-flex gap-2"><button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-filter me-1"></i>Apply</button>
        <button type="reset" class="btn btn-sm btn-light">Clear</button></div>
    </form>
  </div>
  <div class="card-body"><table class="table table-hover w-100" id="auditTable"></table></div>
</div>
