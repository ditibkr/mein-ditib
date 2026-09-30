<?php
$title = 'PayPal-Suche';
$page = 'paypal';
ob_start();

$eur = fn(float $v): string => number_format($v, 2, ',', '.') . ' €';
?>

<div class="page-header">
  <div>
    <h2>PayPal-Suche</h2>
    <p>
      Lokale Kopie der PayPal-Zahlungen – Einzeltransaktionen suchen und Bescheinigungen erstellen.
      <?php if ((int) $txInfo['anzahl'] > 0): ?>
        Bestand: <strong><?= (int) $txInfo['anzahl'] ?></strong> Zahlungen
        (<?= htmlspecialchars(substr($txInfo['aeltester'] ?? '', 0, 10)) ?> bis
        <?= htmlspecialchars(substr($txInfo['neuester'] ?? '', 0, 10)) ?>)
      <?php else: ?>
        Noch keine Daten geladen – unten „Von PayPal laden" nutzen.
      <?php endif ?>
    </p>
  </div>
  <div style="align-self:center">
    <a href="/paypal-summen" class="btn btn-outline btn-sm" title="Summen pro Spender (Jahres-/Sammelbescheinigungen)">
      📊 Summen pro Spender
    </a>
  </div>
</div>

<!-- ── Suche & Filter (lokal, schnell) ── -->
<div class="form-card">
  <form method="get" action="/paypal">
    <div class="form-row">
      <div class="fg" style="flex:2">
        <label>Suche (Name, E-Mail, Transaktions-ID)</label>
        <input type="text" name="suche" value="<?= htmlspecialchars($suche) ?>" placeholder="z. B. Mustermann">
      </div>
      <div class="fg"><label>Von</label>
        <input type="date" name="von" value="<?= htmlspecialchars($von) ?>"></div>
      <div class="fg"><label>Bis</label>
        <input type="date" name="bis" value="<?= htmlspecialchars($bis) ?>"></div>
      <div class="fg" style="align-self:flex-end">
        <button type="submit" class="btn btn-primary">🔍 Suchen</button>
        <a href="/paypal" class="btn btn-outline">✕</a>
      </div>
    </div>
    <small style="color:var(--muted);font-size:11px;display:block;margin-top:4px">
      Tipp: Zeitraum genügt – du kannst auch ohne Text nur nach „Von/Bis" suchen.
    </small>
  </form>
</div>

<?php if (!empty($standard)): ?>
  <div class="alert alert-info" style="margin-bottom:12px">
    Standardansicht: <strong>heute (<?= date('d.m.Y') ?>)</strong>.
    Für ältere Zahlungen oben Suche oder Zeitraum nutzen.
  </div>
<?php endif ?>

<?php if ($transaktionen): ?>

  <!-- ── Einzeltransaktionen ── -->
  <div class="form-card">
    <h3>💳 Einzeltransaktionen (<?= count($transaktionen) ?><?= count($transaktionen) >= 1000 ? '+' : '' ?>)</h3>
    <?php if (count($transaktionen) >= 1000): ?>
      <small style="color:var(--orange);font-size:11px;display:block;margin-bottom:6px">
        ⚠ Anzeige auf 1000 begrenzt – bitte Zeitraum eingrenzen.
      </small>
    <?php endif ?>
    <table class="tbl">
      <thead>
        <tr>
          <th>Datum</th><th>Name</th><th>E-Mail</th>
          <th style="text-align:right">Brutto</th>
          <th style="text-align:right">Gebühr</th>
          <th style="text-align:right">Netto</th>
          <th>Status</th><th>Transaktions-ID</th><th>Spende</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transaktionen as $t): ?>
          <tr>
            <td><?= htmlspecialchars(substr($t['datum'], 0, 10)) ?></td>
            <td><?= htmlspecialchars(trim($t['vorname'] . ' ' . $t['nachname'])) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($t['email']) ?></td>
            <td style="text-align:right"><strong><?= $eur((float) $t['brutto']) ?></strong></td>
            <td style="text-align:right;color:var(--muted)">− <?= $eur((float) $t['gebuehr']) ?></td>
            <td style="text-align:right"><?= $eur((float) $t['netto']) ?></td>
            <td><?= $t['status'] === 'S' ? '✓' : htmlspecialchars($t['status']) ?></td>
            <td><code style="font-size:11px"><?= htmlspecialchars($t['tx_id']) ?></code></td>
            <td>
              <?php if ($t['spende_id']): ?>
                <a href="/spende/<?= (int) $t['spende_id'] ?>" class="nb nb-blue"
                   style="text-decoration:none" title="Bereits als Spende importiert">✓ #<?= (int) $t['spende_id'] ?></a>
              <?php else: ?>
                <a class="btn btn-outline btn-sm"
                   href="/manuell?vorname=<?= urlencode($t['vorname']) ?>&nachname=<?= urlencode($t['nachname']) ?>&email=<?= urlencode($t['email']) ?>&betrag=<?= number_format((float) $t['brutto'], 2, '.', '') ?>&datum=<?= urlencode(substr($t['datum'], 0, 10)) ?>"
                   title="Bescheinigung für diese Zahlung erstellen – Formular wird vorbefüllt (auch unter Mindestbetrag)">
                  ✏️ Bescheinigung
                </a>
              <?php endif ?>
            </td>
            <td>
              <button type="button" class="btn btn-outline btn-sm"
                      onclick="txDetail('<?= htmlspecialchars($t['tx_id'], ENT_QUOTES) ?>')"
                      title="Transaktionsdetails anzeigen">🔍</button>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

<?php elseif ((int) $txInfo['anzahl'] > 0): ?>
  <div class="form-card"><p style="color:var(--muted)">Keine Treffer für diesen Filter.</p></div>
<?php endif ?>

<!-- ── Von PayPal laden ── -->
<div class="form-card">
  <h3>⬇️ Von PayPal laden</h3>
  <p style="font-size:12px;color:var(--muted);margin-bottom:10px">
    Holt Zahlungen aus der PayPal-API in die lokale Tabelle (bereits geladene werden aktualisiert, nichts geht verloren).
    Lange Zeiträume werden automatisch in 31-Tage-Blöcke zerlegt – das kann bei mehreren Monaten etwas dauern.
    Neue Zahlungen erscheinen bei PayPal erst nach ca. 3 Stunden.
  </p>
  <div class="form-row">
    <div class="fg"><label>Von</label><input type="date" id="sync-von"
        value="<?= date('Y-m-d', strtotime('-31 days')) ?>"></div>
    <div class="fg"><label>Bis</label><input type="date" id="sync-bis" value="<?= date('Y-m-d') ?>"></div>
    <div class="fg" style="align-self:flex-end">
      <button type="button" class="btn btn-primary" onclick="paypalSync(this)">⬇️ Laden</button>
    </div>
  </div>
  <div id="sync-result" style="margin-top:8px;font-size:12px;display:none"></div>
</div>

<?php
$content = ob_get_clean();

// Modal-HTML direkt vor dem Layout einhängen
$content .= <<<'HTML'
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
HTML;

$extraJs = <<<'JS'
function txDetail(txId) {
  const modal = document.getElementById('txDetailModal');
  const frame = document.getElementById('txDetailFrame');
  const title = document.getElementById('txDetailTitle');
  title.textContent = 'Transaktionsdetails – ' + txId;
  frame.src = '/api/paypal/tx/' + txId + '/detail';
  modal.style.display = 'flex';
  document.addEventListener('keydown', txDetailEsc);
}
function txDetailClose() {
  const modal = document.getElementById('txDetailModal');
  modal.style.display = 'none';
  document.getElementById('txDetailFrame').src = '';
  document.removeEventListener('keydown', txDetailEsc);
}
function txDetailEsc(e) { if (e.key === 'Escape') txDetailClose(); }
// Klick auf Hintergrund schließt Modal
document.getElementById('txDetailModal').addEventListener('click', function(e) {
  if (e.target === this) txDetailClose();
});
function paypalSync(btn) {
  const result = document.getElementById('sync-result');
  btn.disabled = true; btn.textContent = '⏳ Lade von PayPal…';
  result.style.display = 'block';
  result.innerHTML = '<span style="color:var(--muted)">Bitte warten – je nach Zeitraum mehrere API-Abfragen…</span>';
  const body = new URLSearchParams({
    von: document.getElementById('sync-von').value,
    bis: document.getElementById('sync-bis').value,
  });
  fetch('/api/paypal/sync', { method: 'POST', body })
    .then(r => r.json()).then(d => {
      btn.disabled = false; btn.textContent = '⬇️ Laden';
      if (d.ok) {
        result.innerHTML = `<span style="color:var(--green)">✓ ${d.gespeichert} Zahlungen gespeichert (${d.gesamt} Transaktionen geprüft)</span> – Seite wird neu geladen…`;
        setTimeout(() => location.reload(), 1200);
      } else {
        result.innerHTML = `<span style="color:var(--red)">✕ Fehler: ${d.error}</span>`;
      }
    }).catch(() => {
      btn.disabled = false; btn.textContent = '⬇️ Laden';
      result.innerHTML = '<span style="color:var(--red)">✕ Anfrage fehlgeschlagen (Timeout?)</span>';
    });
}
JS;
require __DIR__ . '/layout.php';
