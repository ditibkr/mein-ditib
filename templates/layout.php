<?php
/**
 * Haupt-Layout – wird von allen Seiten per require eingebunden
 * Erwartet: $page (aktive Route), $title, $zaehler (von DB)
 */
use App\DB\Database;
if (!isset($zaehler))
  $zaehler = Database::spendeZaehler();
$cfg = require ROOT . '/config/settings.php';
$cron = sprintf('%02d:%02d', $cfg['cron_hour'], $cfg['cron_minute']);
?>
<!DOCTYPE html>
<html lang="de">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? 'Dashboard') ?> – SpendenPortal</title>
  <script>
    // Theme vor dem CSS-Rendering setzen, verhindert Aufblitzen des Dunkelmodus
    if (localStorage.getItem('theme') === 'light')
      document.documentElement.setAttribute('data-theme', 'light');
  </script>
  <link rel="stylesheet" href="/static/css/portal.css">
</head>

<body>

  <!-- ── SIDEBAR ── -->
  <aside class="sidebar" id="sb">
    <div class="sb-toggle" onclick="toggleSb()" title="Menü ein/ausklappen">
      <span class="arr">◀</span>
    </div>

    <div class="sb-logo">
      <div class="sb-logo-icon"><img src="../static/img/logoDitib.png" style="height: 40px; width: 40px;" /></div>
      <div class="sb-logo-text">
        <h1>Spendenportal</h1>
        <span>DITIB Krefeld</span>
      </div>
    </div>

    <?php if ($cfg['testmodus'] ?? false): ?>
    <div style="background:#b45309;color:#fff;font-size:11px;font-weight:700;text-align:center;
                padding:6px 8px;margin:0 -16px;letter-spacing:.5px;text-transform:uppercase">
      ⚠ Testmodus aktiv
    </div>
    <?php endif ?>

    <nav class="sb-nav">
      <a href="/" class="nav-solo <?= ($page ?? '') === 'dashboard' ? 'active' : '' ?>" data-tip="Dashboard">
        <span class="ni">🏠</span><span class="nl">Dashboard</span>
      </a>

      <div class="sb-label">Spenden</div>

      <div class="nav-group open" id="grp-s">
        <div class="nav-gh" onclick="toggleGrp('grp-s')" data-tip="Spenden">
          <span class="gi">💶</span><span class="gl">Spenden</span><span class="ga">▶</span>
        </div>
        <div class="nav-gc">
          <a href="/liste" class="nav-item <?= ($page ?? '') === 'liste' ? 'active' : '' ?>" data-tip="Spendenliste">
            <span class="ni">📋</span><span class="nl">Spendenliste</span>
            <?php if (($zaehler['neu'] ?? 0) > 0): ?>
              <span class="nb nb-orange"><?= $zaehler['neu'] ?></span>
            <?php endif ?>
          </a>
          <a href="/manuell" class="nav-item <?= ($page ?? '') === 'manuell' ? 'active' : '' ?>"
            data-tip="Manuell erstellen">
            <span class="ni">✏️</span><span class="nl">Manuell erstellen</span>
          </a>
          <a href="/import" class="nav-item <?= ($page ?? '') === 'import' ? 'active' : '' ?>"
            data-tip="Bestehende PDFs importieren">
            <span class="ni">📥</span><span class="nl">PDF-Import</span>
          </a>
          <a href="/paypal" class="nav-item <?= ($page ?? '') === 'paypal' ? 'active' : '' ?>"
            data-tip="PayPal-Transaktionen">
            <span class="ni">💳</span><span class="nl">PayPal-Suche</span>
          </a>
          <a href="/paypal-summen" class="nav-item <?= ($page ?? '') === 'paypal_summen' ? 'active' : '' ?>"
            data-tip="Summen pro Spender">
            <span class="ni">📊</span><span class="nl">PayPal-Summen</span>
          </a>
          <a href="/paypal-anonym" class="nav-item <?= ($page ?? '') === 'paypal_anonym' ? 'active' : '' ?>"
            data-tip="Anonyme Zahlungen prüfen">
            <span class="ni">👤</span><span class="nl">Anonyme Zahlungen</span>
          </a>
          <a href="/versand" class="nav-item <?= ($page ?? '') === 'versand' ? 'active' : '' ?>"
            data-tip="Versand-Freigabe">
            <span class="ni">✉️</span><span class="nl">Versand-Freigabe</span>
            <?php if (($zaehler['freigegeben'] ?? 0) > 0): ?>
              <span class="nb nb-blue"><?= $zaehler['freigegeben'] ?></span>
            <?php endif ?>
          </a>
        </div>
      </div>

      <div class="sb-label">Verein</div>

      <a href="/protokolle" class="nav-solo <?= ($page ?? '') === 'protokolle' ? 'active' : '' ?>"
        data-tip="Sitzungsprotokolle">
        <span class="ni">📝</span><span class="nl">Protokolle</span>
      </a>

      <div class="sb-label">System</div>

      <div class="nav-group" id="grp-sys">
        <div class="nav-gh" onclick="toggleGrp('grp-sys')" data-tip="System">
          <span class="gi">⚙️</span><span class="gl">System</span><span class="ga">▶</span>
        </div>
        <div class="nav-gc">
          <a href="/logs" class="nav-item <?= ($page ?? '') === 'logs' ? 'active' : '' ?>" data-tip="System-Logs">
            <span class="ni">📜</span><span class="nl">System-Logs</span>
          </a>
          <a href="/einstellungen" class="nav-item <?= ($page ?? '') === 'einstellungen' ? 'active' : '' ?>"
            data-tip="Einstellungen">
            <span class="ni">🔧</span><span class="nl">Einstellungen</span>
          </a>
        </div>
      </div>



    </nav>

    <div class="sb-footer">
      <div style="font-size:12px;color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500"
           title="Eingeloggt als: <?= htmlspecialchars(getCurrentUser()) ?>">
        👤 <?= htmlspecialchars(getCurrentUserName()) ?>
      </div>
      <div style="margin-top:7px;font-size:11px">
        Nächster Job: <strong style="color:var(--green)"><?= $cron ?> Uhr</strong>
      </div>
    </div>
  </aside>

  <!-- ── SIDEBAR OVERLAY (Mobile) ── -->
  <div class="sb-overlay" id="sbOverlay" onclick="closeMobileMenu()"></div>

  <!-- ── MOBILE TOPBAR ── -->
  <div class="mobile-topbar">
    <button class="mob-menu-btn" onclick="toggleMobileMenu()" aria-label="Menü öffnen">
      <span></span><span></span><span></span>
    </button>
    <div class="mob-title">SpendenPortal</div>
    <div class="mob-actions">
      <?php if (($zaehler['neu'] ?? 0) > 0): ?>
        <a href="/liste?status=neu" class="nb nb-orange" style="padding:5px 9px;border-radius:20px;text-decoration:none">
          <?= $zaehler['neu'] ?> neu
        </a>
      <?php endif ?>
    </div>
  </div>

  <!-- ── MAIN ── -->
  <main class="main" id="mn">

    <?php if ($cfg['testmodus'] ?? false): ?>
    <div style="background:#fef3c7;border:2px solid #b45309;border-radius:8px;
                color:#92400e;font-weight:700;font-size:13px;
                padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
      <span style="font-size:18px">⚠</span>
      <span>TESTMODUS AKTIV – Mails werden an
        <strong><?= htmlspecialchars($cfg['test_email'] ?? '?') ?></strong>
        gesendet, nicht an echte Empfänger.
      </span>
    </div>
    <?php endif ?>

    <?php if (!empty($_SESSION['flash'])): ?>
      <?php $f = $_SESSION['flash'];
      unset($_SESSION['flash']); ?>
      <div class="flash flash-<?= $f['type'] ?>"><?= htmlspecialchars($f['msg']) ?></div>
    <?php endif ?>

    <?= $content ?? '' ?>

  </main>

  <!-- ── KOMMENTAR-PANEL ── -->
  <div class="cp-overlay" id="cpOverlay" onclick="closeComment()"></div>
  <div class="cp" id="cpPanel">
    <div class="cp-head">
      <div>
        <h3>💬 Kommentare</h3>
        <p id="cpSub"></p>
      </div>
      <button class="icon-btn" onclick="closeComment()">✕</button>
    </div>
    <div class="cp-body" id="cpBody">
      <div class="empty-state">
        <div class="es-icon">💬</div>
        <p>Noch keine Kommentare.</p>
      </div>
    </div>
    <div class="cp-foot">
      <textarea class="cp-textarea" id="cpInput" rows="3" placeholder="Kommentar hinzufügen…"></textarea>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary btn-sm" style="flex:1" onclick="addComment()">Hinzufügen</button>
        <button class="btn btn-outline btn-sm" onclick="closeComment()">Schließen</button>
      </div>
    </div>
  </div>

  <!-- ── NOTIFICATION ── -->
  <div id="notif" style="display:none" class="notif ok">
    <span id="notifIcon">✓</span><span id="notifTxt"></span>
  </div>

  <script>
    const USER_NAMEN = <?= json_encode($cfg['user_namen'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    function autorName(email) {
      return email ? (USER_NAMEN[email] || email) : 'Unbekannt';
    }
    // ── Mobile Menü ──
    function toggleMobileMenu() {
      const sb = document.getElementById('sb');
      const ov = document.getElementById('sbOverlay');
      const open = sb.classList.toggle('mobile-open');
      ov.classList.toggle('open', open);
      document.body.style.overflow = open ? 'hidden' : '';
    }
    function closeMobileMenu() {
      document.getElementById('sb').classList.remove('mobile-open');
      document.getElementById('sbOverlay').classList.remove('open');
      document.body.style.overflow = '';
    }
    // Schließen bei Navklick auf Mobile
    document.querySelectorAll('.nav-item, .nav-solo').forEach(el => {
      el.addEventListener('click', () => {
        if (window.innerWidth <= 768) closeMobileMenu();
      });
    });

    // ── Sidebar Desktop ──
    const SB_KEY = 'sb_collapsed';
    const sb = document.getElementById('sb');
    const mn = document.getElementById('mn');
    if (localStorage.getItem(SB_KEY) === '1') { sb.classList.add('collapsed'); mn.classList.add('collapsed'); }

    function toggleSb() {
      sb.classList.toggle('collapsed');
      mn.classList.toggle('collapsed');
      localStorage.setItem(SB_KEY, sb.classList.contains('collapsed') ? '1' : '0');
    }
    function toggleGrp(id) {
      if (!sb.classList.contains('collapsed'))
        document.getElementById(id).classList.toggle('open');
    }

    // ── Notification ──
    let ntTimer;
    function showNotif(msg, ok = true) {
      clearTimeout(ntTimer);
      const n = document.getElementById('notif');
      n.className = 'notif ' + (ok ? 'ok' : 'err');
      document.getElementById('notifIcon').textContent = ok ? '✓' : '✕';
      document.getElementById('notifTxt').textContent = msg;
      n.style.display = 'flex';
      ntTimer = setTimeout(() => n.style.display = 'none', 3500);
    }

    // ── Kommentar-Panel ──
    let cpSid = null;
    function openComment(sid, label) {
      cpSid = sid;
      document.getElementById('cpSub').textContent = label;
      document.getElementById('cpBody').innerHTML = '<div style="color:var(--muted);font-size:12px;padding:20px">Lade…</div>';
      fetch(`/api/spende/${sid}/kommentar`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ text: '' }) })
        .then(r => r.json()).then(renderComments);
      document.getElementById('cpPanel').classList.add('open');
      document.getElementById('cpOverlay').classList.add('open');
    }
    function closeComment() {
      document.getElementById('cpPanel').classList.remove('open');
      document.getElementById('cpOverlay').classList.remove('open');
    }
    function renderComments(list) {
      const b = document.getElementById('cpBody');
      if (!list || list.length === 0) {
        b.innerHTML = '<div class="empty-state"><div class="es-icon">💬</div><p>Noch keine Kommentare.</p></div>';
        return;
      }
      b.innerHTML = list.map(k => `
    <div class="comment-bubble ${k.typ === 'system' ? 'sys' : 'usr'}">
      <div class="cb-meta">
        <span>${k.typ === 'system' ? '🤖 System' : '👤 ' + autorName(k.autor)}</span>
        <span>${k.erstellt_am}</span>
      </div>
      <div class="cb-text">${k.text}</div>
    </div>`).join('');
      b.scrollTop = b.scrollHeight;
    }
    function addComment() {
      const t = document.getElementById('cpInput').value.trim();
      if (!t || !cpSid) return;
      fetch(`/api/spende/${cpSid}/kommentar`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ text: t }) })
        .then(r => r.json()).then(list => { renderComments(list); document.getElementById('cpInput').value = ''; showNotif('Kommentar gespeichert'); });
    }

    // ── Freigeben ──
    function freigeben(sid) {
      fetch(`/api/spende/${sid}/freigeben`, { method: 'POST' })
        .then(r => r.json()).then(d => { if (d.ok) { showNotif('Freigegeben ✓'); setTimeout(() => location.reload(), 1000); } });
    }

    // ── Versenden ──
    function versenden(sid, btn, sprache) {
      const lang = sprache || document.getElementById('mailSprache')?.value || 'tr';
      btn.disabled = true; btn.textContent = 'Sende…';
      fetch(`/api/spende/${sid}/versenden`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({sprache: lang})
      }).then(r => r.json()).then(d => {
          if (d.ok) {
            showNotif('Mail versendet ✓ (' + (lang === 'de' ? '🇩🇪 Deutsch' : '🇹🇷 Türkisch') + ')');
            setTimeout(() => window.location = '/liste', 1200);
          } else if (d.duplikat) {
            showNotif('⚠ ' + (d.info || 'Bereits versendet – kein erneuter Versand'), false);
            setTimeout(() => location.reload(), 2000);
          } else {
            showNotif(d.error || 'Fehler beim Versand', false);
            btn.disabled = false; btn.textContent = '✉ Mail versenden';
          }
        });
    }

    // ── Löschen ──
    function postversand(sid, btn) {
      if (!confirm('Bescheinigung als per Post versendet markieren?')) return;
      btn.disabled = true; btn.textContent = '⏳…';
      fetch(`/api/spende/${sid}/postversand`, { method: 'POST' })
        .then(r => r.json()).then(d => {
          if (d.ok) { showNotif('✓ Als Postversand markiert'); setTimeout(() => location.reload(), 1000); }
          else { showNotif(d.error || 'Fehler', false); btn.disabled = false; btn.textContent = '📬 Postversand'; }
        });
    }
    // ── Adressanfrage – nur auf Klick, nie automatisch ──
    // In der Liste gibt es keine Sprachauswahl, dort fällt sie auf Türkisch zurück.
    function adresseAnfragen(sid, btn) {
      const lang = document.getElementById('adrSprache')?.value || 'tr';
      const txt  = lang === 'de' ? '🇩🇪 Deutsch' : '🇹🇷 Türkisch';
      if (!confirm(`Adressanfrage (${txt}) an den Spender senden?`)) return;
      const alt = btn.textContent;
      btn.disabled = true; btn.textContent = 'Sende…';
      fetch(`/api/spende/${sid}/adresse-anfrage`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sprache: lang })
      }).then(r => r.json()).then(d => {
        if (d.ok) { showNotif('✓ Adressanfrage versendet (' + txt + ')'); setTimeout(() => location.reload(), 1200); }
        else { showNotif(d.error || 'Fehler bei der Adressanfrage', false); btn.disabled = false; btn.textContent = alt; }
      });
    }

    function erledigen(sid, btn) {
      if (!confirm('Vorgang als „Erledigt" markieren?')) return;
      btn.disabled = true; btn.textContent = '⏳';
      fetch(`/api/spende/${sid}/erledigt`, { method: 'POST' })
        .then(r => r.json()).then(d => {
          if (d.ok) { showNotif('✓ Erledigt markiert'); setTimeout(() => location.reload(), 800); }
          else { showNotif(d.error || 'Fehler', false); btn.disabled = false; btn.textContent = '✓'; }
        });
    }
    function loeschen(sid, name, redirect) {
      if (!confirm(`Eintrag "${name}" wirklich löschen?\n\nDas zugehörige PDF wird ebenfalls gelöscht.`)) return;
      fetch(`/api/spende/${sid}/loeschen`, { method: 'POST' })
        .then(r => r.json()).then(d => {
          if (d.ok) {
            showNotif(`"${name}" gelöscht`);
            if (redirect) { setTimeout(() => window.location = '/liste', 1000); }
            else { setTimeout(() => location.reload(), 800); }
          }
        });
    }

    // ── Reset (Produktions-Reset) ──
    function resetAlles() {
      const eingabe = prompt(
        '⚠ ACHTUNG: Alle Einträge, Logs, PDFs und Mail-IDs werden unwiderruflich gelöscht!\n\n' +
        'Zum Bestätigen "RESET" eingeben:'
      );
      if (eingabe !== 'RESET') { alert('Abgebrochen.'); return; }
      fetch('/api/reset', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bestaetigung: 'RESET_BESTAETIGT' })
      }).then(r => r.json()).then(d => {
        if (d.ok) { showNotif('Reset durchgeführt – Seite wird neu geladen'); setTimeout(() => location.reload(), 1500); }
        else showNotif(d.error || 'Fehler', false);
      });
    }

    // ── Manueller Job: INBOX → spenden-eingang → PDF + Eintrag → Archiv ──
    function jobManuell(btn) {
      btn.disabled = true; btn.textContent = '⏳ Läuft…';
      fetch('/api/job/manuell', { method: 'POST' })
        .then(r => r.json())
        .then(d => {
          btn.disabled = false; btn.textContent = '▶ Job starten';
          if (d.ok) {
            const teile = [];
            if (d.verschoben > 0) teile.push(`${d.verschoben} verschoben`);
            if (d.api       > 0) teile.push(`${d.api} per PayPal-API`);
            if (d.erstellt  > 0) teile.push(`${d.erstellt} PDFs erstellt`);
            if (d.fehler    > 0) teile.push(`${d.fehler} Fehler`);
            showNotif(teile.length ? '✓ ' + teile.join(' · ') : 'Keine neuen Spenden', d.fehler === 0);
            if (d.erstellt > 0) setTimeout(() => location.reload(), 1500);
          } else {
            showNotif(d.error || 'Fehler', false);
          }
        })
        .catch(() => { btn.disabled = false; btn.textContent = '▶ Job starten'; showNotif('Verbindungsfehler', false); });
    }

    // Sammelspende
    function berechnesammel() {
      const von = document.getElementById('sammel_von').value;
      const bis = document.getElementById('sammel_bis').value;
      const name = document.querySelector('[name=vorname]').value + ' ' +
        document.querySelector('[name=nachname]').value;

      fetch(`/api/sammel-vorschau?von=${von}&bis=${bis}&name=${encodeURIComponent(name)}`)
        .then(r => r.json())
        .then(d => {
          document.getElementById('sammel_vorschau').innerHTML =
            `Gefundene Beträge: ${d.anzahl} | Gesamt: ${d.gesamt}€`;
          document.querySelector('[name=betrag]').value = d.gesamt;
          document.querySelector('[name=zeitraum_von]').value = von;
          document.querySelector('[name=zeitraum_bis]').value = bis;
        });
    }
    function sucheSpender(q) {
      if (q.length < 2) { document.getElementById('sammel_vorschlaege').innerHTML = ''; return; }
      fetch('/api/spender-suche?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(d => {
          const box = document.getElementById('sammel_vorschlaege');
          box.innerHTML = d.map(s =>
            `<div class="vorschlag" onclick="waehleSpender(${JSON.stringify(s)})"
                  style="padding:6px 10px;cursor:pointer;background:var(--surface);
                         border-radius:4px;margin-bottom:2px">
                  ${s.vorname} ${s.nachname}
                </div>`
          ).join('');
        });
    }

    function waehleSpender(s) {
      document.getElementById('sammel_suche').value = s.vorname + ' ' + s.nachname;
      document.querySelector('[name=vorname]').value = s.vorname;
      document.querySelector('[name=nachname]').value = s.nachname;
      document.querySelector('[name=strasse]').value = s.strasse;
      document.querySelector('[name=plz]').value = s.plz;
      document.querySelector('[name=ort]').value = s.ort;
      document.querySelector('[name=email]').value = s.email;
      document.getElementById('sammel_vorschlaege').innerHTML = '';
      // Dateiname aktualisieren
      aktualisiereDateiname();
    }

    function ladeSpenden() {
      const von = document.getElementById('s_von').value;
      const bis = document.getElementById('s_bis').value;
      const name = document.getElementById('sammel_suche').value;
      if (!name || !von || !bis) { alert('Person und Zeitraum auswählen'); return; }

      fetch(`/api/sammel-vorschau?von=${von}&bis=${bis}&name=${encodeURIComponent(name)}`)
        .then(r => r.json())
        .then(d => {
          const box = document.getElementById('sammel_liste');
          if (d.spenden.length === 0) {
            box.innerHTML = '<p style="color:var(--muted)">Keine Spenden gefunden</p>';
            return;
          }

          let html = '<table class="tbl"><thead><tr>'
            + '<th>Datum</th><th>Betrag</th><th>Art</th><th>Status</th><th>Hinweis</th>'
            + '</tr></thead><tbody>';

          let gesamt = 0;
          let ids = [];

          d.spenden.forEach(s => {
            const hatBescheinigung = s.status === 'versendet';
            const farbe = hatBescheinigung ? 'color:var(--orange)' : '';
            const hinweis = hatBescheinigung
              ? '⚠ Bereits bescheinigt'
              : '✓ Wird eingeschlossen';
            gesamt += parseFloat(s.betrag);
            ids.push(s.id);
            html += `<tr style="${farbe}">
                    <td>${s.datum}</td>
                    <td>${parseFloat(s.betrag).toFixed(2).replace('.', ',')} €</td>
                    <td>${s.art}</td>
                    <td>${s.status}</td>
                    <td>${hinweis}</td>
                </tr>`;
          });

          html += `</tbody></table>`;
          html += `<div style="margin-top:8px;font-weight:bold">
                Gesamt: ${gesamt.toFixed(2).replace('.', ',')} €
                aus ${d.spenden.length} Spenden
            </div>`;

          if (d.bereits_bescheinigt > 0) {
            html += `<div style="color:var(--orange);margin-top:6px">
                    ⚠ ${d.bereits_bescheinigt} Spende(n) haben bereits eine Einzelbescheinigung.
                    Diese werden trotzdem in die Sammelbescheinigung aufgenommen.
                </div>`;
          }

          box.innerHTML = html;
          document.querySelector('[name=sammel_ids]').value = JSON.stringify(ids);
          document.getElementById('sammel_betrag_hidden').value = gesamt.toFixed(2);
        });
    }

    /*---

    ## 4. Logs gezielt prüfen nach Test-Job

    Nach dem Test-Job prüfst du in der Logs-Seite nach diesen Einträgen der Reihe nach:
    ```
    ✓ INFO  → Kopier-Job: X Mails gefunden
    ✓ OK    → Kopiert nach 'INBOX.Spenden-Eingang': ...
    ✓ INFO  → IMAP Login OK
    ✓ INFO  → Verarbeite X Mails
    ✓ OK    → Parser: Vorname Nachname | Betrag | Datum
    ✓ OK    → PDF erstellt: ...
    ✓ WARN  → Testmodus: Mail geht an test@mail.de statt spender@mail.de
    ✓ OK    → Job abgeschlossen*/
  </script>
  <?php if (!empty($extraJs)): ?>
  <script><?= $extraJs ?></script>
  <?php endif ?>
</body>

</html>