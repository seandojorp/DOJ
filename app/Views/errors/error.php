<div class="auth-card text-center">
  <div class="display-5 fw-bold text-navy mb-2"><?= e($status ?? 500) ?></div>
  <h2 class="h4"><?= e(($status ?? 500) === 404 ? 'Page not found' : (($status ?? 500) === 403 ? 'Access denied' : 'Something went wrong')) ?></h2>
  <p class="text-muted"><?= e($message ?? '') ?></p>
  <a href="<?= e(url('/')) ?>" class="btn btn-primary mt-2"><i class="fa-solid fa-house me-2"></i>Go to home</a>
</div>
