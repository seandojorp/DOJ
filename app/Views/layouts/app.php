<?php
use App\Services\Modules;

/** @var array $me */
$isAdmin = ($me['Role'] ?? '') === 'admin';
$active = $active ?? '';
$prefix = $isAdmin ? '/admin/records/' : '/user/profile/';
$modules = Modules::navigation();
$initial = strtoupper(mb_substr((string) ($me['UserName'] ?? '?'), 0, 1));

$link = static function (string $href, string $icon, string $label, string $key) use ($active): string {
    $is = $active === $key;
    return '<a class="nav-link' . ($is ? ' active' : '') . '" href="' . e(url($href)) . '"' . ($is ? ' aria-current="page"' : '')
        . ' title="' . e($label) . '"><i class="fa-solid ' . e($icon) . '" aria-hidden="true"></i><span>' . e($label) . '</span></a>';
};
?>
<!doctype html>
<html lang="en">
<head>
<?php $withTables = true; include APP_PATH . '/Views/partials/head.php'; ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<aside class="app-sidebar" id="sidebar" aria-label="Main navigation">
  <a class="brand" href="<?= e(url($isAdmin ? '/admin/dashboard' : '/user/dashboard')) ?>">
    <img class="seal" src="<?= e(asset('img/doj-seal.png')) ?>" alt="" width="40" height="40">
    <span class="name"><strong>DOJ Region 02</strong><small>Employees Management</small></span>
  </a>
  <nav>
    <?php if ($isAdmin): ?>
      <?= $link('/admin/dashboard', 'fa-gauge-high', 'Dashboard', 'dashboard') ?>
      <div class="nav-heading">Administration</div>
      <?= $link('/admin/employees', 'fa-users', 'Employee Management', 'employees') ?>
      <?= $link('/admin/preload-users', 'fa-address-book', 'Preloaded Users', 'preload') ?>
      <?= $link('/admin/accounts', 'fa-user-shield', 'Registered Accounts', 'accounts') ?>
      <div class="nav-heading">Employee Information</div>
    <?php else: ?>
      <?= $link('/user/dashboard', 'fa-gauge-high', 'Dashboard', 'dashboard') ?>
      <?= $link('/user/profile', 'fa-id-badge', 'My Employee Profile', 'profile') ?>
      <div class="nav-heading">My Information</div>
    <?php endif; ?>
    <?php foreach ($modules as $slug => $m): ?>
      <?= $link($prefix . $slug, $m['icon'], $m['title'], 'mod-' . $slug) ?>
    <?php endforeach; ?>
    <div class="nav-heading">Account</div>
    <?php if ($isAdmin): ?>
      <?= $link('/admin/audit-logs', 'fa-clipboard-list', 'Audit Logs', 'audit') ?>
      <?= $link('/admin/settings', 'fa-gear', 'Account Settings', 'settings') ?>
    <?php else: ?>
      <?= $link('/user/settings', 'fa-key', 'Change Password', 'settings') ?>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/logout')) ?>" class="m-0" data-confirm-logout>
      <?= csrf_field() ?>
      <button type="submit" class="nav-link logout" title="Logout"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Logout</span></button>
    </form>
  </nav>
</aside>
<div class="sidebar-backdrop" data-sidebar-close></div>

<header class="app-topbar">
  <button class="btn btn-light btn-icon" type="button" id="sidebarToggle" aria-controls="sidebar" aria-label="Toggle navigation">
    <i class="fa-solid fa-bars"></i>
  </button>
  <h1 class="page-title flex-grow-1"><?= e($title ?? '') ?></h1>
  <div class="dropdown">
    <button class="user-chip dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <span class="avatar" aria-hidden="true"><?= e($initial) ?></span>
      <span class="d-none d-sm-inline small fw-semibold"><?= e($me['UserName'] ?? '') ?></span>
      <span class="role-badge <?= $isAdmin ? 'admin' : '' ?>"><?= $isAdmin ? 'Admin' : 'Employee' ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
      <li><h6 class="dropdown-header">Signed in as <?= e($me['UserName'] ?? '') ?></h6></li>
      <?php if (!empty($me['DOJ_ID'])): ?><li><span class="dropdown-item-text small text-muted">DOJ ID: <?= e($me['DOJ_ID']) ?></span></li><?php endif; ?>
      <li><a class="dropdown-item" href="<?= e(url($isAdmin ? '/admin/settings' : '/user/settings')) ?>"><i class="fa-solid fa-key me-2"></i>Change Password</a></li>
      <li><hr class="dropdown-divider"></li>
      <li>
        <form method="post" action="<?= e(url('/logout')) ?>" class="m-0" data-confirm-logout>
          <?= csrf_field() ?>
          <button class="dropdown-item text-doj-red" type="submit"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</button>
        </form>
      </li>
    </ul>
  </div>
</header>

<main class="app-main" id="main" tabindex="-1">
  <div class="container-fluid px-0">
    <?= $content ?>
  </div>
</main>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toasts" aria-live="polite" aria-atomic="true"></div>
<?php include APP_PATH . '/Views/partials/scripts.php'; ?>
</body>
</html>
