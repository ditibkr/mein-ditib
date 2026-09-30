<?php
$title = 'PDF-Import';
$page  = 'import';
ob_start();

$offen  = array_filter($eintraege, fn ($e) => $e['status'] !== 'uebernommen');
$fertig = array_filter($eintraege, fn ($e) => $e['status'] === 'uebernommen');

$cfg     = require ROOT . '/config/settings.php';
$ordner  = $cfg['import_dir'] ?? '/var/www/html/import_eingang';
$imAordner = is_dir($ordner) ? count(glob(rtrim($ordner, '/') . '/*.[pP][dD][fF]') ?: []) : 0;
$indexGroesse = \App\Imap\AnhangIndex::anzahl();
?>

<div class="page-header">
  <div>
    <h2>Bestehende Bescheinigungen importieren</h2>
    <p>Alte PDFs einlesen · Prüfen · Freigeben. Erst die Freigabe erzeugt einen Eintrag.</p>
  </div>
</div>

<!-- ── Massenimport aus dem Serververzeichnis ── -->
<div class="card">
  <div class="card-header">
    <div>
      <h3>Massenimport aus dem Verzeichnis</h3>
      <p>Der Weg für viele Dateien – der Browser-Upload schafft nur ~6 PDFs pro Durchgang</p>
    </div>
    <span class="chip"><b><?= $imAordner ?></b> PDFs im Ordner</span>
  </div>

  <ol style="color:var(--text2);font-size:13px;line-height:1.9;margin:0 0 14px;padding-left:20px">
    <li>PDFs auf den Server kopieren nach
      <code>spendenportal_php/import_eingang/</code></li>
    <li><strong>Verzeichnis einlesen</strong> – parst alle PDFs in den Prüfbereich (100 pro Durchgang)</li>
    <li><strong>Mail-Abgleich</strong> – vergleicht jede Datei mit den gesendeten Anhängen.
      Treffer = E-Mail wird ergänzt. Kein Treffer = Eintrag ohne E-Mail.</li>
  </ol>

  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-primary" onclick="verzeichnisEinlesen(this)">📂 Verzeichnis einlesen</button>
    <button class="btn btn-outline" onclick="mailIndex(this)">📧 Mail-Index aufbauen
      <span class="text-muted">(<?= $indexGroesse ?> Anhänge)</span></button>
    <button class="btn btn-outline" onclick="mailAbgleich(this)">🔗 Mail-Abgleich starten</button>
  </div>
  <div id="importStatus" class="text-small text-muted" style="margin-top:10px"></div>
</div>

<!-- ── Einzel-Upload ── -->
<div class="card">
  <div class="card-header">
    <div>
      <h3>Einzelne PDFs hochladen</h3>
      <p>Für Nachzügler · max. ~6 Dateien pro Durchgang (Server-Limit 8 MB)</p>
    </div>
  </div>
  <form method="post" action="/import/upload" enctype="multipart/form-data"
        style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="file" name="pdfs[]" accept="application/pdf" multiple required
           style="flex:1;min-width:240px;background:var(--bg);border:1px solid var(--border);
                  border-radius:8px;padding:8px 10px;color:var(--text);font-family:inherit">
    <button type="submit" class="btn btn-primary">📥 Einlesen</button>
  </form>
  <div class="alert alert-warn" style="margin-top:12px">
    Das Einlesen ändert noch <strong>nichts</strong> an den Spenderdaten. Die Einträge landen unten
    zur Prüfung. Das Original-PDF wird übernommen und <strong>nie neu erzeugt</strong>.
  </div>
</div>

<!-- ── Eingang / Prüfung ── -->
<div class="card">
  <div class="card-header">
    <div>
      <h3>Zur Prüfung <span class="text-muted">(<?= count($offen) ?>)</span></h3>
      <p>Daten kontrollieren, Zahlungsweg setzen, dann freigeben</p>
    </div>
  </div>

  <?php if (empty($offen)): ?>
    <div class="empty-state">
      <div class="es-icon">📄</div>
      <p>Nichts zu prüfen. Lade oben PDFs hoch.</p>
    </div>
  <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Datei</th>
            <th>Spender</th>
            <th>Betrag</th>
            <th>Datum</th>
            <th>Zuwendungsart / Jahr</th>
            <th>Zahlungsweg *</th>
            <th>Prüfung</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($offen as $e): ?>
            <tr data-id="<?= $e['id'] ?>">
              <td>
                <a href="/import/<?= $e['id'] ?>/pdf" target="_blank" style="color:var(--accent)">
                  <?= htmlspecialchars(mb_strimwidth($e['dateiname'], 0, 28, '…')) ?>
                </a>
              </td>
              <td>
                <input class="f" data-f="vorname" value="<?= htmlspecialchars($e['vorname']) ?>" placeholder="Vorname" style="width:90px">
                <input class="f" data-f="nachname" value="<?= htmlspecialchars($e['nachname']) ?>" placeholder="Nachname" style="width:100px">
                <br>
                <input class="f" data-f="strasse" value="<?= htmlspecialchars($e['strasse']) ?>" placeholder="Straße" style="width:120px">
                <input class="f" data-f="plz" value="<?= htmlspecialchars($e['plz']) ?>" placeholder="PLZ" style="width:56px">
                <input class="f" data-f="ort" value="<?= htmlspecialchars($e['ort']) ?>" placeholder="Ort" style="width:100px">
                <br>
                <input class="f" data-f="email" value="<?= htmlspecialchars($e['email']) ?>"
                       placeholder="keine E-Mail (bar/Überweisung)" style="width:220px">
                <?php if ($e['mail_gefunden']): ?>
                  <span class="badge b-mail" title="Datei ist byte-identisch mit dem Anhang einer gesendeten Mail">
                    ✉ aus Mail<?= $e['mail_datum'] ? ' ' . date('d.m.Y', strtotime($e['mail_datum'])) : '' ?>
                  </span>
                <?php else: ?>
                  <span class="badge b-nomail" title="Diese Datei wurde nie per Mail verschickt">nie gemailt</span>
                <?php endif ?>
              </td>
              <td>
                <input class="f" data-f="betrag" value="<?= number_format((float) $e['betrag'], 2, ',', '') ?>" style="width:70px"> €
                <?php if ($e['betrag_wort']): ?>
                  <br><span class="text-muted text-small"><?= htmlspecialchars($e['betrag_wort']) ?></span>
                <?php endif ?>
              </td>
              <td><input class="f" data-f="datum" value="<?= htmlspecialchars($e['datum']) ?>" style="width:86px"></td>
              <td>
                <select class="f" data-f="zuwendungsart" style="width:130px">
                  <option value="">– wählen –</option>
                  <?php foreach (['Geldzuwendung', 'Mitgliedsbeitrag'] as $z): ?>
                    <option value="<?= $z ?>" <?= $e['zuwendungsart'] === $z ? 'selected' : '' ?>><?= $z ?></option>
                  <?php endforeach ?>
                </select>
                <br>
                <input class="f" data-f="jahr" value="<?= htmlspecialchars($e['jahr']) ?>"
                       placeholder="Jahr" style="width:60px">
                <span class="text-muted text-small" title="So steht es im PDF">
                  <?= htmlspecialchars(mb_strimwidth($e['art'], 0, 22, '…')) ?>
                </span>
              </td>
              <td>
                <select class="f" data-f="zahlungsweg" style="width:120px">
                  <option value="">– wählen –</option>
                  <?php foreach (['Bar', 'Überweisung', 'PayPal', 'Lastschrift'] as $w): ?>
                    <option value="<?= $w ?>" <?= $e['zahlungsweg'] === $w ? 'selected' : '' ?>><?= $w ?></option>
                  <?php endforeach ?>
                </select>
              </td>
              <td>
                <?php if ($e['fehler']): ?>
                  <span class="badge b-fehler">⛔ <?= htmlspecialchars($e['fehler']) ?></span>
                <?php elseif ($e['dup_id']): ?>
                  <span class="badge b-dup">⛔ Duplikat?</span>
                  <br><span class="text-small">
                    <a href="/spende/<?= $e['dup_id'] ?>" target="_blank" style="color:var(--muted)">
                      #<?= $e['dup_id'] ?> <?= htmlspecialchars($e['dup_nachname']) ?>
                      <?= number_format((float) $e['dup_betrag'], 2, ',', '.') ?> €
                    </a>
                  </span>
                <?php elseif ($e['warnungen']): ?>
                  <span class="badge b-warn">⚠ Prüfen</span>
                  <br><span class="text-small text-muted"><?= htmlspecialchars($e['warnungen']) ?></span>
                <?php else: ?>
                  <span class="badge b-ok">✓ Gegenprobe ok</span>
                <?php endif ?>
              </td>
              <td class="td-actions">
                <button class="icon-btn" onclick="importSpeichern(<?= $e['id'] ?>, this)">💾 Speichern</button>
                <button class="icon-btn approve" onclick="importUebernehmen(<?= $e['id'] ?>, this)">✓ Freigeben</button>
                <button class="icon-btn" onclick="importVerwerfen(<?= $e['id'] ?>, this)">🗑</button>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <p class="text-muted text-small" style="margin-top:10px">
      * Der Zahlungsweg steht nicht im PDF und muss gesetzt werden (bar, Überweisung, Mitgliedsbeitrag …).
    </p>
  <?php endif ?>
</div>

<!-- ── Übernommen ── -->
<?php if (!empty($fertig)): ?>
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Übernommen <span class="text-muted">(<?= count($fertig) ?>)</span></h3>
        <p>Stehen jetzt in der Spenderübersicht – mit dem Original-PDF</p>
      </div>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>Datei</th><th>Spender</th><th>Betrag</th><th>Datum</th><th>Eintrag</th></tr>
        </thead>
        <tbody>
          <?php foreach ($fertig as $e): ?>
            <tr>
              <td><?= htmlspecialchars(mb_strimwidth($e['dateiname'], 0, 30, '…')) ?></td>
              <td><?= htmlspecialchars(trim($e['vorname'] . ' ' . $e['nachname'])) ?></td>
              <td><strong><?= number_format((float) $e['betrag'], 2, ',', '.') ?> €</strong></td>
              <td><?= htmlspecialchars($e['datum']) ?></td>
              <td><a href="/spende/<?= $e['spende_id'] ?>" class="icon-btn preview">Spende #<?= $e['spende_id'] ?> →</a></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif ?>

<style>
  .f {
    background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
    color: var(--text); font-family: inherit; font-size: 12px; padding: 4px 6px; margin: 1px 0;
  }
  .f:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
  .badge.b-ok { background: rgba(62,207,142,.15); color: var(--green); }
  .badge.b-warn { background: rgba(247,201,79,.15); color: var(--accent2); }
  .badge.b-dup, .badge.b-fehler { background: rgba(247,111,79,.15); color: var(--red); }
  .badge.b-mail { background: rgba(79,142,247,.15); color: var(--accent); }
  .badge.b-nomail { background: var(--surface2); color: var(--muted); }
</style>

<script>
  const status = (t) => document.getElementById('importStatus').textContent = t;

  async function verzeichnisEinlesen(btn) {
    btn.disabled = true;
    status('Lese Verzeichnis … das kann bei vielen PDFs dauern.');
    try {
      const r = await fetch('/api/import/verzeichnis', { method: 'POST' }).then(r => r.json());
      if (!r.ok) { status('Fehler: ' + r.error); btn.disabled = false; return; }
      status(`${r.gefunden} Dateien gefunden · ${r.neu} neu eingelesen · ${r.uebersprungen} schon bekannt · `
        + `${r.fehler} nicht lesbar${r.rest ? ` · ${r.rest} noch offen – nochmal klicken` : ''}`);
      setTimeout(() => location.reload(), 1800);
    } catch (e) {
      status('Fehler beim Einlesen');
      btn.disabled = false;
    }
  }

  async function mailIndex(btn) {
    btn.disabled = true;
    status('Durchsuche "Gesendete Objekte" ab 2024 … das dauert ein paar Minuten.');
    try {
      const r = await fetch('/api/import/mailindex', { method: 'POST' }).then(r => r.json());
      status(`${r.mails} Mails durchsucht · ${r.anhaenge} PDF-Anhänge · ${r.neu} neu im Index`);
    } catch (e) {
      status('Fehler beim Mail-Index');
    }
    btn.disabled = false;
  }

  async function mailAbgleich(btn) {
    btn.disabled = true;
    status('Vergleiche Dateien mit den gesendeten Anhängen …');
    try {
      const r = await fetch('/api/import/mailabgleich', { method: 'POST' }).then(r => r.json());
      status(`${r.geprueft} geprüft · ${r.treffer} einer Mail zugeordnet (E-Mail ergänzt) · `
        + `${r.ohne_mail} nie gemailt`);
      setTimeout(() => location.reload(), 1800);
    } catch (e) {
      status('Fehler beim Abgleich');
      btn.disabled = false;
    }
  }
</script>

<script>
  function importFelder(id) {
    const tr = document.querySelector(`tr[data-id="${id}"]`);
    const d = {};
    tr.querySelectorAll('.f').forEach(el => d[el.dataset.f] = el.value);
    return d;
  }

  async function importSpeichern(id, btn) {
    btn.disabled = true;
    const r = await fetch(`/api/import/${id}/bearbeiten`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(importFelder(id))
    }).then(r => r.json());
    btn.disabled = false;
    showNotif(r.ok ? '✓ Gespeichert' : (r.error || 'Fehler'), !!r.ok);
  }

  // Freigabe: erst speichern, dann übernehmen – sonst ginge eine Korrektur verloren
  async function importUebernehmen(id, btn) {
    btn.disabled = true;
    await fetch(`/api/import/${id}/bearbeiten`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(importFelder(id))
    });
    const r = await fetch(`/api/import/${id}/uebernehmen`, { method: 'POST' }).then(r => r.json());
    if (r.ok) {
      showNotif('✓ Übernommen als Spende #' + r.spende_id);
      setTimeout(() => location.reload(), 1000);
    } else {
      showNotif(r.error || 'Fehler', false);
      btn.disabled = false;
    }
  }

  async function importVerwerfen(id, btn) {
    if (!confirm('Diesen Import verwerfen?')) return;
    btn.disabled = true;
    const r = await fetch(`/api/import/${id}/verwerfen`, { method: 'POST' }).then(r => r.json());
    if (r.ok) location.reload();
  }
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
