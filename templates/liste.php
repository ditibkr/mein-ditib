<?php
$title = 'Spendenliste';
$page = 'liste';
ob_start();
?>

<div class="page-header">
  <div>
    <h2>Spendenliste</h2>
    <p>Alle Bescheinigungen verwalten</p>
  </div>
  <div class="page-header-actions">
    <a href="/manuell" class="btn btn-primary btn-sm">+ Manuell erstellen</a>
  </div>
</div>

<div class="card">
  <!-- Tabs -->
  <div class="tabs">
    <?php
    $tabs = ['alle' => 'Alle', 'neu' => 'Neu', 'freigegeben' => 'Freigegeben', 'versendet' => 'Versendet', 'erledigt' => 'Erledigt', 'nicht_erforderlich' => 'Nicht erforderlich', 'fehler' => 'Fehler'];
    foreach ($tabs as $key => $label):
      $count = $key === 'alle' ? $zaehler['gesamt'] : ($zaehler[$key] ?? 0);
      $qs = http_build_query(['status' => $key, 'q' => $suche, 'sort' => $sortKey]);
      ?>
      <a href="/liste?<?= $qs ?>" class="tab <?= $status === $key ? 'active' : '' ?>">
        <?= $label ?><span class="tab-n"><?= $count ?></span>
      </a>
    <?php endforeach ?>
  </div>

  <!-- Suche & Sortierung -->
  <form method="get" action="/liste">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <div class="search-bar">
      <div class="search-input">
        <span style="color:var(--muted)">🔍</span>
        <input type="text" name="q" id="sucheInput" value="<?= htmlspecialchars($suche) ?>" placeholder="Name, E-Mail, Betrag…">
        <?php if ($suche): ?>
          <a href="/liste?status=<?= htmlspecialchars($status) ?>&sort=<?= htmlspecialchars($sortKey) ?>"
             style="color:var(--muted);text-decoration:none;font-size:15px;line-height:1;padding:0 2px"
             title="Suche leeren">✕</a>
        <?php endif ?>
      </div>
      <select class="sort-sel" name="sort" onchange="this.form.submit()">
        <?php foreach ([
          'datum_desc' => 'Datum ↓',
          'datum' => 'Datum ↑',
          'name' => 'Name A–Z',
          'name_desc' => 'Name Z–A',
          'betrag_desc' => 'Betrag ↓',
          'betrag' => 'Betrag ↑',
        ] as $v => $l): ?>
          <option value="<?= $v ?>" <?= $sortKey === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach ?>
      </select>

      <input type="date" name="datum_von" value="<?= htmlspecialchars($_GET['datum_von'] ?? '') ?>">
      <input type="date" name="datum_bis" value="<?= htmlspecialchars($_GET['datum_bis'] ?? '') ?>">
      <button type="submit" class="btn btn-outline btn-sm">Suchen</button>
      <?php if ($status === 'versendet'): ?>
        <a href="/api/sammel-druck" class="btn btn-outline btn-sm" title="Alle versendeten PDFs als eine Datei herunterladen">
          🖨 Sammel-Druck
        </a>
        <button type="button" class="btn btn-outline btn-sm" onclick="sammelErledigt()"
                title="Alle versendeten Einträge auf Erledigt setzen">
          ✓ Alle erledigt
        </button>
      <?php endif ?>
    </div>
  </form>

  <!-- Tabelle -->
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Spender</th>
          <th>Art</th>
          <th>Betrag</th>
          <th>Datum</th>
          <th>Quelle</th>
          <th>Status</th>
          <th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($spenden)): ?>
          <tr>
            <td colspan="7">
              <div class="empty-state">
                <div class="es-icon">🔍</div>
                <p>Keine Einträge gefunden.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($spenden as $s): ?>
            <tr>
              <td data-label="Spender">
                <a href="/spende/<?= $s['id'] ?>" style="font-weight:600;color:var(--text)">
                  <?= htmlspecialchars($s['vorname'] . ' ' . $s['nachname']) ?>
                </a>
                <?php if ((int)($s['wiederholer'] ?? 0) > 0): ?>
                  <?php $ppSuche = $s['email'] ?: ($s['vorname'] . ' ' . $s['nachname']); ?>
                  <a href="/paypal-summen?suche=<?= urlencode($ppSuche) ?>"
                     title="Wiederholungsspender – <?= (int)$s['wiederholer'] + 1 ?> Spenden insgesamt – alle in PayPal-Summen anzeigen"
                     style="margin-left:5px;text-decoration:none;font-size:13px">🔁</a>
                <?php endif ?>
                <?php
                  $aFehlt = [];
                  if (trim($s['strasse'] ?? '') === '') $aFehlt[] = 'Straße';
                  if (trim($s['plz']     ?? '') === '') $aFehlt[] = 'PLZ';
                  if (trim($s['ort']     ?? '') === '') $aFehlt[] = 'Ort';
                  $adresseVollstaendig = empty($aFehlt);
                  $adresseAktiv = (int)($s['adresse_angefragt'] ?? 0) === 1
                                  && !$adresseVollstaendig
                                  && !in_array($s['status'], ['erledigt', 'versendet', 'nicht_erforderlich']);
                  $flagTooltip = $adresseAktiv
                    ? 'Adresse angefragt – fehlt: ' . implode(', ', $aFehlt) . ' – klicken zum Zurücksetzen'
                    : 'Adressanfrage erledigt';
                ?>
                <?php if ($adresseAktiv || (int)($s['adresse_angefragt'] ?? 0) === 1): ?>
                  <button type="button"
                          onclick="toggleAdresseFlag(<?= $s['id'] ?>, this)"
                          title="<?= htmlspecialchars($flagTooltip) ?>"
                          style="background:none;border:none;cursor:pointer;padding:0 0 0 4px;font-size:13px;opacity:<?= $adresseAktiv ? '1' : '0.35' ?>">📬</button>
                <?php endif ?>
                <?php if ($s['email']): ?>
                  <br><span class="text-muted text-small"><?= htmlspecialchars($s['email']) ?></span>
                <?php endif ?>
              </td>
              <td data-label="Art"><?php include __DIR__ . '/parts/art_badge.php' ?></td>
              <td data-label="Betrag"><strong><?= number_format($s['betrag'], 2, ',', '.') ?> €</strong></td>
              <td data-label="Datum"><?= htmlspecialchars($s['datum']) ?></td>
              <td data-label="Quelle">
                <span class="text-muted text-small">
                  <?= $s['quelle'] === 'mail' ? '📧 Mail' : ($s['quelle'] === 'manuell' ? '✏ Manuell' : '📦 ' . $s['quelle']) ?>
                </span>
              </td>
              <td data-label="Status"><?php include __DIR__ . '/parts/status_badge.php' ?></td>
              <td class="td-actions">
                <?php if ($s['pdf_pfad']): ?>
                  <a href="/spende/<?= $s['id'] ?>/pdf" target="_blank" class="icon-btn preview" title="PDF ansehen">👁</a>
                <?php endif ?>
                <?php if ($s['status'] === 'neu'): ?>
                  <button class="icon-btn approve" onclick="freigeben(<?= $s['id'] ?>)" title="Freigeben">✓</button>
                <?php endif ?>
                <?php if ($s['status'] === 'freigegeben' && $s['email']): ?>
                  <button class="icon-btn send" onclick="versenden(<?= $s['id'] ?>, this)" title="Mail senden">✉</button>
                <?php endif ?>
                <?php if ($s['status'] === 'freigegeben' && $s['pdf_pfad']): ?>
                  <button class="icon-btn" onclick="postversand(<?= $s['id'] ?>, this)" title="Per Post versendet">📬</button>
                <?php endif ?>
                <?php if (adresseAnfragbar($s)): ?>
                  <button class="icon-btn" onclick="adresseAnfragen(<?= $s['id'] ?>, this)"
                    title="Keine Anschrift – Spender per Mail nach Adresse fragen">🏠</button>
                <?php endif ?>
                <?php if ($s['status'] === 'versendet'): ?>
                  <button class="icon-btn approve" onclick="erledigen(<?= $s['id'] ?>, this)" title="Als erledigt markieren">✓ Erl.</button>
                <?php endif ?>
                <a href="/spende/<?= $s['id'] ?>" class="icon-btn" title="Details">✎</a>
                <button class="icon-btn"
                  onclick="openComment(<?= $s['id'] ?>,'<?= htmlspecialchars($s['vorname'] . ' ' . $s['nachname']) ?>')"
                  title="Kommentare">💬</button>
                <button class="icon-btn danger"
                  onclick="loeschen(<?= $s['id'] ?>, '<?= htmlspecialchars($s['vorname'] . ' ' . $s['nachname']) ?>')"
                  title="Löschen">🗑</button>
              </td>
            </tr>
          <?php endforeach ?>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$content = ob_get_clean();
$extraJs = <<<'JS'
function sammelErledigt() {
  if (!confirm('Alle versendeten Einträge auf „Erledigt" setzen?')) return;
  fetch('/api/sammel-erledigt', {method:'POST'})
    .then(r => r.json())
    .then(d => {
      if (d.ok) { showNotif('✓ ' + d.anzahl + ' Einträge auf Erledigt gesetzt'); setTimeout(() => location.reload(), 1000); }
      else showNotif(d.error || 'Fehler', false);
    });
}
function toggleAdresseFlag(id, btn) {
  fetch('/api/spende/' + id + '/adresse-flag', {method:'POST'})
    .then(r => r.json())
    .then(d => {
      if (d.ok) {
        btn.style.opacity = d.aktiv ? '1' : '0.35';
        btn.title = d.aktiv ? 'Adresse wurde angefragt – klicken zum Zurücksetzen' : 'Adressanfrage erledigt';
      }
    });
}
JS;
require __DIR__ . '/layout.php';
