<?php App\Core\View::share('scripts', ['modules.js', 'admin-records.js']); ?>
<div class="page-head">
  <div>
    <h1><i class="fa-solid <?= e($module['icon']) ?> me-2 text-doj-red"></i><?= e($module['title']) ?></h1>
    <p class="sub">All employees' <?= e(strtolower($module['title'])) ?> records. To work on a single employee, open their profile from Employee Management.</p>
  </div>
  <?php if (($module['special'] ?? '') !== 'personal'): ?>
  <button type="button" class="btn btn-primary" data-add="<?= e($module['slug']) ?>"><i class="fa-solid fa-plus me-2"></i>Add Record</button>
  <?php endif; ?>
</div>
<?php foreach ($defs as $d): ?>
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fa-solid <?= e($d['icon']) ?> me-2"></i><?= e($d['title']) ?></span>
    <?php if ($d['slug'] !== $module['slug']): ?><button type="button" class="btn btn-sm btn-outline-primary" data-add="<?= e($d['slug']) ?>"><i class="fa-solid fa-plus me-1"></i>Add</button><?php endif; ?></div>
  <div class="card-body"><table class="table table-hover w-100" data-table="<?= e($d['slug']) ?>"></table></div>
</div>
<?php endforeach; ?>
<script type="application/json" id="page-data"><?= json_for_html(['defs' => $defs, 'employees' => $employees]) ?></script>
