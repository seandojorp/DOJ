<?php
$c = $completion;
$p = $personal;
$emp = $employment;
$name = $p['Full_Name'] ?? $me['UserName'];
?>
<div class="profile-header mb-4">
  <div class="d-flex flex-wrap gap-4 align-items-center">
    <?php if (!empty($p['has_photo'])): ?>
      <img src="<?= e(url('/photo/' . $me['User_ID'])) ?>" alt="Your photo" class="profile-photo" style="width:96px;height:112px">
    <?php else: ?>
      <div class="profile-photo" style="width:96px;height:112px" aria-hidden="true"><i class="fa-solid fa-user"></i></div>
    <?php endif; ?>
    <div class="grow">
      <div class="meta mb-1">Welcome back,</div>
      <h2><?= e($name) ?></h2>
      <div class="meta mt-2 d-flex flex-wrap gap-3">
        <span><i class="fa-solid fa-id-card me-1"></i>DOJ ID: <strong><?= e($me['DOJ_ID'] ?? '—') ?></strong></span>
        <span><i class="fa-solid fa-user me-1"></i><?= e($me['UserName']) ?></span>
        <?php if ($emp): ?><span><i class="fa-solid fa-briefcase me-1"></i><?= e($emp['Current_Position']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="text-center" style="min-width:150px">
      <div class="display-6 fw-bold" style="color:var(--gold)"><?= (int) $c['percent'] ?>%</div>
      <div class="meta">profile complete</div>
    </div>
  </div>
</div>

<?php if ($c['percent'] < 100): ?>
<div class="alert alert-gold d-flex flex-wrap gap-2 align-items-center justify-content-between">
  <span><i class="fa-solid fa-circle-info me-2"></i>Your profile is <?= (int) $c['percent'] ?>% complete. Please complete the remaining required sections.</span>
  <a href="<?= e(url('/user/profile')) ?>" class="btn btn-sm btn-primary">Continue my profile</a>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fa-solid fa-id-card me-2"></i>Personal information</span>
        <a class="btn btn-sm btn-light" href="<?= e(url('/user/profile/personal')) ?>"><?= $p ? 'View / Edit' : 'Add' ?></a></div>
      <div class="card-body">
        <?php if ($p): ?>
        <dl class="dl-grid">
          <div><dt>Full name</dt><dd><?= e($p['Full_Name']) ?></dd></div>
          <div><dt>Date of birth</dt><dd><?= e(date('M j, Y', strtotime($p['Birthday']))) ?></dd></div>
          <div><dt>Sex</dt><dd><?= e($p['Sex']) ?></dd></div>
          <div><dt>Civil status</dt><dd><?= e($p['Civil_Status']) ?></dd></div>
          <div><dt>Mobile</dt><dd><?= e($p['Mobile_No']) ?></dd></div>
          <div><dt>Email</dt><dd><?= e($p['Email_Address']) ?></dd></div>
          <div><dt>Residence</dt><dd><?= e($p['Residential_City_Municipality'] . ', ' . $p['Residential_Province']) ?></dd></div>
        </dl>
        <?php else: ?>
        <div class="empty-state py-3"><i class="fa-solid fa-id-card"></i><h3>No personal information yet</h3>
          <a class="btn btn-primary btn-sm mt-2" href="<?= e(url('/user/profile/personal')) ?>">Add personal information</a></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center"><span><i class="fa-solid fa-building-columns me-2"></i>Employment information</span>
        <a class="btn btn-sm btn-light" href="<?= e(url('/user/profile/employment')) ?>"><?= $emp ? 'View / Edit' : 'Add' ?></a></div>
      <div class="card-body">
        <?php if ($emp): ?>
        <dl class="dl-grid">
          <div><dt>Current position</dt><dd><?= e($emp['Current_Position']) ?></dd></div>
          <div><dt>Salary grade</dt><dd><?= e($emp['Salary_Grade']) ?></dd></div>
          <div><dt>Original office</dt><dd><?= e($emp['Original_Office']) ?></dd></div>
          <div><dt>Reassignment office</dt><dd><?= e($emp['Reassignment_Office']) ?></dd></div>
          <div><dt>Detailed office</dt><dd><?= e($emp['Detailed_Office']) ?></dd></div>
          <div><dt>Date of entry</dt><dd><?= e(date('M j, Y', strtotime($emp['Date_of_Entry']))) ?></dd></div>
          <div><dt>Latest promotion</dt><dd><?= e(date('M j, Y', strtotime($emp['Date_Latest_Promotion']))) ?></dd></div>
        </dl>
        <?php else: ?>
        <div class="empty-state py-3"><i class="fa-solid fa-building-columns"></i><h3>No employment information yet</h3>
          <a class="btn btn-primary btn-sm mt-2" href="<?= e(url('/user/profile/employment')) ?>">Add employment information</a></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-list-check me-2"></i>Profile sections</div>
      <div class="card-body">
        <div class="row g-3">
        <?php foreach ($c['sections'] as $slug => $s): if ($s['hidden']) continue; ?>
          <div class="col-sm-6 col-xl-3">
            <a class="section-tile" href="<?= e(url('/user/profile/' . $slug)) ?>">
              <span class="icon <?= $s['complete'] ? 'icon-green' : ($s['required'] ? 'icon-gold' : 'icon-gray') ?>"><i class="fa-solid <?= e($s['icon']) ?>"></i></span>
              <span><span class="t d-block small"><?= e($s['title']) ?></span><span class="s"><?= $s['complete'] ? 'Completed' : ($s['required'] ? 'Required' : 'Optional') ?></span></span>
            </a>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
