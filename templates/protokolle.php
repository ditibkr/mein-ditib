<?php
$title = 'Sitzungsprotokolle';
$page = 'protokolle';
ob_start();

$fmtDatum = function (string $iso): string {
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) ? "$m[3].$m[2].$m[1]" : $iso;
};
?>

<div class="page-header">
  <div>
    <h2>Sitzungsprotokolle</h2>
    <p>Vorstandssitzungen · Neueste zuerst</p>
  </div>
  <div class="page-header-actions">
    <form method="post" action="/protokoll/neu">
      <button class="btn btn-primary btn-sm">＋ Neues Protokoll</button>
    </form>
  </div>
</div>

<div class="card">
  <?php if (empty($protokolle)): ?>
    <div class="empty-state">
      <div class="es-icon">📝</div>
      <p>Noch keine Protokolle. Mit „Neues Protokoll“ startet die erste Live-Mitschrift.</p>
    </div>
  <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Datum</th>
            <th>Uhrzeit</th>
            <th>Anwesende</th>
            <th>TOPs</th>
            <th>Status</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($protokolle as $p):
              $tn = json_decode($p['teilnehmer'] ?: '[]', true) ?: [];
              $anwesend = array_values(array_filter($tn, fn ($t) => !empty($t['anwesend'])));
              $tops = json_decode($p['tops'] ?: '[]', true) ?: [];
              $offen = ($p['status'] ?? 'offen') !== 'abgeschlossen';
          ?>
          <tr>
            <td data-label="Datum">
              <a href="/protokoll/<?= (int) $p['id'] ?>" style="font-weight:600">
                <?= htmlspecialchars($fmtDatum($p['datum'])) ?>
              </a>
            </td>
            <td data-label="Uhrzeit" class="text-muted">
              <?= htmlspecialchars($p['beginn'] ?: '–') ?><?= $p['ende'] ? ' – ' . htmlspecialchars($p['ende']) : '' ?>
            </td>
            <td data-label="Anwesende">
              <?= count($anwesend) ?>
              <span class="text-muted text-small">
                <?= htmlspecialchars(implode(', ', array_slice(array_column($anwesend, 'name'), 0, 3))) ?><?= count($anwesend) > 3 ? ', …' : '' ?>
              </span>
            </td>
            <td data-label="TOPs"><?= count($tops) ?></td>
            <td data-label="Status">
              <span class="badge <?= $offen ? 'b-neu' : 'b-erl' ?>"><?= $offen ? 'offen' : 'abgeschlossen' ?></span>
            </td>
            <td data-label="Aktionen" class="td-actions">
              <a class="btn btn-outline btn-xs" href="/protokoll/<?= (int) $p['id'] ?>"><?= $offen ? '✏️ Fortsetzen' : '👁 Ansehen' ?></a>
              <a class="btn btn-outline btn-xs" href="/protokoll/<?= (int) $p['id'] ?>/pdf" title="Als PDF speichern">📄</a>
              <button class="btn btn-outline btn-xs" onclick="protLoeschen(<?= (int) $p['id'] ?>, '<?= htmlspecialchars($fmtDatum($p['datum']), ENT_QUOTES) ?>')">🗑</button>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>

<script>
async function protLoeschen(id, datum) {
  if (!confirm(`Protokoll vom ${datum} wirklich löschen?`)) return;
  const r = await fetch(`/api/protokoll/${id}/loeschen`, { method: 'POST' });
  const j = await r.json();
  if (j.ok) location.reload();
  else alert('Löschen fehlgeschlagen');
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
