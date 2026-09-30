<?php // erwartet $l, $cfg
$dotMap = ['OK' => 'ld-ok', 'WARN' => 'ld-warn', 'ERROR' => 'ld-err', 'INFO' => 'ld-info'];
$iconMap = ['OK' => '✓', 'WARN' => '!', 'ERROR' => '✕', 'INFO' => 'i'];
$dot  = $dotMap[$l['level']]  ?? 'ld-info';
$icon = $iconMap[$l['level']] ?? 'i';
$logUser      = $l['user'] ?? '';
$logUserName  = $logUser ? ($cfg['user_namen'][$logUser] ?? $logUser) : '';
?>
<div class="log-row">
  <span class="log-time"><?= $l['erstellt_am'] ?></span>
  <div class="log-dot <?= $dot ?>"><?= $icon ?></div>
  <div class="log-msg"><?= htmlspecialchars($l['nachricht']) ?></div>
  <?php if ($logUserName): ?>
    <span style="font-size:11px;color:var(--muted);white-space:nowrap;margin-left:auto;padding-left:8px"
          title="<?= htmlspecialchars($logUser) ?>">
      👤 <?= htmlspecialchars($logUserName) ?>
    </span>
  <?php endif ?>
</div>