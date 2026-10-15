<?php App\Core\View::share('scripts', ['modules.js', 'section.js']); ?>
<div class="page-head">
  <div>
    <h1><i class="fa-solid <?= e($module['icon']) ?> me-2 text-doj-red"></i><?= e($module['title']) ?></h1>
    <p class="sub"><?= $module['single'] ? 'Keep this information complete and up to date.' : 'Add an entry for each item that applies to you.' ?>
      All fields are required — type <strong>N/A</strong> where a field does not apply.</p>
  </div>
  <a href="<?= e(url('/user/profile')) ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left me-2"></i>My Employee Profile</a>
</div>
<?php foreach ($defs as $i => $d): ?>
<div class="card mb-4">
  <div class="card-header"><i class="fa-solid <?= e($d['icon']) ?> me-2"></i><?= e($d['title']) ?></div>
  <div class="card-body" data-section="<?= e($d['slug']) ?>"></div>
</div>
<?php endforeach; ?>
<script type="application/json" id="page-data"><?= json_for_html(['defs' => $defs, 'isAdmin' => false]) ?></script>
