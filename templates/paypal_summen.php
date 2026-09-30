<?php
$title = 'PayPal-Summen';
$page = 'paypal_summen';
ob_start();

$eur = fn(float $v): string => number_format($v, 2, ',', '.') . ' €';

$sumBrutto  = array_sum(array_map(fn($g) => (float) $g['brutto'], $gruppen));
$sumGebuehr = array_sum(array_map(fn($g) => (float) $g['gebuehr'], $gruppen));
$sumNetto   = array_sum(array_map(fn($g) => (float) $g['netto'], $gruppen));
$sumAnzahl  = array_sum(array_map(fn($g) => (int) $g['anzahl'], $gruppen));
?>

<div class="page-header">
  <div>
    <h2>📊 Summen pro Spender</h2>
    <p>
      Zusammenfassung der PayPal-Zahlungen je Spender – für Jahres- bzw. Sammelbescheinigungen.
      Klick auf eine Zeile zeigt die Einzeltransaktionen. Summen gelten über den <strong>gesamten gewählten Zeitraum</strong>.
    </p>
  </div>
  <div style="align-self:center">
    <a href="/paypal" class="btn btn-outline btn-sm" title="Einzeltransaktionen suchen">
      💳 Einzeltransaktionen
    </a>
  </div>
</div>

<!-- ── Suche & Zeitraum ── -->
<div class="form-card">
  <form method="get" action="/paypal-summen">
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
        <button type="submit" class="btn btn-primary">🔍 Auswerten</button>
        <a href="/paypal-summen" class="btn btn-outline">✕</a>
      </div>
    </div>
    <small style="color:var(--muted);font-size:11px;display:block;margin-top:4px">
      Tipp: Zeitraum genügt – Text ist optional. Spaltenüberschrift anklicken zum Sortieren.
    </small>
  </form>
</div>

<?php if (!empty($standard)): ?>
  <div class="alert alert-info" style="margin-bottom:12px">
    Standardansicht: <strong>aktuelles Jahr <?= date('Y') ?></strong>
    (<?= date('d.m.Y', strtotime($von)) ?> – <?= date('d.m.Y', strtotime($bis)) ?>).
  </div>
<?php endif ?>

<?php if ($gruppen): ?>

  <div class="form-card">
    <h3>Σ Spender im Zeitraum (<?= count($gruppen) ?><?= count($gruppen) >= 5000 ? '+' : '' ?>)</h3>
    <?php if (count($gruppen) >= 5000): ?>
      <small style="color:var(--orange);font-size:11px;display:block;margin-bottom:6px">
        ⚠ Anzeige auf 5000 Spender begrenzt – bitte Zeitraum eingrenzen.
      </small>
    <?php endif ?>
    <table class="tbl" id="summen-tbl"
           data-von="<?= htmlspecialchars($von) ?>" data-bis="<?= htmlspecialchars($bis) ?>">
      <thead>
        <tr>
          <th style="width:26px"></th>
          <th data-key="name"    onclick="sortSummen(this)" style="cursor:pointer" title="Sortieren">Spender</th>
          <th data-key="email"   onclick="sortSummen(this)" style="cursor:pointer" title="Sortieren">E-Mail</th>
          <th data-key="anzahl"  data-type="num" onclick="sortSummen(this)" style="cursor:pointer;text-align:right" title="Sortieren">Zahlungen</th>
          <th data-key="brutto"  data-type="num" onclick="sortSummen(this)" style="cursor:pointer;text-align:right" title="Sortieren">Brutto</th>
          <th data-key="gebuehr" data-type="num" onclick="sortSummen(this)" style="cursor:pointer;text-align:right" title="Sortieren">Gebühren</th>
          <th data-key="netto"   data-type="num" onclick="sortSummen(this)" style="cursor:pointer;text-align:right" title="Sortieren">Netto</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($gruppen as $g): ?>
          <?php $name = trim($g['vorname'] . ' ' . $g['nachname']); ?>
          <tr class="sp-row" style="cursor:pointer"
              data-email="<?= htmlspecialchars($g['email']) ?>"
              data-vorname="<?= htmlspecialchars($g['vorname']) ?>"
              data-nachname="<?= htmlspecialchars($g['nachname']) ?>"
              onclick="toggleSpender(this)">
            <td><span class="exp-arr" style="color:var(--muted)">▸</span></td>
            <td data-v="<?= htmlspecialchars(mb_strtolower($name)) ?>"><strong><?= htmlspecialchars($name ?: '–') ?></strong></td>
            <td data-v="<?= htmlspecialchars(mb_strtolower($g['email'])) ?>" style="color:var(--muted)"><?= htmlspecialchars($g['email']) ?></td>
            <td data-v="<?= (int) $g['anzahl'] ?>" style="text-align:right"><?= (int) $g['anzahl'] ?></td>
            <td data-v="<?= (float) $g['brutto'] ?>" style="text-align:right"><strong><?= $eur((float) $g['brutto']) ?></strong></td>
            <td data-v="<?= (float) $g['gebuehr'] ?>" style="text-align:right;color:var(--muted)">− <?= $eur((float) $g['gebuehr']) ?></td>
            <td data-v="<?= (float) $g['netto'] ?>" style="text-align:right"><?= $eur((float) $g['netto']) ?></td>
            <td style="text-align:right" onclick="event.stopPropagation()">
              <a class="btn btn-outline btn-sm"
                 href="/manuell?vorname=<?= urlencode($g['vorname']) ?>&nachname=<?= urlencode($g['nachname']) ?>&email=<?= urlencode($g['email']) ?>&betrag=<?= number_format((float) $g['brutto'], 2, '.', '') ?>"
                 title="Manuelles Formular mit Spender und Brutto-Gesamtsumme vorbefüllen">
                ✏️ Bescheinigung
              </a>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <?php if (count($gruppen) > 1): ?>
          <tr style="border-top:2px solid var(--border)">
            <td></td>
            <td colspan="2"><strong>Gesamt</strong></td>
            <td style="text-align:right"><strong><?= (int) $sumAnzahl ?></strong></td>
            <td style="text-align:right"><strong><?= $eur($sumBrutto) ?></strong></td>
            <td style="text-align:right;color:var(--muted)">− <?= $eur($sumGebuehr) ?></td>
            <td style="text-align:right"><strong><?= $eur($sumNetto) ?></strong></td>
            <td></td>
          </tr>
        <?php endif ?>
      </tfoot>
    </table>
    <small style="color:var(--muted);font-size:11px;display:block;margin-top:6px">
      Hinweis: Für die Spendenbescheinigung zählt der <strong>Brutto</strong>-Betrag (das hat der Spender gegeben) –
      die PayPal-Gebühr ist Aufwand des Vereins.
    </small>
  </div>

<?php elseif ((int) $txInfo['anzahl'] > 0): ?>
  <div class="form-card"><p style="color:var(--muted)">Keine Zahlungen in diesem Zeitraum.</p></div>
<?php else: ?>
  <div class="form-card"><p style="color:var(--muted)">Noch keine Daten geladen – unter „PayPal-Suche → Von PayPal laden" Zahlungen holen.</p></div>
<?php endif ?>

<?php
$content = ob_get_clean();
$extraJs = <<<'JS'
// ── Betrag hübsch formatieren (1.234,56 €) ──
function eur(v) {
  return (Number(v) || 0).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

// ── Zeile aufklappen: Einzeltransaktionen des Spenders per AJAX laden ──
function toggleSpender(row) {
  const arr = row.querySelector('.exp-arr');
  const next = row.nextElementSibling;
  if (next && next.classList.contains('detail-row')) {
    const show = next.style.display === 'none';
    next.style.display = show ? '' : 'none';
    arr.textContent = show ? '▾' : '▸';
    return;
  }
  const tbl = document.getElementById('summen-tbl');
  arr.textContent = '⏳';
  const p = new URLSearchParams({
    email: row.dataset.email, vorname: row.dataset.vorname, nachname: row.dataset.nachname,
    von: tbl.dataset.von || '', bis: tbl.dataset.bis || '',
  });
  fetch('/api/paypal/spender-tx?' + p).then(r => r.json()).then(d => {
    arr.textContent = '▾';
    const tr = document.createElement('tr');
    tr.className = 'detail-row';
    const td = document.createElement('td');
    td.colSpan = row.children.length;
    td.style.background = 'rgba(127,127,127,.05)';
    td.innerHTML = renderDetail(d.transaktionen || []);
    tr.appendChild(td);
    row.after(tr);
  }).catch(() => { arr.textContent = '▸'; alert('Fehler beim Laden der Transaktionen'); });
}

function renderDetail(txs) {
  if (!txs.length) return '<em style="color:var(--muted)">Keine Einzeltransaktionen im Zeitraum.</em>';
  let h = '<table class="tbl" style="margin:6px 0"><thead><tr>'
        + '<th>Datum</th><th style="text-align:right">Brutto</th><th style="text-align:right">Gebühr</th>'
        + '<th style="text-align:right">Netto</th><th>Transaktions-ID</th><th>Spende</th></tr></thead><tbody>';
  for (const t of txs) {
    const spende = t.spende_id
      ? `<a href="/spende/${t.spende_id}" class="nb nb-blue" style="text-decoration:none">✓ #${t.spende_id}</a>`
      : `<a class="btn btn-outline btn-sm" href="/manuell?vorname=${encodeURIComponent(t.vorname)}&nachname=${encodeURIComponent(t.nachname)}&email=${encodeURIComponent(t.email)}&betrag=${(Number(t.brutto)||0).toFixed(2)}&datum=${encodeURIComponent((t.datum||'').slice(0,10))}">✏️ Bescheinigung</a>`;
    h += `<tr><td>${(t.datum||'').slice(0,10)}</td>`
       + `<td style="text-align:right"><strong>${eur(t.brutto)}</strong></td>`
       + `<td style="text-align:right;color:var(--muted)">− ${eur(t.gebuehr)}</td>`
       + `<td style="text-align:right">${eur(t.netto)}</td>`
       + `<td><code style="font-size:11px">${t.tx_id}</code></td>`
       + `<td>${spende}</td></tr>`;
  }
  return h + '</tbody></table>';
}

// ── Spalten sortieren (auf-/absteigend) ──
let summenSort = {};
function sortSummen(th) {
  const tbl = document.getElementById('summen-tbl');
  const tbody = tbl.tBodies[0];
  const idx = [...th.parentNode.children].indexOf(th);
  const numeric = th.dataset.type === 'num';
  const key = th.dataset.key;
  // Aufgeklappte Detailzeilen einklappen (sonst geraten sie beim Sortieren durcheinander)
  tbody.querySelectorAll('tr.detail-row').forEach(r => r.remove());
  tbody.querySelectorAll('.exp-arr').forEach(a => a.textContent = '▸');
  const dir = summenSort[key] === 'asc' ? 'desc' : 'asc';
  summenSort = { [key]: dir };
  const rows = [...tbody.querySelectorAll('tr.sp-row')];
  rows.sort((a, b) => {
    let av = a.children[idx].dataset.v ?? a.children[idx].textContent.trim();
    let bv = b.children[idx].dataset.v ?? b.children[idx].textContent.trim();
    if (numeric) { av = parseFloat(av) || 0; bv = parseFloat(bv) || 0; return dir === 'asc' ? av - bv : bv - av; }
    return dir === 'asc' ? String(av).localeCompare(bv, 'de') : String(bv).localeCompare(av, 'de');
  });
  rows.forEach(r => tbody.appendChild(r));
  // Sortier-Pfeil im Header anzeigen
  tbl.querySelectorAll('th .sort-arr').forEach(s => s.remove());
  const s = document.createElement('span');
  s.className = 'sort-arr';
  s.textContent = dir === 'asc' ? ' ▲' : ' ▼';
  th.appendChild(s);
}
JS;
require __DIR__ . '/layout.php';
