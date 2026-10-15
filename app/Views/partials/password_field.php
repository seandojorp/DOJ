<?php
/** Expects: $pf = ['id'=>, 'name'=>, 'label'=>, 'meter'=>bool, 'match'=>?string, 'autocomplete'=>string, 'help'=>?string] */
$pf += ['meter' => false, 'match' => null, 'autocomplete' => 'new-password', 'help' => null];
?>
<div class="mb-3">
  <label class="form-label" for="<?= e($pf['id']) ?>"><?= e($pf['label']) ?><span class="req" aria-hidden="true">*</span></label>
  <div class="input-group">
    <input type="password" class="form-control" id="<?= e($pf['id']) ?>" name="<?= e($pf['name']) ?>" required maxlength="72"
           autocomplete="<?= e($pf['autocomplete']) ?>"<?= $pf['meter'] ? ' data-password-meter' : '' ?><?= $pf['match'] ? ' data-match="' . e($pf['match']) . '"' : '' ?>>
    <button class="btn btn-toggle-pw border" type="button" data-toggle-password="<?= e($pf['id']) ?>" aria-label="Show password" aria-pressed="false"><i class="fa-solid fa-eye"></i></button>
  </div>
  <?php if ($pf['help']): ?><div class="form-text"><?= e($pf['help']) ?></div><?php endif; ?>
</div>
