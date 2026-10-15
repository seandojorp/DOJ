<?php
$c = $completion;
?>
<div class="page-head">
  <div>
    <h1>My Employee Profile</h1>
    <p class="sub">Your Personal Data Sheet, organized by section. Required sections count toward profile completion.</p>
  </div>
</div>
<div class="card mb-4">
  <div class="card-body d-flex flex-wrap align-items-center gap-4">
    <div class="flex-grow-1" style="min-width:240px">
      <div class="d-flex justify-content-between small mb-1"><span class="fw-semibold">Profile completion</span><span><?= (int) $c['done'] ?> of <?= (int) $c['total'] ?> required sections</span></div>
      <div class="progress" role="progressbar" aria-label="Profile completion" aria-valuenow="<?= (int) $c['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar <?= $c['percent'] === 100 ? 'done' : '' ?>" style="width: <?= (int) $c['percent'] ?>%"></div>
      </div>
    </div>
    <div class="display-6 fw-bold text-navy"><?= (int) $c['percent'] ?>%</div>
  </div>
</div>
<div class="row g-3">
  <?php foreach ($c['sections'] as $slug => $s): if ($s['hidden']) continue; ?>
  <div class="col-sm-6 col-xl-4">
    <a class="section-tile" href="<?= e(url('/user/profile/' . $slug)) ?>">
      <span class="icon <?= $s['complete'] ? 'icon-green' : ($s['required'] ? 'icon-gold' : 'icon-gray') ?>"><i class="fa-solid <?= e($s['icon']) ?>"></i></span>
      <span class="flex-grow-1">
        <span class="t d-block"><?= e($s['title']) ?></span>
        <span class="s"><?php
          if ($s['complete']) echo e($s['count'] . ' record' . ($s['count'] === 1 ? '' : 's') . ' on file');
          else echo $s['required'] ? 'Not yet completed' : 'Optional — none added';
        ?></span>
      </span>
      <i class="fa-solid <?= $s['complete'] ? 'fa-circle-check text-success' : 'fa-chevron-right text-muted' ?>"></i>
    </a>
  </div>
  <?php endforeach; ?>
</div>
