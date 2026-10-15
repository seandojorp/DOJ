<?php App\Core\View::share('scripts', ['auth.js']); App\Core\View::share('cardClass', 'wide'); ?>
<div class="auth-card">
  <h2>Employee Account Registration</h2>
  <p class="lead-sm">Your DOJ ID, cellphone number and email address must exactly match the record HR has preloaded for you.</p>
  <form id="registerForm" novalidate>
    <div class="form-section-title">Employee verification</div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="doj_id">DOJ ID<span class="req" aria-hidden="true">*</span></label>
        <input type="text" class="form-control" id="doj_id" name="doj_id" required maxlength="50" autocomplete="off" autofocus>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="cellphone">Cellphone Number<span class="req" aria-hidden="true">*</span></label>
        <input type="tel" class="form-control" id="cellphone" name="cellphone" required maxlength="16" placeholder="09XXXXXXXXX" autocomplete="tel" inputmode="tel">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="email">Email Address<span class="req" aria-hidden="true">*</span></label>
        <input type="email" class="form-control" id="email" name="email" required maxlength="50" autocomplete="email">
      </div>
    </div>
    <div class="form-section-title">Sign-in credentials</div>
    <div class="mb-3">
      <label class="form-label" for="username">Username<span class="req" aria-hidden="true">*</span></label>
      <input type="text" class="form-control" id="username" name="username" required minlength="4" maxlength="50" autocomplete="username"
             pattern="[A-Za-z0-9][A-Za-z0-9._\-]{3,49}">
      <div class="form-text">4–50 characters: letters, numbers, dot, dash or underscore.</div>
    </div>
    <div class="row g-3">
      <div class="col-md-6"><?php $pf = ['id' => 'password', 'name' => 'password', 'label' => 'Password', 'meter' => true]; include APP_PATH . '/Views/partials/password_field.php'; ?></div>
      <div class="col-md-6"><?php $pf = ['id' => 'confirm_password', 'name' => 'confirm_password', 'label' => 'Confirm Password', 'match' => 'password']; include APP_PATH . '/Views/partials/password_field.php'; ?></div>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2 mt-2"><i class="fa-solid fa-user-plus me-2"></i>Register Account</button>
  </form>
  <p class="text-center small mt-4 mb-0">Already registered? <a href="<?= e(url('/login')) ?>" class="fw-semibold">Sign in</a></p>
</div>
