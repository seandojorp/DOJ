<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/dataTables.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
<?php if (!empty($withCharts)): ?>
<script src="<?= e(asset('vendor/chartjs/chart.umd.min.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach (($scripts ?? []) as $s): ?>
<script src="<?= e(asset('js/' . $s)) ?>"></script>
<?php endforeach; ?>
