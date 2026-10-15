<?php use App\Core\Config; ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(App\Core\Csrf::token()) ?>">
<meta name="base-url" content="<?= e(base_path()) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') ?><?= e(Config::get('app.short', 'DOJ Region 02 EMS')) ?></title>
<link rel="icon" href="<?= e(asset('img/favicon-64.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fontawesome/css/all.min.css')) ?>">
<?php if (!empty($withTables)): ?>
<link rel="stylesheet" href="<?= e(asset('vendor/datatables/dataTables.bootstrap5.min.css')) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
