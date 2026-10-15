<?php
App\Core\View::share('scripts', ['modules.js', 'section.js', 'admin-profile.js']);
$emp = $employee;
$c = $completion;
$defs = array_values($modules);
?>
<div class="mb-3"><a href="<?= e(url('/admin/employees')) ?>" class="small"><i class="fa-solid fa-arrow-left me-1"></i>Employee Management</a></div>
<div class="profile-header mb-4">
  <div class="d-flex flex-wrap gap-4 align-items-center">
    <img src="<?= e(url('/photo/' . $emp['User_ID'])) ?>" alt="" class="profile-photo" style="width:96px;height:112px" data-photo-fallback>
    <div class="grow">
      <h2><?= e($emp['Full_Name'] ?: '(No personal information yet)') ?></h2>
      <div class="meta mt-2 d-flex flex-wrap gap-3">
        <span>DOJ ID: <strong><?= e($emp['DOJ_ID'] ?? '—') ?></strong></span>
        <span>Username: <strong><?= e($emp['UserName']) ?></strong></span>
        <span>Role: <strong><?= $emp['Role'] === 'admin' ? 'Administrator' : 'User' ?></strong></span>
        <span>Status: <strong><?= $emp['Account_Status'] === 'active' ? 'Active' : 'Inactive' ?></strong></span>
        <span>Last login: <strong><?= e($emp['Last_Login'] ? date('M j, Y g:i A', strtotime($emp['Last_Login'])) : 'Never') ?></strong></span>
      </div>
    </div>
    <div class="text-center" style="min-width:140px">
      <div class="display-6 fw-bold" style="color:var(--gold)" id="completionPct"><?= (int) $c['percent'] ?>%</div>
      <div class="meta">profile complete</div>
    </div>
  </div>
</div>

<div class="card">
  <ul class="nav nav-tabs-scroll px-2" role="tablist">
    <?php $first = true; foreach ($modules as $slug => $m): if (!empty($m['hidden'])) continue; ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link<?= $first ? ' active' : '' ?>" id="tab-<?= e($slug) ?>" data-bs-toggle="tab" data-bs-target="#pane-<?= e($slug) ?>" type="button" role="tab"
                aria-controls="pane-<?= e($slug) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>">
          <i class="fa-solid <?= e($m['icon']) ?> me-1"></i><?= e($m['title']) ?>
          <?php $s = $c['sections'][$slug]; ?>
          <i class="fa-solid <?= $s['complete'] ? 'fa-circle-check text-success' : ($s['required'] ? 'fa-circle-exclamation text-warning' : '') ?> ms-1 small" data-tab-state="<?= e($slug) ?>"></i>
        </button>
      </li>
    <?php $first = false; endforeach; ?>
  </ul>
  <div class="tab-content card-body">
    <?php $first = true; foreach ($modules as $slug => $m): if (!empty($m['hidden'])) continue; ?>
      <div class="tab-pane fade<?= $first ? ' show active' : '' ?>" id="pane-<?= e($slug) ?>" role="tabpanel" aria-labelledby="tab-<?= e($slug) ?>" tabindex="0">
        <div data-section="<?= e($slug) ?>"></div>
        <?php if (!empty($m['sub'])): $sub = $modules[$m['sub']]; ?>
          <h3 class="h6 mt-4 pt-3 border-top"><i class="fa-solid <?= e($sub['icon']) ?> me-2"></i><?= e($sub['title']) ?></h3>
          <div data-section="<?= e($m['sub']) ?>"></div>
        <?php endif; ?>
      </div>
    <?php $first = false; endforeach; ?>
  </div>
</div>
<script type="application/json" id="page-data"><?= json_for_html(['defs' => $defs, 'userId' => $emp['User_ID'], 'isAdmin' => true]) ?></script>
