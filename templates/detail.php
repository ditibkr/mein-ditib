<?php
$title = htmlspecialchars($spende['vorname'].' '.$spende['nachname']);
$page  = 'liste';
// Cache-Buster: ändert sich mit der PDF, sonst zeigt der Browser die alte Version
$pdfVersion = $spende['pdf_pfad'] ? (@filemtime($spende['pdf_pfad']) ?: time()) : 0;
ob_start();
?>

<div class="page-header">
  <div>
    <h2>
      <?= htmlspecialchars($spende['vorname'].' '.$spende['nachname']) ?>
      <?php
        $adresseVollstaendig = trim($spende['strasse'] ?? '') !== '' && trim($spende['plz'] ?? '') !== '';
        $adresseAktiv = (int)($spende['adresse_angefragt'] ?? 0) === 1
                        && !$adresseVollstaendig
                        && !in_array($spende['status'], ['erledigt', 'versendet', 'nicht_erforderlich']);
      ?>
      <?php if ((int)($spende['adresse_angefragt'] ?? 0) === 1): ?>
        <button id="btnAdresseFlag" type="button"
                onclick="toggleAdresseFlag(<?= $spende['id'] ?>, this)"
                title="<?= $adresseAktiv ? 'Adresse wurde angefragt – klicken zum Zurücksetzen' : 'Adressanfrage erledigt (Adresse vorhanden)' ?>"
                style="background:none;border:none;cursor:pointer;font-size:16px;vertical-align:middle;opacity:<?= $adresseAktiv ? '1' : '0.35' ?>">📬</button>
      <?php endif ?>
    </h2>
    <p>Spende vom <?= htmlspecialchars($spende['datum']) ?> · #<?= $spende['id'] ?></p>
  </div>
  <div class="page-header-actions">
    <?php if ($spende['pdf_pfad']): ?>
      <button class="btn btn-outline btn-sm" onclick="togglePdfVorschau()">👁 PDF Vorschau</button>
      <a href="/spende/<?= $spende['id'] ?>/pdf" download class="btn btn-outline btn-sm">⬇ Download</a>
    <?php endif ?>
    <?php if ($spende['mail_id']): ?>
      <button id="btnOriginalMail" class="btn btn-outline btn-sm" onclick="toggleOriginalMail()">📧 Original Mail</button>
    <?php endif ?>
    <?php if ($spende['pdf_pfad'] && $spende['mail_id']): ?>
      <button id="btnVergleich" class="btn btn-outline btn-sm" onclick="toggleVergleich()">↔ Vergleich</button>
    <?php endif ?>
    <?php if (!empty($spende['auslaendisch']) && !in_array($spende['status'], ['versendet', 'erledigt'])): ?>
      <button class="btn btn-outline btn-sm" onclick="auslaendischToggle(<?= $spende['id'] ?>, this)">🇩🇪 Als inländisch markieren</button>
    <?php endif ?>
    <button class="btn btn-outline btn-sm" onclick="toggleEdit()">✏️ Bearbeiten</button>
    <?php if (!in_array($spende['status'], ['versendet', 'erledigt', 'nicht_erforderlich'])): ?>
      <button class="btn btn-outline btn-sm" style="color:var(--muted)" onclick="nichtErforderlich(<?= $spende['id'] ?>, this)">— Nicht erforderlich</button>
    <?php endif ?>
    <?php if ($spende['status'] === 'nicht_erforderlich'): ?>
      <button class="btn btn-outline btn-sm" style="color:var(--accent)" onclick="reaktivieren(<?= $spende['id'] ?>, this)">♻ Fall reaktivieren</button>
    <?php endif ?>
    <?php if ($spende['status'] === 'neu'): ?>
      <button class="btn btn-success btn-sm" onclick="freigeben(<?= $spende['id'] ?>)">✓ Freigeben</button>
    <?php endif ?>
    <?php if ($spende['status'] === 'versendet'): ?>
      <button class="btn btn-outline btn-sm" style="color:var(--green)" onclick="erledigen(<?= $spende['id'] ?>, this)">✓ Als erledigt markieren</button>
    <?php endif ?>
    <?php if (in_array($spende['status'], ['freigegeben', 'versendet']) && $spende['email']): ?>
      <select id="mailSprache" class="btn btn-outline btn-sm" style="padding:6px 10px;cursor:pointer" title="Mailsprache wählen" onchange="aktualisiereMailVorschau()">
        <option value="tr">🇹🇷 Türkisch</option>
        <option value="de">🇩🇪 Deutsch</option>
      </select>
      <button id="btnMailVorschau" class="btn btn-outline btn-sm" onclick="toggleMailVorschau()">👁 Mail Vorschau</button>
      <?php if ($spende['status'] === 'freigegeben'): ?>
        <button class="btn btn-primary btn-sm" onclick="versenden(<?= $spende['id'] ?>, this)">✉ Mail versenden</button>
      <?php else: ?>
        <button class="btn btn-outline btn-sm" onclick="erneutSenden(<?= $spende['id'] ?>, this)">🔄 Erneut senden</button>
      <?php endif ?>
    <?php endif ?>
    <?php // Postversand bewusst unabhängig von der E-Mail – Spender ohne Mail können nur per Post beliefert werden ?>
    <?php if ($spende['status'] === 'freigegeben' && $spende['pdf_pfad']): ?>
      <button class="btn btn-outline btn-sm" onclick="postversand(<?= $spende['id'] ?>, this)">📬 Postversand</button>
    <?php endif ?>
    <?php if (adresseAnfragbar($spende)): ?>
      <select id="adrSprache" class="btn btn-outline btn-sm" style="padding:6px 10px;cursor:pointer" title="Sprache der Adressanfrage">
        <option value="tr">🇹🇷 Türkisch</option>
        <option value="de">🇩🇪 Deutsch</option>
      </select>
      <a id="btnAdrVorschau" href="/api/spende/<?= $spende['id'] ?>/adresse-vorschau?sprache=tr" target="_blank"
         class="btn btn-outline btn-sm" onclick="this.href='/api/spende/<?= $spende['id'] ?>/adresse-vorschau?sprache='+document.getElementById('adrSprache').value">👁 Vorschau</a>
      <button class="btn btn-primary btn-sm" onclick="adresseAnfragen(<?= $spende['id'] ?>, this)">🏠 Adresse anfragen</button>
    <?php endif ?>
  </div>
</div>

<!-- ── Mail-Vorschau Inline ── -->
<div id="mailVorschau" style="display:none;margin-bottom:20px">
  <iframe id="mailVorschauFrame" src=""
          style="width:100%;height:780px;border:1px solid var(--border);border-radius:8px;background:#fff"
          title="Mail Vorschau"></iframe>
</div>

<?php if (!empty($spende['auslaendisch'])): ?>
<div class="alert alert-warn" style="margin-bottom:16px">
  🌍 <strong>Ausländischer Spender<?= $spende['land'] ? ' (' . htmlspecialchars($spende['land']) . ')' : '' ?></strong> – Es darf keine steuerliche Spendenbescheinigung ausgestellt werden. Kein PDF erstellt.
</div>
<?php endif ?>

<?php if (adresseFehlt($spende) && empty($spende['auslaendisch'])): ?>
<?php $fehltText = adresseFehlendeFelderText($spende); ?>
<div class="alert alert-warn" style="margin-bottom:16px">
  🏠
  <?php if (substr_count($fehltText, ',') >= 2): ?>
    <strong>Keine Anschrift</strong> – ohne Adresse keine Bescheinigung.
  <?php else: ?>
    <strong>Adresse unvollständig</strong> – fehlt: <strong><?= htmlspecialchars($fehltText) ?></strong>.
  <?php endif ?>
  <?php if (adresseAnfragbar($spende)): ?>
    Per <strong>„🏠 Adresse anfragen"</strong> beim Spender nachfragen.
  <?php elseif (empty($spende['email'])): ?>
    Auch keine E-Mail vorhanden – Nachfrage nur per Telefon/Post.
  <?php endif ?>
</div>
<?php endif ?>

<?php if ($spende['mail_id']): ?>
<div id="originalMailVorschau" style="display:none;margin-bottom:20px">
  <iframe id="originalMailFrame" src=""
          style="width:100%;height:780px;border:1px solid var(--border);border-radius:8px;background:#fff"
          title="Original PayPal Mail"></iframe>
</div>
<?php endif ?>

<?php if ($spende['pdf_pfad']): ?>
<div id="pdfVorschau" style="display:none;margin-bottom:20px">
  <iframe src="/spende/<?= $spende['id'] ?>/pdf?v=<?= $pdfVersion ?>"
          style="width:100%;height:780px;border:1px solid var(--border);border-radius:8px;background:#fff"
          title="PDF Vorschau"></iframe>
</div>
<?php endif ?>

<?php if ($spende['pdf_pfad'] && $spende['mail_id']): ?>
<div id="vergleichPanel" style="display:none;margin-bottom:20px">
  <div class="vergleich-grid">
    <div class="vergleich-col">
      <div class="vergleich-header">📄 PDF Bescheinigung</div>
      <iframe id="vergleichPdfFrame" src=""
              style="width:100%;height:700px;border:1px solid var(--border);border-radius:0 0 8px 8px;background:#fff"
              title="PDF Bescheinigung"></iframe>
    </div>
    <div class="vergleich-col">
      <div class="vergleich-header">📧 Original PayPal Mail</div>
      <iframe id="vergleichMailFrame" src=""
              style="width:100%;height:700px;border:1px solid var(--border);border-radius:0 0 8px 8px;background:#fff"
              title="Original PayPal Mail"></iframe>
    </div>
  </div>
</div>
<?php endif ?>

<!-- ── Bearbeiten-Formular (aufklappbar) ── -->
<div id="editPanel" style="display:none;margin-bottom:20px">
  <div class="form-card">
    <h3>✏️ Eintrag bearbeiten</h3>
    <div class="form-grid">
      <div>
        <div class="form-row">
          <div class="fg"><label>Vorname *</label><input type="text" id="e_vorname" value="<?= htmlspecialchars($spende['vorname']) ?>"></div>
          <div class="fg"><label>Nachname *</label><input type="text" id="e_nachname" value="<?= htmlspecialchars($spende['nachname']) ?>"></div>
        </div>
        <div class="fg"><label>Straße & Hausnummer</label><input type="text" id="e_strasse" value="<?= htmlspecialchars($spende['strasse']) ?>"></div>
        <div class="form-row">
          <div class="fg"><label>PLZ</label><input type="text" id="e_plz" value="<?= htmlspecialchars($spende['plz']) ?>"></div>
          <div class="fg"><label>Ort</label><input type="text" id="e_ort" value="<?= htmlspecialchars($spende['ort']) ?>"></div>
        </div>
        <div class="fg"><label>E-Mail</label><input type="email" id="e_email" value="<?= htmlspecialchars($spende['email']) ?>"></div>
      </div>
      <div>
        <div class="form-row">
          <div class="fg"><label>Betrag (€)</label><input type="number" id="e_betrag" step="0.01" value="<?= $spende['betrag'] ?>"></div>
          <div class="fg"><label>Datum (TT.MM.JJJJ)</label><input type="text" id="e_datum" value="<?= htmlspecialchars($spende['datum']) ?>"></div>
        </div>
        <div class="fg"><label>Art der Zuwendung</label><input type="text" id="e_art" value="<?= htmlspecialchars($spende['art']) ?>"></div>
        <div class="fg">
          <label>Zahlungsweg</label>
          <select id="e_zahlungsweg">
            <?php foreach (['Überweisung','Bar','PayPal','Lastschrift'] as $zw): ?>
              <option <?= $spende['zahlungsweg']===$zw?'selected':'' ?>><?= $zw ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div style="display:flex;gap:10px;margin-top:14px">
          <button class="btn btn-primary" style="flex:1" onclick="speichernUndPdf()">💾 Speichern & PDF neu erstellen</button>
          <button class="btn btn-outline" onclick="toggleEdit()">Abbrechen</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <!-- Stammdaten -->
  <div>
    <div class="form-card">
      <h3>👤 Spender-Daten</h3>
      <table style="font-size:13px;width:100%">
        <?php $rows = [
          ['Name',        htmlspecialchars($spende['vorname'].' '.$spende['nachname'])],
          ['Adresse',     htmlspecialchars($spende['strasse']).'<br>'.htmlspecialchars($spende['plz'].' '.$spende['ort'])],
          ['E-Mail',      $spende['email'] ? '<a href="mailto:'.htmlspecialchars($spende['email']).'" style="color:var(--accent)">'.htmlspecialchars($spende['email']).'</a>' : '—'],
          ['Art',         htmlspecialchars($spende['art'])],
          ['Betrag',      '<strong style="color:var(--green)">'.number_format($spende['betrag'],2,',','.').' €</strong>'],
          ['Datum',       htmlspecialchars($spende['datum'])],
          ['Zahlungsweg', htmlspecialchars($spende['zahlungsweg'])],
          ['Quelle',      htmlspecialchars($spende['quelle'])],
        ];
        foreach ($rows as [$k,$v]): ?>
          <tr>
            <td style="color:var(--muted);padding:7px 0;width:130px;vertical-align:top"><?= $k ?></td>
            <td><?= $v ?></td>
          </tr>
        <?php endforeach ?>
        <tr>
          <td style="color:var(--muted);padding:7px 0">Status</td>
          <td><?php include __DIR__ . '/parts/status_badge.php' ?></td>
        </tr>
        <?php if ($spende['versendet_am']): ?>
          <tr>
            <td style="color:var(--muted);padding:7px 0">Versendet</td>
            <td><?= htmlspecialchars($spende['versendet_am']) ?></td>
          </tr>
        <?php endif ?>
        <?php if ($spende['pdf_pfad']): ?>
          <tr>
            <td style="color:var(--muted);padding:7px 0">PDF-Datei</td>
            <td><code style="font-size:11px;color:var(--accent)"><?= basename($spende['pdf_pfad']) ?></code></td>
          </tr>
        <?php endif ?>
        <?php if ($spende['mitgliedsnr']): ?>
          <tr>
            <td style="color:var(--muted);padding:7px 0">Mitgliedsnr</td>
            <td><?= htmlspecialchars($spende['mitgliedsnr']) ?></td>
          </tr>
        <?php endif ?>
      </table>
    </div>

    <!-- Sammelbescheinigung-Details -->
    <?php if ($spende['sammel_ids']): ?>
      <?php $items = json_decode($spende['sammel_ids'], true) ?? [] ?>
      <div class="form-card">
        <h3>📦 Sammel-Positionen</h3>
        <table style="font-size:12px;width:100%">
          <thead><tr><th style="text-align:left;color:var(--muted);padding:5px 0">Betrag</th><th style="text-align:left;color:var(--muted);padding:5px 0">Datum</th></tr></thead>
          <tbody>
            <?php foreach ($items as [$b,$d]): ?>
              <tr>
                <td style="padding:4px 0"><?= number_format((float)$b, 2, ',', '.') ?> €</td>
                <td style="padding:4px 0;color:var(--muted)"><?= htmlspecialchars($d??'') ?></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </div>

  <!-- Kommentare -->
  <div>
    <div class="form-card" style="display:flex;flex-direction:column;height:100%">
      <h3>💬 Kommentare</h3>
      <div id="kommentarListe" style="flex:1;overflow-y:auto;max-height:380px;margin-bottom:12px">
        <?php if (empty($kommentare)): ?>
          <div class="empty-state"><div class="es-icon">💬</div><p>Noch keine Kommentare.</p></div>
        <?php else: ?>
          <?php foreach ($kommentare as $k): ?>
            <div class="comment-bubble <?= $k['typ']==='system'?'sys':'usr' ?>">
              <div class="cb-meta">
                <?php
                  $autorEmail = $k['autor'] ?? '';
                  $autorName  = $cfg['user_namen'][$autorEmail] ?? $autorEmail;
                ?>
                <span title="<?= htmlspecialchars($autorEmail) ?>">
                  <?= $k['typ']==='system' ? '🤖 ' : '👤 ' ?><?= htmlspecialchars($autorName) ?>
                </span>
                <span><?= $k['erstellt_am'] ?></span>
              </div>
              <div class="cb-text"><?= htmlspecialchars($k['text']) ?></div>
            </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>
      <textarea id="newKommentar" class="cp-textarea" rows="3" placeholder="Kommentar hinzufügen…"></textarea>
      <button class="btn btn-primary btn-sm" onclick="saveKommentar()">Hinzufügen</button>
    </div>
  </div>

</div>

<div style="margin-top:8px;display:flex;gap:10px;align-items:center">
  <a href="/liste" class="btn btn-outline btn-sm">← Zurück zur Liste</a>
  <button class="btn btn-danger btn-sm" onclick="loeschen(<?= $spende['id'] ?>, '<?= htmlspecialchars($spende['vorname'].' '.$spende['nachname']) ?>', true)">🗑 Eintrag löschen</button>
</div>

<?php
$sid = $spende['id'];
$content = ob_get_clean();
$extraJs = <<<JS
function toggleMailVorschau() {
  const panel = document.getElementById('mailVorschau');
  const btn   = document.getElementById('btnMailVorschau');
  const offen = panel.style.display !== 'none';
  if (offen) {
    panel.style.display = 'none';
    document.getElementById('mailVorschauFrame').src = '';
    btn.textContent = '👁 Mail Vorschau';
  } else {
    const sprache = document.getElementById('mailSprache')?.value || 'tr';
    document.getElementById('mailVorschauFrame').src = '/api/spende/{$sid}/vorschau?sprache=' + sprache;
    panel.style.display = 'block';
    btn.textContent = '✕ Mail schließen';
  }
}
function aktualisiereMailVorschau() {
  const panel = document.getElementById('mailVorschau');
  if (panel.style.display === 'none') return;
  const sprache = document.getElementById('mailSprache')?.value || 'tr';
  document.getElementById('mailVorschauFrame').src = '/api/spende/{$sid}/vorschau?sprache=' + sprache;
}
function erneutSenden(sid, btn) {
  const sprache = document.getElementById('mailSprache')?.value || 'tr';
  if (!confirm('Mail erneut senden (Duplikat-Prüfung wird übersprungen)?')) return;
  btn.disabled = true; btn.textContent = '⏳ Sendet…';
  fetch('/api/spende/' + sid + '/erneut-senden', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({sprache})
  }).then(r => r.json()).then(d => {
    if (d.ok) { showNotif('✓ Mail erneut versendet'); setTimeout(() => window.location = '/liste', 1200); }
    else { showNotif(d.error || 'Fehler beim Senden', false); btn.disabled = false; btn.textContent = '🔄 Erneut senden'; }
  }).catch(() => { showNotif('Verbindungsfehler', false); btn.disabled = false; btn.textContent = '🔄 Erneut senden'; });
}
function nichtErforderlich(sid, btn) {
  if (!confirm('Eintrag als „Nicht erforderlich" markieren? Vorhandenes PDF wird gelöscht.')) return;
  btn.disabled = true;
  fetch('/api/spende/' + sid + '/nicht-erforderlich', { method: 'POST' })
    .then(r => r.json()).then(d => {
      if (d.ok) location.reload();
      else { showNotif(d.error || 'Fehler', false); btn.disabled = false; }
    });
}
function auslaendischToggle(sid, btn) {
  if (!confirm('Diesen Eintrag als inländischen Spender markieren? Die Seite lädt danach neu.')) return;
  btn.disabled = true;
  fetch('/api/spende/' + sid + '/inlaendisch', { method: 'POST' })
    .then(r => r.json()).then(d => {
      if (d.ok) location.reload();
      else { showNotif('Fehler', false); btn.disabled = false; }
    });
}
function reaktivieren(sid, btn) {
  if (!confirm('Fall reaktivieren? Status wird auf "Neu" zurückgesetzt und ein PDF neu generiert.')) return;
  btn.disabled = true; btn.textContent = '⏳ Reaktiviere…';
  fetch('/api/spende/' + sid + '/reaktivieren', { method: 'POST' })
    .then(r => r.json()).then(d => {
      if (d.ok) { showNotif('✓ Fall reaktiviert – PDF erstellt'); setTimeout(() => location.reload(), 1200); }
      else { showNotif(d.error || 'Fehler', false); btn.disabled = false; btn.textContent = '♻ Fall reaktivieren'; }
    }).catch(() => { showNotif('Verbindungsfehler', false); btn.disabled = false; btn.textContent = '♻ Fall reaktivieren'; });
}
function erledigen(sid, btn) {
  if (!confirm('Vorgang als „Erledigt" markieren? Alle internen Abschlussarbeiten sind abgeschlossen.')) return;
  btn.disabled = true; btn.textContent = '⏳ Markiere…';
  fetch('/api/spende/' + sid + '/erledigt', { method: 'POST' })
    .then(r => r.json()).then(d => {
      if (d.ok) { showNotif('✓ Als erledigt markiert'); setTimeout(() => location.reload(), 1200); }
      else { showNotif(d.error || 'Fehler', false); btn.disabled = false; btn.textContent = '✓ Als erledigt markieren'; }
    }).catch(() => { showNotif('Verbindungsfehler', false); btn.disabled = false; btn.textContent = '✓ Als erledigt markieren'; });
}
function toggleOriginalMail() {
  const panel = document.getElementById('originalMailVorschau');
  const btn   = document.getElementById('btnOriginalMail');
  const offen = panel.style.display !== 'none';
  if (offen) {
    panel.style.display = 'none';
    document.getElementById('originalMailFrame').src = '';
    btn.textContent = '📧 Original Mail';
  } else {
    document.getElementById('originalMailFrame').src = '/api/spende/{$sid}/original-mail';
    panel.style.display = 'block';
    btn.textContent = '✕ Original schließen';
  }
}
function togglePdfVorschau() {
  const p = document.getElementById('pdfVorschau');
  p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
function toggleVergleich() {
  const panel = document.getElementById('vergleichPanel');
  const btn   = document.getElementById('btnVergleich');
  const offen = panel.style.display !== 'none';
  if (offen) {
    panel.style.display = 'none';
    document.getElementById('vergleichPdfFrame').src  = '';
    document.getElementById('vergleichMailFrame').src = '';
    btn.textContent = '↔ Vergleich';
  } else {
    // Einzelvorschauen schließen
    document.getElementById('pdfVorschau').style.display         = 'none';
    document.getElementById('mailVorschau').style.display        = 'none';
    document.getElementById('originalMailVorschau').style.display = 'none';
    document.getElementById('vergleichPdfFrame').src  = '/spende/{$sid}/pdf?v=' + Date.now();
    document.getElementById('vergleichMailFrame').src = '/api/spende/{$sid}/original-mail';
    panel.style.display = 'block';
    btn.textContent = '✕ Vergleich schließen';
  }
}
function toggleEdit() {
  const p = document.getElementById('editPanel');
  p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
function speichernUndPdf() {
  const btn = event.target;
  btn.disabled = true; btn.textContent = '⏳ Speichert…';
  const data = {
    vorname:     document.getElementById('e_vorname').value.trim(),
    nachname:    document.getElementById('e_nachname').value.trim(),
    strasse:     document.getElementById('e_strasse').value.trim(),
    plz:         document.getElementById('e_plz').value.trim(),
    ort:         document.getElementById('e_ort').value.trim(),
    email:       document.getElementById('e_email').value.trim(),
    betrag:      parseFloat(document.getElementById('e_betrag').value),
    datum:       document.getElementById('e_datum').value.trim(),
    art:         document.getElementById('e_art').value.trim(),
    zahlungsweg: document.getElementById('e_zahlungsweg').value,
  };
  fetch('/api/spende/{$sid}/bearbeiten', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  }).then(r => r.json()).then(d => {
    if (d.ok) {
      // Bei versendeten Bescheinigungen bleibt die PDF unverändert (Archivschutz)
      showNotif(d.hinweis ? '✓ ' + d.hinweis : '✓ Gespeichert – PDF neu erstellt');
      setTimeout(() => location.reload(), d.hinweis ? 2500 : 1200);
    } else {
      showNotif(d.error || 'Fehler', false);
      btn.disabled = false; btn.textContent = '💾 Speichern & PDF neu erstellen';
    }
  }).catch(() => {
    showNotif('Verbindungsfehler', false);
    btn.disabled = false; btn.textContent = '💾 Speichern & PDF neu erstellen';
  });
}
function saveKommentar() {
  const t = document.getElementById('newKommentar').value.trim();
  if (!t) return;
  fetch('/api/spende/{$sid}/kommentar', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({text: t})
  }).then(r => r.json()).then(list => {
    const b = document.getElementById('kommentarListe');
    if (!list.length) return;
    b.innerHTML = list.map(k => `
      <div class="comment-bubble \${k.typ==='system'?'sys':'usr'}">
        <div class="cb-meta"><span>\${k.typ==='system'?'🤖 System':'👤 '+autorName(k.autor)}</span><span>\${k.erstellt_am}</span></div>
        <div class="cb-text">\${k.text}</div>
      </div>`).join('');
    document.getElementById('newKommentar').value = '';
    b.scrollTop = b.scrollHeight;
    showNotif('Kommentar gespeichert');
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
