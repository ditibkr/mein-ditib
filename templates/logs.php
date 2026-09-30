<?php
$title = 'System-Logs';
$page = 'logs';
ob_start();
?>

<div class="page-header">
  <div>
    <h2>System-Logs</h2>
    <p>Aktivitätsprotokoll · Neueste Einträge zuerst</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-outline btn-sm" onclick="jobStarten(this)">▶ Job jetzt starten</button>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div>
      <h3>Protokoll</h3>
      <p><?= count($logs) ?> Einträge</p>
    </div>
  </div>
  <?php if (empty($logs)): ?>
    <div class="empty-state">
      <div class="es-icon">📜</div>
      <p>Noch keine Logs.</p>
    </div>
  <?php else: ?>
    <?php foreach ($logs as $l): ?>
      <?php include __DIR__ . '/parts/log_row.php' ?>
    <?php endforeach ?>
  <?php endif ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
