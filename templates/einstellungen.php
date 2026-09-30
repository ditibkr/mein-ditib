<?php
$title = 'Einstellungen';
$page = 'einstellungen';
ob_start();

$settingsPath = ROOT . '/config/settings.php';
$saved = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $cfg = require $settingsPath;
  // Nur sichere Felder übernehmen
  $allowed = [
    'admin_user', 'admin_password',
    'imap_host', 'imap_port', 'imap_user', 'imap_password',
    'imap_folder', 'imap_subject_filter', 'imap_done_folder',
    'imap_low_folder', 'imap_sent_folder',
    'smtp_host', 'smtp_port', 'smtp_user', 'smtp_password',
    'smtp_from_name', 'smtp_from_email',
    'mindestbetrag', 'mail_limit', 'cron_hour', 'cron_minute',
    'smtp_mailtext', 'smtp_mailtext_de', 'smtp_subject_tr', 'smtp_subject_de', 'imap_seit_datum', 'test_email',
    'smtp_mailtext_adresse', 'smtp_mailtext_adresse_de', 'smtp_subject_adresse_tr', 'smtp_subject_adresse_de',
    'report_email', 'pdf_owner_passwort', 'pdf_import_passwort',
    'paypal_mode', 'paypal_client_id', 'paypal_secret', 'paypal_import_tage', 'paypal_seit_datum',
  ];
  foreach ($allowed as $key) {
    if (isset($_POST[$key])) {
      $val = $_POST[$key];
      if (in_array($key, ['imap_port', 'smtp_port', 'mail_limit', 'cron_hour', 'cron_minute', 'paypal_import_tage']))
        $val = (int) $val;
      if ($key === 'mindestbetrag')
        $val = (float) str_replace(',', '.', $val);
      $cfg[$key] = $val;
    }
  }
  // User-Namen-Mapping aufbauen
  $userNamen = [];
  $unEmails  = $_POST['un_email'] ?? [];
  $unNamen   = $_POST['un_name']  ?? [];
  foreach ($unEmails as $i => $email) {
      $email = trim($email);
      $name  = trim($unNamen[$i] ?? '');
      if ($email !== '' && $name !== '') {
          $userNamen[$email] = $name;
      }
  }
  $cfg['user_namen'] = $userNamen;

  // Checkbox: testmodus (nur gesetzt wenn angehakt, sonst false)
  $cfg['testmodus'] = isset($_POST['testmodus']);
  // Checkbox: PayPal-API-Import
  $cfg['paypal_api_aktiv'] = isset($_POST['paypal_api_aktiv']);
  // Checkbox: Mail-Parsing im Cron-Job
  $cfg['imap_verarbeitung_aktiv'] = isset($_POST['imap_verarbeitung_aktiv']);
  // Checkbox: Report-Mail nach dem Cron-Job
  $cfg['report_aktiv'] = isset($_POST['report_aktiv']);
  // Checkbox: Guilloche-Sicherheitsmuster in PDFs
  $cfg['pdf_guilloche_aktiv'] = isset($_POST['pdf_guilloche_aktiv']);
  // Datei neu schreiben
  $export = "<?php\nreturn " . var_export($cfg, true) . ";\n";
  if (file_put_contents($settingsPath, $export)) {
    header('Location: /einstellungen?saved=1');
    exit;
  } else {
    $error = 'Datei konnte nicht gespeichert werden – Schreibrechte prüfen!';
  }
}

$saved = isset($_GET['saved']);
$cfg = require $settingsPath;
?>

<?php if ($saved): ?>
  <div class="flash flash-success">✓ Einstellungen gespeichert</div>
<?php endif ?>
<?php if ($error): ?>
  <div class="flash flash-error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif ?>

<form method="post" action="/einstellungen">
<div class="page-header">
  <div>
    <h2>Einstellungen</h2>
    <p>Direkte Bearbeitung der config/settings.php – kein Docker-Neustart nötig</p>
  </div>
  <div class="page-header-actions">
    <button type="submit" class="btn btn-primary">💾 Speichern</button>
  </div>
</div>
  <div class="form-grid">

    <!-- ── Linke Spalte: E-Mail & Versand ── -->
    <div>
      <!-- Login
  <div class="form-card">
    <h3>🔐 Web-Login</h3>
    <div class="form-row">
      <div class="fg"><label>Benutzername</label>
        <input type="text" name="admin_user" value="<?= htmlspecialchars($cfg['admin_user']) ?>"></div>
      <div class="fg"><label>Passwort</label>
        <input type="password" name="admin_password" value="<?= htmlspecialchars($cfg['admin_password']) ?>"></div>
    </div>
  </div>-->

      <!-- User-Namen -->
      <div class="form-card">
        <h3>👤 Benutzer-Anzeigenamen</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:14px">
          Cloudflare Zero Trust übergibt die E-Mail-Adresse des eingeloggten Users.
          Hier kannst du jedem User einen Anzeigenamen zuweisen – dieser erscheint in Kommentaren und Logs.
        </p>
        <div id="user-namen-liste">
          <?php foreach ($cfg['user_namen'] ?? [] as $email => $name): ?>
          <div class="form-row user-namen-zeile" style="margin-bottom:6px">
            <div class="fg"><input type="email" name="un_email[]" value="<?= htmlspecialchars($email) ?>"
              placeholder="email@beispiel.de" style="width:100%"></div>
            <div class="fg" style="display:flex;align-items:center;gap:4px">
              <input type="text" name="un_name[]" value="<?= htmlspecialchars($name) ?>"
                placeholder="Anzeigename" style="flex:1">
              <button type="button" onclick="this.closest('.user-namen-zeile').remove()"
                style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0 4px;line-height:1;flex-shrink:0" title="Entfernen">×</button>
            </div>
          </div>
          <?php endforeach ?>
        </div>
        <button type="button" onclick="userNamenZeileAdd()"
          class="btn btn-outline btn-sm" style="margin-top:6px">+ User hinzufügen</button>
      </div>

      <!-- Mail-Texte -->
      <div class="form-card">
        <h3>📨 E-Mail Texte (HTML erlaubt)</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:12px">
          Platzhalter: <code>{name}</code> = Vor- und Nachname des Spenders.
          HTML-Tags wie <code>&lt;p&gt;</code>, <code>&lt;b&gt;</code>, <code>&lt;br&gt;</code> sind erlaubt.
        </p>

        <!-- Tab-Umschalter -->
        <div style="display:flex;gap:0;margin-bottom:12px;border-bottom:2px solid var(--border)">
          <button type="button" id="tab-tr" onclick="switchTab('tr')"
            style="padding:7px 18px;border:none;background:var(--accent);color:#fff;border-radius:6px 6px 0 0;font-weight:700;cursor:pointer">
            🇹🇷 Türkisch
          </button>
          <button type="button" id="tab-de" onclick="switchTab('de')"
            style="padding:7px 18px;border:none;background:var(--surface);color:var(--muted);border-radius:6px 6px 0 0;font-weight:700;cursor:pointer;margin-left:4px">
            🇩🇪 Deutsch
          </button>
        </div>

        <div id="panel-tr">
          <div class="fg" style="margin-bottom:10px">
            <label>Betreff Türkisch</label>
            <input type="text" name="smtp_subject_tr" value="<?= htmlspecialchars($cfg['smtp_subject_tr'] ?? '') ?>" style="width:100%">
          </div>
          <div class="fg">
            <label>Mailtext Türkisch (HTML)</label>
            <textarea name="smtp_mailtext" rows="14"
              style="width:100%;font-family:monospace;font-size:12px"><?= htmlspecialchars($cfg['smtp_mailtext']) ?></textarea>
          </div>
          <div style="margin-top:8px">
            <a href="/api/spende/vorschau-template?sprache=tr" target="_blank" class="btn btn-outline btn-sm">👁 Vorschau</a>
          </div>
        </div>
        <div id="panel-de" style="display:none">
          <div class="fg" style="margin-bottom:10px">
            <label>Betreff Deutsch</label>
            <input type="text" name="smtp_subject_de" value="<?= htmlspecialchars($cfg['smtp_subject_de'] ?? '') ?>" style="width:100%">
          </div>
          <div class="fg">
            <label>Mailtext Deutsch (HTML)</label>
            <textarea name="smtp_mailtext_de" rows="14"
              style="width:100%;font-family:monospace;font-size:12px"><?= htmlspecialchars($cfg['smtp_mailtext_de'] ?? '') ?></textarea>
          </div>
          <div style="margin-top:8px">
            <a href="/api/spende/vorschau-template?sprache=de" target="_blank" class="btn btn-outline btn-sm">👁 Vorschau</a>
          </div>
        </div>
      </div>

      <!-- Adressanfrage bei fehlender Anschrift -->
      <div class="form-card">
        <h3>🏠 E-Mail Adressanfrage (HTML erlaubt)</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:12px">
          Wird nur manuell über den Button <strong>„🏠 Adresse anfragen"</strong> versendet – nie automatisch.
          Für Spender (meist aus PayPal), zu denen keine Anschrift vorliegt.<br>
          Platzhalter: <code>{name}</code>, <code>{betrag}</code>, <code>{datum}</code>,
          <code>{adresse_hinweis}</code> <span style="color:var(--muted)">(automatischer Text: „ohne Anschrift" oder „unvollständige Anschrift")</span>,
          <code>{adresse_status}</code> <span style="color:var(--muted)">(Tabelle mit ✅/❌ je Feld – Straße, PLZ, Ort)</span>.
        </p>

        <div style="display:flex;gap:0;margin-bottom:12px;border-bottom:2px solid var(--border)">
          <button type="button" id="tab-tr-adr" onclick="switchTab('tr','-adr')"
            style="padding:7px 18px;border:none;background:var(--accent);color:#fff;border-radius:6px 6px 0 0;font-weight:700;cursor:pointer">
            🇹🇷 Türkisch
          </button>
          <button type="button" id="tab-de-adr" onclick="switchTab('de','-adr')"
            style="padding:7px 18px;border:none;background:var(--surface);color:var(--muted);border-radius:6px 6px 0 0;font-weight:700;cursor:pointer;margin-left:4px">
            🇩🇪 Deutsch
          </button>
        </div>

        <div id="panel-tr-adr">
          <div class="fg" style="margin-bottom:10px">
            <label>Betreff Türkisch</label>
            <input type="text" name="smtp_subject_adresse_tr" value="<?= htmlspecialchars($cfg['smtp_subject_adresse_tr'] ?? '') ?>" style="width:100%">
          </div>
          <div class="fg">
            <label>Mailtext Türkisch (HTML)</label>
            <textarea name="smtp_mailtext_adresse" rows="14"
              style="width:100%;font-family:monospace;font-size:12px"><?= htmlspecialchars($cfg['smtp_mailtext_adresse'] ?? '') ?></textarea>
          </div>
          <div style="margin-top:8px">
            <a href="/api/spende/vorschau-template?typ=adresse&sprache=tr" target="_blank" class="btn btn-outline btn-sm">👁 Vorschau</a>
          </div>
        </div>
        <div id="panel-de-adr" style="display:none">
          <div class="fg" style="margin-bottom:10px">
            <label>Betreff Deutsch</label>
            <input type="text" name="smtp_subject_adresse_de" value="<?= htmlspecialchars($cfg['smtp_subject_adresse_de'] ?? '') ?>" style="width:100%">
          </div>
          <div class="fg">
            <label>Mailtext Deutsch (HTML)</label>
            <textarea name="smtp_mailtext_adresse_de" rows="14"
              style="width:100%;font-family:monospace;font-size:12px"><?= htmlspecialchars($cfg['smtp_mailtext_adresse_de'] ?? '') ?></textarea>
          </div>
          <div style="margin-top:8px">
            <a href="/api/spende/vorschau-template?typ=adresse&sprache=de" target="_blank" class="btn btn-outline btn-sm">👁 Vorschau</a>
          </div>
        </div>
      </div>

      <!-- SMTP -->
      <div class="form-card">
        <h3>✉️ SMTP – Mails versenden</h3>
        <div class="form-row">
          <div class="fg"><label>Host</label><input type="text" name="smtp_host"
              value="<?= htmlspecialchars($cfg['smtp_host']) ?>"></div>
          <div class="fg"><label>Port</label><input type="number" name="smtp_port" value="<?= $cfg['smtp_port'] ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="fg"><label>Benutzer</label><input type="text" name="smtp_user"
              value="<?= htmlspecialchars($cfg['smtp_user']) ?>"></div>
          <div class="fg"><label>Passwort</label><input type="password" name="smtp_password"
              value="<?= htmlspecialchars($cfg['smtp_password']) ?>"></div>
        </div>
        <div class="form-row">
          <div class="fg"><label>Absender Name</label><input type="text" name="smtp_from_name"
              value="<?= htmlspecialchars($cfg['smtp_from_name']) ?>"></div>
          <div class="fg"><label>Absender E-Mail</label><input type="email" name="smtp_from_email"
              value="<?= htmlspecialchars($cfg['smtp_from_email']) ?>"></div>
        </div>
      </div>

      <!-- Testmodus -->
      <div class="form-card">
        <h3>🧪 Testmodus</h3>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:10px">
          <input type="checkbox" name="testmodus" value="1" <?= !empty($cfg['testmodus']) ? 'checked' : '' ?>
            style="width:18px;height:18px;accent-color:var(--accent2)">
          <span>Testmodus aktiv – Versandmails gehen an Test-Adresse statt an Spender</span>
        </label>
        <div class="fg">
          <label>Test-E-Mail-Adresse</label>
          <input type="email" name="test_email" value="<?= htmlspecialchars($cfg['test_email'] ?? '') ?>" placeholder="test@beispiel.de">
        </div>
        <?php if (!empty($cfg['testmodus'])): ?>
          <div class="alert alert-warn" style="margin-top:8px">⚠️ Testmodus ist derzeit <strong>AKTIV</strong></div>
        <?php else: ?>
          <div style="margin-top:8px;padding:8px 12px;background:rgba(80,200,120,.08);border:1px solid rgba(80,200,120,.25);border-radius:6px;font-size:13px;color:var(--green)">✓ Produktivmodus – Mails gehen direkt an Spender</div>
        <?php endif ?>
      </div>

      <!-- Report-Mail -->
      <div class="form-card">
        <h3>📊 Report-Mail nach dem Cron-Job</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:10px">
          Sendet nach jedem Cron-Lauf eine Übersicht: wie viele Spenden auf <strong>neu</strong>,
          <strong>freigegeben</strong> und <strong>versendet</strong> stehen, plus das Ergebnis des Laufs.
        </p>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:10px">
          <input type="checkbox" name="report_aktiv" value="1" <?= !empty($cfg['report_aktiv']) ? 'checked' : '' ?>
            style="width:18px;height:18px;accent-color:var(--accent2)">
          <span>Report-Mail aktiv</span>
        </label>
        <div class="fg">
          <label>Empfänger</label>
          <input type="email" name="report_email" value="<?= htmlspecialchars($cfg['report_email'] ?? '') ?>"
            placeholder="<?= htmlspecialchars($cfg['smtp_from_email'] ?? '') ?> (Absenderadresse)">
        </div>
      </div>

      <!-- PDF-Sicherheit -->
      <div class="form-card">
        <h3>🔒 PDF-Sicherheit</h3>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:10px">
          <input type="checkbox" name="pdf_guilloche_aktiv" value="1" <?= !empty($cfg['pdf_guilloche_aktiv']) ? 'checked' : '' ?>
            style="width:18px;height:18px;accent-color:var(--accent)">
          <span>Guilloche-Sicherheitsmuster in neuen PDFs aktivieren</span>
        </label>
        <?php if (empty($cfg['pdf_guilloche_aktiv'])): ?>
          <div class="alert alert-warn" style="margin-bottom:10px">⚠️ Guilloche ist <strong>deaktiviert</strong> – neue Bescheinigungen enthalten kein Manipulationsschutz-Muster.</div>
        <?php else: ?>
          <div style="margin-bottom:10px;padding:8px 12px;background:rgba(80,200,120,.08);border:1px solid rgba(80,200,120,.25);border-radius:6px;font-size:13px;color:var(--green)">✓ Aktiv – neue PDFs erhalten das Guilloche-Sicherheitsmuster hinter Name, Betrag und Betrag in Worten.</div>
        <?php endif ?>
        <p style="font-size:12px;color:var(--muted);margin-bottom:10px">
          Das <strong>Owner-Passwort</strong> ist der Schreibschutz der erzeugten Bescheinigungen –
          Öffnen bleibt frei, nur Ändern ist gesperrt. Leer = bisheriges Schema
          (<code>DITIB_OWNER_&lt;Jahr&gt;</code>).
        </p>
        <div class="fg">
          <label>Owner-Passwort (neue PDFs)</label>
          <input type="password" name="pdf_owner_passwort"
            value="<?= htmlspecialchars($cfg['pdf_owner_passwort'] ?? '') ?>" placeholder="leer = DITIB_OWNER_<Jahr>">
        </div>
        <div class="fg">
          <label>Import-Passwort (alte PDFs)</label>
          <input type="password" name="pdf_import_passwort"
            value="<?= htmlspecialchars($cfg['pdf_import_passwort'] ?? '') ?>" placeholder="normalerweise leer">
        </div>
        <div style="margin-top:8px;padding:8px 12px;background:rgba(80,200,120,.08);border:1px solid rgba(80,200,120,.25);border-radius:6px;font-size:12px;color:var(--green)">
          ✓ Die alten Bescheinigungen haben ein leeres Öffnen-Passwort – der Import braucht hier
          normalerweise nichts. Das Feld ist nur die Rückfallebene.
        </div>
      </div>
    </div>

    <!-- ── Rechte Spalte: Import, Regeln & System ── -->
    <div>
      <!-- IMAP -->
      <div class="form-card">
        <h3>📨 IMAP – Postfach abrufen</h3>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:10px">
          <input type="checkbox" name="imap_verarbeitung_aktiv" value="1" <?= !empty($cfg['imap_verarbeitung_aktiv']) ? 'checked' : '' ?>
            style="width:18px;height:18px;accent-color:var(--accent)">
          <span>Mail-Parsing im nächtlichen Cron-Job aktiv</span>
        </label>
        <?php if (empty($cfg['imap_verarbeitung_aktiv'])): ?>
          <div class="alert alert-warn" style="margin-bottom:10px">⚠️ Mail-Parsing ist <strong>deaktiviert</strong> – Spenden werden nur über die PayPal-API importiert. Mail-Versand und IMAP-Archiv funktionieren weiter.</div>
        <?php else: ?>
          <div style="margin-bottom:10px;padding:8px 12px;background:rgba(80,200,120,.08);border:1px solid rgba(80,200,120,.25);border-radius:6px;font-size:13px;color:var(--green)">✓ Aktiv – beim Go-Live der PayPal-API abschalten, sonst werden Spenden doppelt importiert!</div>
        <?php endif ?>
        <div class="form-row">
          <div class="fg"><label>Host</label><input type="text" name="imap_host"
              value="<?= htmlspecialchars($cfg['imap_host']) ?>"></div>
          <div class="fg"><label>Port</label><input type="number" name="imap_port" value="<?= $cfg['imap_port'] ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="fg"><label>Benutzer</label><input type="text" name="imap_user"
              value="<?= htmlspecialchars($cfg['imap_user']) ?>"></div>
          <div class="fg"><label>Passwort</label><input type="password" name="imap_password"
              value="<?= htmlspecialchars($cfg['imap_password']) ?>"></div>
        </div>
        <div class="form-row">
          <div class="fg"><label>Postfach (Inbox)</label><input type="text" name="imap_folder"
              value="<?= htmlspecialchars($cfg['imap_folder']) ?>"></div>
          <div class="fg"><label>Betreff-Filter</label><input type="text" name="imap_subject_filter"
              value="<?= htmlspecialchars($cfg['imap_subject_filter']) ?>"></div>
        </div>
        <div class="form-row">
          <div class="fg"><label>Ordner „Erledigt"</label><input type="text" name="imap_done_folder"
              value="<?= htmlspecialchars($cfg['imap_done_folder']) ?>"></div>
          <div class="fg"><label>Ordner „Geringbetrag"</label><input type="text" name="imap_low_folder"
              value="<?= htmlspecialchars($cfg['imap_low_folder']) ?>"></div>
        </div>
        <div class="fg"><label>Gesendete-Objekte Ordner</label>
          <input type="text" name="imap_sent_folder" value="<?= htmlspecialchars($cfg['imap_sent_folder']) ?>">
        </div>
        <div class="fg"><label>Mails einlesen ab Datum (JJJJ-MM-TT)</label>
          <input type="date" name="imap_seit_datum" value="<?= htmlspecialchars($cfg['imap_seit_datum'] ?? '') ?>">
          <small style="color:var(--muted);font-size:11px">Nur Mails ab diesem Datum werden geladen – verhindert Timeout bei großen Postfächern</small>
        </div>
      </div>

      <!-- IMAP-Test -->
      <div class="form-card">
        <h3>🔬 IMAP-Verbindung testen</h3>
        <button type="button" class="btn btn-outline btn-sm" onclick="testImap()">Verbindung testen</button>
        <div id="imap-result" style="margin-top:10px;font-size:12px;display:none"></div>
      </div>

      <!-- PayPal API -->
      <div class="form-card">
        <h3>💳 PayPal API – Transaktionsimport</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:10px">
          Importiert Zahlungen direkt über die PayPal Transaction Search API (statt aus E-Mails).
          App anlegen unter <a href="https://developer.paypal.com" target="_blank" style="color:var(--accent)">developer.paypal.com</a>
          → Apps &amp; Credentials → Feature „Transaction Search" aktivieren.
        </p>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:12px">
          <input type="checkbox" name="paypal_api_aktiv" value="1" <?= !empty($cfg['paypal_api_aktiv']) ? 'checked' : '' ?>
            style="width:18px;height:18px;accent-color:var(--accent)">
          <span>API-Import im nächtlichen Cron-Job aktivieren</span>
        </label>
        <div class="form-row">
          <div class="fg"><label>Modus</label>
            <select name="paypal_mode">
              <option value="sandbox" <?= ($cfg['paypal_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (Test)</option>
              <option value="live" <?= ($cfg['paypal_mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live (Produktion)</option>
            </select>
          </div>
          <div class="fg"><label>Import-Zeitraum (Tage, max. 31)</label>
            <input type="number" name="paypal_import_tage" min="1" max="31" value="<?= (int) ($cfg['paypal_import_tage'] ?? 3) ?>">
          </div>
          <div class="fg"><label>Import ab Datum (inkl.)</label>
            <input type="date" name="paypal_seit_datum" value="<?= htmlspecialchars($cfg['paypal_seit_datum'] ?? '') ?>">
          </div>
        </div>
        <small style="color:var(--muted);font-size:11px;display:block;margin-bottom:8px">
          „Import ab Datum": Transaktionen vor diesem Datum werden nie importiert – wichtig beim Umstieg
          vom Mail-Parsing, damit bereits erfasste Spenden nicht doppelt angelegt werden. Leer = nur Zeitraum in Tagen.
        </small>
        <div class="fg"><label>Client ID</label>
          <input type="text" name="paypal_client_id" value="<?= htmlspecialchars($cfg['paypal_client_id'] ?? '') ?>">
        </div>
        <div class="fg"><label>Secret</label>
          <input type="password" name="paypal_secret" value="<?= htmlspecialchars($cfg['paypal_secret'] ?? '') ?>">
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="testPaypal(this)" style="margin-top:8px">Verbindung testen</button>
        <div id="paypal-result" style="margin-top:10px;font-size:12px;display:none"></div>
        <small style="color:var(--muted);font-size:11px;display:block;margin-top:8px">
          ⚠️ Vor dem Testen speichern! Der Test nutzt die gespeicherten Zugangsdaten.
        </small>
      </div>

      <!-- Regeln -->
      <div class="form-card">
        <h3>⚙️ Regeln & Cron</h3>
        <div class="form-row">
          <div class="fg">
            <label>Dateinamenformat</label>
            <input type="text" name="fileformat" value="<?= $cfg['fileformat'] ?>" style="width: 385px" ;>
          </div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label>Mindestbetrag (€)</label>
            <input type="number" name="mindestbetrag" step="0.01" value="<?= $cfg['mindestbetrag'] ?>">
            <small style="color:var(--muted);font-size:11px">Unter diesem Betrag → Ordner "Geringbetrag"</small>
          </div>
          <div class="fg">
            <label>Mail-Limit pro Job</label>
            <input type="number" name="mail_limit" min="0" value="<?= $cfg['mail_limit'] ?>">
            <small style="color:var(--muted);font-size:11px">0 = alle Mails; 5 = Testmodus</small>
          </div>
        </div>
        <div class="form-row">
          <div class="fg"><label>Cron-Stunde (0–23)</label><input type="number" name="cron_hour" min="0" max="23"
              value="<?= $cfg['cron_hour'] ?>"></div>
          <div class="fg"><label>Cron-Minute (0–59)</label><input type="number" name="cron_minute" min="0" max="59"
              value="<?= $cfg['cron_minute'] ?>"></div>
        </div>
        <div class="alert alert-warn" style="margin-top:4px">
          ⚠️ Cron-Uhrzeit-Änderungen erfordern <code style="font-size:11px">docker compose restart</code>
          um die Crontab neu einzulesen.
        </div>
      </div>

      <!-- Ausländisch-Scan -->
      <div class="form-card">
        <h3>🌍 Ausländische Spender erkennen</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:10px">Scannt alle Einträge ohne deutsches PLZ (5 Stellen), markiert sie als ausländisch und löscht vorhandene PDFs. Einmalig für bestehende Einträge.</p>
        <button type="button" class="btn btn-outline btn-sm" onclick="auslaendischScan(this)">🔍 Scan starten</button>
        <div id="auslaendisch-result" style="margin-top:10px;font-size:12px;display:none"></div>
      </div>

      <!-- Mail-Body Backfill -->
      <div class="form-card">
        <h3>📧 Original-Mail Body nachladen</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:10px">Scannt Archiv-Ordner einmalig und speichert den Originalinhalt aller importierten Mails in die Datenbank. Einmalig nötig für Mails die vor dem Update importiert wurden.</p>
        <button type="button" class="btn btn-outline btn-sm" onclick="mailBodyBackfill(this)">📥 Backfill starten</button>
        <div id="backfill-result" style="margin-top:10px;font-size:12px;display:none"></div>
      </div>

      <!-- Darstellung -->
      <div class="form-card">
        <h3>🎨 Darstellung</h3>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" id="theme-toggle" onchange="setTheme(this.checked)"
            style="width:18px;height:18px;accent-color:var(--accent)">
          <span>☀️ Hellmodus aktivieren</span>
        </label>
        <small style="color:var(--muted);font-size:11px;display:block;margin-top:6px">
          Wird pro Browser gespeichert – gilt sofort, kein Speichern nötig.
        </small>
      </div>

      <?php if (!empty($cfg['adminer_url'])): ?>
        <div class="form-card">
          <h3>🔬 Datenbank</h3>
          <a href="<?= htmlspecialchars($cfg['adminer_url']) ?>" target="_blank" class="nav-solo" data-tip="Datenbank"
            style="margin-top:4px">
            <span class="ni">🗄️</span><span class="nl" style="color:var(--muted)">Datenbank</span>
          </a>
          <button class="btn btn-danger btn-sm" onclick="resetAlles()">⚠ Reset</button>
        </div>
      <?php endif ?>

    </div>

  </div>
</form>

<?php
$content = ob_get_clean();
$extraJs = <<<'JS'
// ── Hellmodus-Umschalter ──
function setTheme(light) {
  if (light) {
    document.documentElement.setAttribute('data-theme', 'light');
    localStorage.setItem('theme', 'light');
  } else {
    document.documentElement.removeAttribute('data-theme');
    localStorage.removeItem('theme');
  }
}
// Checkbox-Zustand beim Laden setzen
document.getElementById('theme-toggle').checked =
  localStorage.getItem('theme') === 'light';

// gruppe = Suffix, damit mehrere Karten eigene Tabs haben ('' = Begleittext, '-adr' = Adressanfrage)
function switchTab(lang, gruppe = '') {
  const active = 'background:var(--accent);color:#fff';
  const inactive = 'background:var(--surface);color:var(--muted)';
  const ids = ['tab-tr' + gruppe, 'tab-de' + gruppe];
  document.getElementById(ids[0]).style.cssText = lang === 'tr' ? active : inactive;
  document.getElementById(ids[1]).style.cssText = lang === 'de' ? active : inactive;
  document.getElementById('panel-tr' + gruppe).style.display = lang === 'tr' ? '' : 'none';
  document.getElementById('panel-de' + gruppe).style.display = lang === 'de' ? '' : 'none';
  // Padding/Radius zurücksetzen (war inline)
  ids.forEach(id => {
    const b = document.getElementById(id);
    b.style.padding = '7px 18px';
    b.style.border = 'none';
    b.style.borderRadius = '6px 6px 0 0';
    b.style.fontWeight = '700';
    b.style.cursor = 'pointer';
  });
  document.getElementById('tab-' + lang + gruppe).style.marginLeft = lang === 'de' ? '4px' : '0';
}
function testImap() {
  const result = document.getElementById('imap-result');
  result.style.display = 'block';
  result.innerHTML = '<span style="color:var(--muted)">Teste Verbindung…</span>';
  fetch('/api/imap/test', { method: 'POST' })
    .then(r => r.json()).then(d => {
      if (d.ok) {
        result.innerHTML = `<span style="color:var(--green)">✓ Verbindung OK</span><br>
          <span style="color:var(--muted)">Verfügbare Ordner:</span><br>` +
          d.ordner.map(o => `<code style="display:block;font-size:11px;color:var(--accent)">${o}</code>`).join('');
      } else {
        result.innerHTML = `<span style="color:var(--red)">✕ Fehler: ${d.error}</span>`;
      }
    });
}
function testPaypal(btn) {
  const result = document.getElementById('paypal-result');
  btn.disabled = true; btn.textContent = '⏳ Teste…';
  result.style.display = 'block';
  result.innerHTML = '<span style="color:var(--muted)">Verbinde mit PayPal…</span>';
  fetch('/api/paypal/test', { method: 'POST' })
    .then(r => r.json()).then(d => {
      btn.disabled = false; btn.textContent = 'Verbindung testen';
      if (d.ok) {
        result.innerHTML = `<span style="color:var(--green)">✓ Verbindung OK (${d.modus})</span><br>
          <span style="color:var(--muted)">${d.anzahl} Transaktionen in den letzten ${d.tage} Tagen gefunden</span>`;
      } else {
        result.innerHTML = `<span style="color:var(--red)">✕ Fehler: ${d.error}</span>`;
      }
    }).catch(() => {
      btn.disabled = false; btn.textContent = 'Verbindung testen';
      result.innerHTML = '<span style="color:var(--red)">✕ Anfrage fehlgeschlagen</span>';
    });
}
function auslaendischScan(btn) {
  const result = document.getElementById('auslaendisch-result');
  btn.disabled = true; btn.textContent = '⏳ Scannt…';
  result.style.display = 'block';
  result.innerHTML = '<span style="color:var(--muted)">Bitte warten…</span>';
  fetch('/api/auslaendisch-scan', { method: 'POST' })
    .then(r => r.json()).then(d => {
      btn.disabled = false; btn.textContent = '🔍 Scan starten';
      result.innerHTML = d.ok
        ? `<span style="color:var(--green)">✓ ${d.markiert} Einträge als ausländisch markiert, PDFs gelöscht</span>`
        : `<span style="color:var(--red)">✕ Fehler</span>`;
    });
}
function mailBodyBackfill(btn) {
  const result = document.getElementById('backfill-result');
  btn.disabled = true; btn.textContent = '⏳ Scannt Archiv…';
  result.style.display = 'block';
  result.innerHTML = '<span style="color:var(--muted)">Bitte warten – scannt alle Ordner…</span>';
  fetch('/api/mailbody-backfill', { method: 'POST' })
    .then(r => r.json()).then(d => {
      btn.disabled = false; btn.textContent = '📥 Backfill starten';
      if (d.ok) {
        result.innerHTML = `<span style="color:var(--green)">✓ ${d.aktualisiert} von ${d.gesamt ?? d.aktualisiert} Mails aktualisiert</span>`;
      } else {
        result.innerHTML = `<span style="color:var(--red)">✕ Fehler</span>`;
      }
    }).catch(() => {
      btn.disabled = false; btn.textContent = '📥 Backfill starten';
      result.innerHTML = '<span style="color:var(--red)">✕ Timeout – zu viele Mails, bitte erneut versuchen</span>';
    });
}
function userNamenZeileAdd() {
  const div = document.createElement('div');
  div.className = 'form-row user-namen-zeile';
  div.style.cssText = 'margin-bottom:6px';
  div.innerHTML = '<div class="fg"><input type="email" name="un_email[]" placeholder="email@beispiel.de" style="width:100%"></div>'
    + '<div class="fg" style="display:flex;align-items:center;gap:4px"><input type="text" name="un_name[]" placeholder="Anzeigename" style="flex:1"><button type="button" onclick="this.closest(\'.user-namen-zeile\').remove()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0 4px;line-height:1;flex-shrink:0" title="Entfernen">×</button></div>';
  document.getElementById('user-namen-liste').appendChild(div);
  div.querySelector('input').focus();
}
JS;
require __DIR__ . '/layout.php';
