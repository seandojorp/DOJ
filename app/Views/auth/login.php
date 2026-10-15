<?php App\Core\View::share('scripts', ['auth.js']); ?>
<div class="auth-intro">
  <h1>Welcome back</h1>
  <p>Sign in with your EMS username and password.</p>
</div>

<?php if (!empty($expired)): ?>
  <div class="auth-alert warn" role="alert"><i class="fa-solid fa-clock"></i><span>Your session has ended. Please sign in again.</span></div>
<?php endif; ?>
<?php if (!empty($notice)): ?>
  <div class="auth-alert ok" role="status"><i class="fa-solid fa-circle-check"></i><span><?= e($notice) ?></span></div>
<?php endif; ?>

<form id="loginForm" novalidate>
  <div class="field">
    <label for="username">Username</label>
    <div class="field-control">
      <i class="fa-regular fa-user field-icon" aria-hidden="true"></i>
      <input type="text" class="form-control" id="username" name="username" required maxlength="50"
             autocomplete="username" autofocus placeholder="Enter your username">
    </div>
  </div>

  <div class="field">
    <label for="password">Password</label>
    <div class="field-control">
      <i class="fa-solid fa-lock field-icon" aria-hidden="true"></i>
      <input type="password" class="form-control" id="password" name="password" required maxlength="72"
             autocomplete="current-password" placeholder="Enter your password">
      <button class="field-action" type="button" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
        <i class="fa-solid fa-eye"></i>
      </button>
    </div>
    <div class="caps-hint" id="capsHint" hidden><i class="fa-solid fa-triangle-exclamation me-1"></i>Caps Lock is on</div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-4 gap-2 flex-wrap">
    <div class="form-check m-0">
      <input class="form-check-input" type="checkbox" id="rememberUsername">
      <label class="form-check-label" for="rememberUsername">Remember my username</label>
    </div>
    <button type="button" class="link-btn" id="forgotLink">Forgot password?</button>
  </div>

  <button type="submit" class="btn btn-auth w-100">
    <span>Sign in</span><i class="fa-solid fa-arrow-right-long ms-2" aria-hidden="true"></i>
  </button>
</form>

<div class="auth-divider"><span>New to the system?</span></div>
<a class="btn btn-auth-outline w-100" href="<?= e(url('/register')) ?>"><i class="fa-solid fa-user-plus me-2"></i>Register your employee account</a>
