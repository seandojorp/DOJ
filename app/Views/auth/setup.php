<?php App\Core\View::share('scripts', ['auth.js']); ?>
<div class="auth-card">
  <span class="badge text-bg-warning mb-3"><i class="fa-solid fa-wrench me-1"></i> First-run setup</span>
  <h2>Create Administrator Account</h2>
  <p class="lead-sm">No administrator has been configured yet. This one-time setup creates the first administrator account. It cannot be run again once completed.</p>
  <form id="setupForm" novalidate data-endpoint="api/setup">
    <div class="mb-3">
      <label class="form-label" for="username">Username<span class="req" aria-hidden="true">*</span></label>
      <input type="text" class="form-control" id="username" name="username" required minlength="4" maxlength="50" autocomplete="username" autofocus
             pattern="[A-Za-z0-9][A-Za-z0-9._\-]{3,49}">
      <div class="form-text">4–50 characters: letters, numbers, dot, dash or underscore.</div>
    </div>
    <?php $pf = ['id' => 'password', 'name' => 'password', 'label' => 'Password', 'meter' => true]; include APP_PATH . '/Views/partials/password_field.php'; ?>
    <?php $pf = ['id' => 'confirm_password', 'name' => 'confirm_password', 'label' => 'Confirm Password', 'match' => 'password']; include APP_PATH . '/Views/partials/password_field.php'; ?>
    <div class="alert alert-gold small d-flex gap-2"><i class="fa-solid fa-circle-info mt-1"></i>
      <span>Store these credentials securely. Additional administrators can be created later from <strong>Registered Accounts</strong>.</span></div>
    <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-user-shield me-2"></i>Create Administrator</button>
  </form>
</div>
