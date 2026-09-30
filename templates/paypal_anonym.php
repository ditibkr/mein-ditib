<?php
$title = 'Anonyme Zahlungen';
$page  = 'paypal_anonym';
ob_start();

$eur = fn(float $v): string => number_format($v, 2, ',', '.') . ' €';

$gruppen = [
    'gebuehr_spende' => [
        'titel'   => 'Echte Spenden — Gebühr vorhanden + „Spende" im Betreff',
        'farbe'   => '#27ae60',
        'hinweis' => 'PayPal-Gebühr wurde abgezogen und der Betreff enthält „Spende". Sicher importierbar.',
        'warnung' => false,
    ],
    'gebuehr_sonstige' => [
        'titel'   => 'Zu prüfen — Gebühr vorhanden, anderer Betreff',
        'farbe'   => '#e67e22',
        'hinweis' => 'PayPal-Gebühr wurde abgezogen (echte externe Zahlung), aber der Betreff enthält kein „Spende". Betreff prüfen — viele sind echte Spenden mit türkischem oder englischem Betreff.',
        'warnung' => false,
    ],
    'ohne_gebuehr' => [
        'titel'   => 'Fragwürdig — Keine PayPal-Gebühr',
        'farbe'   => '#c0392b',
        'hinweis' => 'Keine PayPal-Transaktionsgebühr. Echte externe Spenden haben immer eine Gebühr. Könnten interne PayPal-Transfers, Sammelüberweisungen oder Systemtransaktionen sein.',
        'warnung' => true,
    ],
];
?>

<div class="page-header">
  <div>
    <h2>Anonyme Zahlungen</h2>
    <p>PayPal-Transaktionen ohne erkennbaren Spender — noch nicht in der Spendenliste erfasst.<br>
       Gesamt: <strong><?= $eur((float)$summen['gesamt']) ?></strong> (<?= (int)$summen['anzahl'] ?> Einträge)</p>
  </div>
</div>

<?php foreach ($gruppen as $key => $grp): ?>
  <?php $eintraege = $daten[$key] ?? []; ?>
  <?php $gs = array_sum(array_column($eintraege, 'brutto')); ?>

  <div class="form-card" style="margin-bottom:16px;border-left:4px solid <?= $grp['farbe'] ?>">

    <!-- Kopfzeile -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div>
        <h3 style="margin:0 0 4px;color:<?= $grp['farbe'] ?>;font-size:15px">
          <?= $grp['warnung'] ? '⚠️ ' : '' ?><?= htmlspecialchars($grp['titel']) ?>
        </h3>
        <p style="margin:0;font-size:12px;color:var(--muted)"><?= htmlspecialchars($grp['hinweis']) ?></p>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
        <span style="background:<?= $grp['farbe'] ?>18;color:<?= $grp['farbe'] ?>;border:1px solid <?= $grp['farbe'] ?>44;
                     border-radius:20px;padding:3px 12px;font-size:13px;font-weight:700;white-space:nowrap">
          <?= count($eintraege) ?> · <?= $eur((float)$gs) ?>
        </span>
        <button type="button" class="btn btn-outline btn-sm"
                onclick="toggleGruppe('<?= $key ?>')">
          <span id="lbl-<?= $key ?>">▼ Details</span>
        </button>
        <?php if (!$grp['warnung']): ?>
          <button type="button" class="btn btn-primary btn-sm"
                  onclick="importieren('<?= $key ?>', this)"
                  <?= empty($eintraege) ? 'disabled' : '' ?>>
            ⬇️ Importieren
          </button>
        <?php else: ?>
          <button type="button" class="btn btn-sm"
                  style="background:#fdf0ee;color:#c0392b;border:1px solid #e9b4ae"
                  onclick="importieren('<?= $key ?>', this)"
                  <?= empty($eintraege) ? 'disabled' : '' ?>>
            ⚠️ Trotzdem importieren
          </button>
        <?php endif ?>
      </div>
    </div>

    <!-- Ergebnis-Zeile -->
    <div id="res-<?= $key ?>" style="display:none;margin-top:8px;font-size:12px"></div>

    <!-- Tabelle (eingeklappt) -->
    <div id="grp-<?= $key ?>" style="display:none;margin-top:14px;overflow-x:auto">
      <?php if (empty($eintraege)): ?>
        <p style="color:var(--muted);font-size:13px;margin:0">Keine Einträge.</p>
      <?php else: ?>
        <table class="tbl">
          <thead>
            <tr>
              <th>Datum</th>
              <th>E-Mail</th>
              <th>Betreff</th>
              <th style="text-align:right">Brutto</th>
              <th style="text-align:right">Gebühr</th>
              <th style="text-align:right">Netto</th>
              <th>Transaktions-ID</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($eintraege as $t): ?>
              <tr>
                <td style="white-space:nowrap"><?= htmlspecialchars(substr($t['datum'], 0, 10)) ?></td>
                <td style="color:var(--muted);font-size:12px"><?= $t['email'] ? htmlspecialchars($t['email']) : '<span style="color:#ccc">–</span>' ?></td>
                <td style="font-size:12px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= htmlspecialchars($t['betreff']) ?>">
                  <?= $t['betreff'] ? htmlspecialchars($t['betreff']) : '<span style="color:#ccc">–</span>' ?>
                </td>
                <td style="text-align:right"><strong><?= $eur((float)$t['brutto']) ?></strong></td>
                <td style="text-align:right;color:<?= (float)$t['gebuehr'] > 0 ? 'var(--muted)' : '#c0392b' ?>">
                  <?= (float)$t['gebuehr'] > 0 ? '− ' . $eur((float)$t['gebuehr']) : '<strong>–</strong>' ?>
                </td>
                <td style="text-align:right"><?= $eur((float)$t['netto']) ?></td>
                <td><code style="font-size:10px"><?= htmlspecialchars($t['tx_id']) ?></code></td>
                <td>
                  <button type="button" class="btn btn-outline btn-sm"
                          onclick="txDetail('<?= htmlspecialchars($t['tx_id'], ENT_QUOTES) ?>')"
                          title="Details">🔍</button>
                </td>
              </tr>
            <?php endforeach ?>
          </tbody>
          <tfoot>
            <tr style="font-weight:700;background:var(--bg-subtle,#f8f9fa)">
              <td colspan="3" style="text-align:right;color:var(--muted)">Summe:</td>
              <td style="text-align:right"><?= $eur((float)$gs) ?></td>
              <td colspan="4"></td>
            </tr>
          </tfoot>
        </table>
      <?php endif ?>
    </div>
  </div>
<?php endforeach ?>

<!-- TX-Detail-Modal -->
<div id="txDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;width:min(760px,95vw);max-height:88vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.3)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #e0e0e0">
      <strong id="txDetailTitle" style="font-size:15px">Transaktionsdetails</strong>
      <button type="button" onclick="txDetailClose()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666;line-height:1">&times;</button>
    </div>
    <iframe id="txDetailFrame" src="" style="flex:1;border:none;border-radius:0 0 8px 8px" title="Transaktionsdetails"></iframe>
  </div>
</div>

<?php
$content = ob_get_clean();
$extraJs = <<<'JS'
function toggleGruppe(key) {
  const el  = document.getElementById('grp-' + key);
  const lbl = document.getElementById('lbl-' + key);
  const open = el.style.display === 'none';
  el.style.display  = open ? 'block' : 'none';
  lbl.textContent   = open ? '▲ Schließen' : '▼ Details';
}

function importieren(key, btn) {
  if (!confirm('Gruppe „' + key + '" jetzt importieren?\nAlle Einträge werden als Anonym mit Status „nicht_erforderlich" gespeichert.')) return;
  const res = document.getElementById('res-' + key);
  btn.disabled = true; btn.textContent = '⏳ Importiere…';
  res.style.display = 'block';
  res.innerHTML = '<span style="color:var(--muted)">Bitte warten…</span>';
  fetch('/api/paypal/anonym/importieren', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'gruppe=' + encodeURIComponent(key),
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      btn.disabled = true; btn.textContent = '✓ Importiert';
      res.innerHTML = '<span style="color:var(--green)">✓ ' + d.importiert + ' Einträge importiert — Summe: ' + d.summe + '</span> · <a href="/liste">Spendenliste öffnen</a>';
      setTimeout(() => location.reload(), 2000);
    } else {
      btn.disabled = false;
      res.innerHTML = '<span style="color:var(--red)">✕ Fehler: ' + (d.error || 'Unbekannt') + '</span>';
    }
  })
  .catch(() => {
    btn.disabled = false;
    res.innerHTML = '<span style="color:var(--red)">✕ Anfrage fehlgeschlagen</span>';
  });
}

function txDetail(txId) {
  document.getElementById('txDetailTitle').textContent = 'Transaktionsdetails – ' + txId;
  document.getElementById('txDetailFrame').src = '/api/paypal/tx/' + txId + '/detail';
  document.getElementById('txDetailModal').style.display = 'flex';
  document.addEventListener('keydown', txDetailEsc);
}
function txDetailClose() {
  document.getElementById('txDetailModal').style.display = 'none';
  document.getElementById('txDetailFrame').src = '';
  document.removeEventListener('keydown', txDetailEsc);
}
function txDetailEsc(e) { if (e.key === 'Escape') txDetailClose(); }
document.getElementById('txDetailModal').addEventListener('click', function(e) {
  if (e.target === this) txDetailClose();
});
JS;
require __DIR__ . '/layout.php';
