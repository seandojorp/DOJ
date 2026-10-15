<?php
App\Core\View::share('scripts', ['settings.js']);
$isAdmin = $me['Role'] === 'admin';
$ro = static fn(string $id, string $label, ?string $value) =>
    '<div class="col-md-6"><label class="form-label" for="' . e($id) . '">' . e($label) . '</label>'
    . '<input type="text" class="form-control" id="' . e($id) . '" value="' . e($value ?: '—') . '" readonly aria-readonly="true"></div>';
?>
<div class="page-head">
  <div>
    <h1>Account Settings</h1>
    <p class="sub">Your account details are managed by the system administrator. You can change your password here.</p>
  </div>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="fa-solid fa-id-badge me-2"></i>Account details</div>
      <div class="card-body">
        <div class="row g-3">
          <?= $ro('acc_username', 'Username', $me['UserName']) ?>
          <?= $ro('acc_role', 'Role', $isAdmin ? 'Administrator' : 'Employee (User)') ?>
          <?= $ro('acc_doj', 'DOJ ID', $me['DOJ_ID'] ?? null) ?>
          <?= $ro('acc_cell', 'Registered Cellphone Number', $me['Cellphone_No'] ?? null) ?>
          <div class="col-12"><?php
            echo '<label class="form-label" for="acc_email">Registered Email Address</label>'
               . '<input type="text" class="form-control" id="acc_email" value="' . e(($me['Email_Address'] ?? '') ?: '—') . '" readonly aria-readonly="true">';
          ?></div>
        </div>
        <p class="field-lock mt-3 mb-0"><i class="fa-solid fa-lock me-1"></i>
          <?= $isAdmin ? 'Account details can be changed from Registered Accounts.' : 'To correct these details, please contact the system administrator.' ?></p>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="fa-solid fa-key me-2"></i>Change password</div>
      <div class="card-body">
        <form id="passwordForm" novalidate>
          <?php $pf = ['id' => 'current_password', 'name' => 'current_password', 'label' => 'Current Password', 'autocomplete' => 'current-password']; include APP_PATH . '/Views/partials/password_field.php'; ?>
          <?php $pf = ['id' => 'new_password', 'name' => 'new_password', 'label' => 'New Password', 'meter' => true]; include APP_PATH . '/Views/partials/password_field.php'; ?>
          <?php $pf = ['id' => 'confirm_password', 'name' => 'confirm_password', 'label' => 'Confirm New Password', 'match' => 'new_password']; include APP_PATH . '/Views/partials/password_field.php'; ?>
          <p class="form-text"><i class="fa-solid fa-circle-info me-1"></i>Changing your password signs out every other session using this account.</p>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>
