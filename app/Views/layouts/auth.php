<!doctype html>
<html lang="en">
<head>
<?php $withTables = false; include APP_PATH . '/Views/partials/head.php'; ?>
</head>
<body class="auth-body">
<a class="skip-link" href="#main">Skip to main content</a>
<div class="auth-scene" aria-hidden="true">
  <img class="auth-watermark" src="<?= e(asset('img/doj-seal.png')) ?>" alt="">
</div>
<main class="auth-shell" id="main">
  <div class="auth-panel <?= e($cardClass ?? '') ?>">
    <header class="auth-head">
      <img class="auth-seal" src="<?= e(asset('img/doj-seal.png')) ?>" alt="Department of Justice seal" width="96" height="96">
      <div class="auth-eyebrow">Republic of the Philippines</div>
      <div class="auth-org">Department of Justice</div>
      <div class="auth-region"><span>Region 02</span></div>
      <div class="auth-system">Employees Management System</div>
    </header>
    <div class="auth-body-inner">
      <?= $content ?>
    </div>
  </div>
  <footer class="auth-footer">
    <span><i class="fa-solid fa-shield-halved me-1"></i>Authorized personnel only. All activity is logged.</span>
    <span>&copy; <?= date('Y') ?> Department of Justice — Region 02</span>
  </footer>
</main>
<?php $scripts = $scripts ?? []; include APP_PATH . '/Views/partials/scripts.php'; ?>
</body>
</html>
