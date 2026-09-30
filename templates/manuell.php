<?php
$title = 'Manuell erstellen';
$page = 'manuell';
ob_start();
?>

<div class="page-header">
  <div>
    <h2>Manuell erstellen</h2>
    <p>Bescheinigung ohne Mail-Eingang anlegen</p>
  </div>
</div>

<form method="post" action="/manuell" id="mForm">
  <div class="form-grid">

    <!-- ── LINKE SPALTE ── -->
    <div>

      <!-- Art der Zuwendung -->
      <div class="form-card">
        <h3>📋 Art der Zuwendung</h3>
        <div class="type-toggle">
          <div class="type-opt sel-geld" id="opt-geld" onclick="selType('geld')">
            <div class="t-icon">💶</div>
            <div class="t-name">Geldspende</div>
            <div class="t-desc">Einmalig</div>
          </div>
          <div class="type-opt" id="opt-mitglied" onclick="selType('mitglied')">
            <div class="t-icon">🏷</div>
            <div class="t-name">Mitgliedsbeitrag</div>
            <div class="t-desc">Laufend</div>
          </div>
          <div class="type-opt" id="opt-sammel" onclick="selType('sammel')">
            <div class="t-icon">📦</div>
            <div class="t-name">Sammelbescheinigung</div>
            <div class="t-desc">Mehrere Beträge</div>
          </div>
        </div>
        <input type="hidden" name="art" id="art-val" value="geld">

        <!-- Sammel-Extra -->
        <div id="sammel-extra" style="display:none">
          <div class="alert alert-warn" style="margin-bottom:12px">
            ⚠️ Geldspenden und Mitgliedsbeiträge nicht mischen!<br /> (steuerrechtlich unzulässig)
          </div>
          <div class="fg">

            <div id="sammel_vorschau"></div>
            <label>Art der Positionen</label>
            <select name="sammel_art">
              <option value="geld">Geldspenden</option>
              <option value="mitglied">Mitgliedsbeiträge</option>
            </select>
          </div>
          <div id="sammel-rows">
            <div class="sammel-row">
              <div class="fg" style="margin:0"><label>Betrag (€)</label><input type="number" name="sammel_betrag[]"
                  step="0.01" placeholder="50,00" oninput="updateSammel()"></div>
              <div class="fg" style="margin:0"><label>Datum</label><input type="date" name="sammel_datum[]"></div>
              <button type="button" class="sammel-rm"
                onclick="this.closest('.sammel-row').remove();updateSammel()">✕</button>
            </div>
          </div>
          <button type="button" onclick="addRow()" class="btn btn-outline btn-sm" style="width:100%;margin:8px 0">+
            Zeile hinzufügen</button>
          <div
            style="padding:10px 13px;background:rgba(247,201,79,.06);border:1px solid rgba(247,201,79,.2);border-radius:8px;font-size:13px">
            Gesamt: <strong id="sammel-total" style="color:var(--accent2)">0,00 €</strong>
            <span style="color:var(--muted)"> · <span id="sammel-count">1</span> Positionen</span>
          </div>
        </div>
      </div>

      <!-- Spender -->
      <div class="form-card">
        <h3>👤 Spender-Daten</h3>

        <!-- Smart-Paste: kompletten Adressblock einfügen -->
        <div class="fg" style="margin-bottom:14px">
          <label>📋 Smart-Paste – Adressblock einfügen</label>
          <textarea id="smart-paste" rows="3"
            placeholder="Max Mustermann&#10;Musterstraße 12&#10;47805 Krefeld"></textarea>
          <div id="sp-result" style="font-size:11px;margin-top:4px"></div>
        </div>

        <div class="form-row">
          <div class="fg"><label>Vorname *</label><input type="text" name="vorname" id="fn" placeholder="Max"
              oninput="updateFn();acSuche(this.value)" autocomplete="off" required></div>
          <div class="fg"><label>Nachname *</label><input type="text" name="nachname" id="nn" placeholder="Mustermann"
              oninput="updateFn();acSuche(this.value)" autocomplete="off" required></div>
        </div>
        <div id="ac-box"></div>
        <div class="fg"><label>Straße & Hausnummer</label><input type="text" name="strasse"
            placeholder="Musterstraße 12"></div>
        <div class="form-row">
          <div class="fg"><label>PLZ</label><input type="text" name="plz" placeholder="47805"
              oninput="plzLookup(this.value)" autocomplete="off"></div>
          <div class="fg"><label>Ort <span id="ort-hint" style="color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0"></span></label>
            <input type="text" name="ort" placeholder="Krefeld" oninput="ortAuto=false"></div>
        </div>
        <div class="fg"><label>E-Mail (für automatischen Versand)</label><input type="email" name="email"
            placeholder="max@example.de"></div>
      </div>
      <div id="sammel-bereich" style="display:none">
        <div class="form-grid">
          <div class="fg">
            <label>Person suchen</label>
            <input type="text" id="sammel_suche" placeholder="Name eingeben..." oninput="sucheSpender(this.value)">
            <div id="sammel_vorschlaege" style="margin-top:4px"></div>
          </div>
          <div class="fg">
            <label>Zeitraum von</label>
            <input type="date" name="zeitraum_von" id="s_von">
          </div>
          <div class="fg">
            <label>Zeitraum bis</label>
            <input type="date" name="zeitraum_bis" id="s_bis">
          </div>
        </div>

        <button type="button" class="btn btn-secondary" onclick="ladeSpenden()">
          Spenden laden
        </button>

        <div id="sammel_liste" style="margin-top:12px"></div>
        <input type="hidden" name="sammel_ids">
        <input type="hidden" name="betrag" id="sammel_betrag_hidden">
      </div>




    </div><!-- /linke Spalte -->

    <!-- ── RECHTE SPALTE ── -->
    <!-- Betrag & Datum (wird bei Sammel ausgeblendet) -->
    <div>
      <div class="form-card" id="single-betrag">
        <h3>💰 Betrag & Datum</h3>
        <div class="form-row">
          <div class="fg"><label>Betrag (€) *</label><input type="number" name="betrag" step="0.01" min="0.01"
              placeholder="50,00" required></div>
          <div class="fg"><label>Datum *</label><input type="date" name="datum" id="fd" value="<?= date('Y-m-d') ?>"
              oninput="updateFn()" required></div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label>Kalenderjahr (Bescheinigung)</label>
            <input type="number" name="kalenderjahr" min="2000" max="2099" value="<?= date('Y') ?>"
              placeholder="<?= date('Y') ?>">
          </div>
          <div class="fg">
            <label>Zahlungsweg</label>
            <select name="zahlungsweg">
              <option>Überweisung</option>
              <option>Bar</option>
              <option>PayPal</option>
              <option>Lastschrift</option>
            </select>
          </div>
        </div>
      </div>
      <!-- Kommentar & Absenden -->
      <div class="form-card">
        <h3>💬 Interner Kommentar</h3>
        <div class="fg"><textarea name="kommentar" rows="2" placeholder="Optionale interne Notiz…"></textarea></div>

        <div class="filename-box">
          <div class="fl">Dateiname</div>
          <code id="fn-preview">ddmmyyyy_spendenbescheinigung_vorname_nachname.pdf</code>
        </div>

        <div class="protect-badge">
          🔒 PDF wird automatisch mit AES-256 schreibgeschützt
        </div>

        <div style="display:flex;gap:10px;margin-top:14px">
          <button type="submit" class="btn btn-primary" style="flex:1">💾 PDF erstellen</button>
          <a href="/liste" class="btn btn-outline">Abbrechen</a>
        </div>
      </div>


    </div><!-- /form-grid -->
</form>

<?php
$content = ob_get_clean();
$extraJs = <<<'JS'
// ── Vorbefüllen per URL-Parameter (z. B. aus der PayPal-Suche) ──
(function () {
  const p = new URLSearchParams(location.search);
  if (![...p.keys()].length) return;
  // 'betrag' existiert doppelt (verstecktes Sammel-Feld zuerst im DOM) → gezielt das sichtbare Einzelfeld nehmen
  const f = n => n === 'betrag'
    ? document.querySelector('#single-betrag [name="betrag"]')
    : document.querySelector(`[name="${n}"]`);
  ['vorname', 'nachname', 'email', 'strasse', 'plz', 'ort', 'betrag'].forEach(n => {
    if (p.get(n) && f(n)) f(n).value = p.get(n);
  });
  // Datum (ISO, z. B. aus der PayPal-Einzeltransaktion) ins Datumsfeld übernehmen
  if (p.get('datum')) {
    const fd = document.getElementById('fd');
    if (fd) { fd.value = p.get('datum'); fd.dispatchEvent(new Event('change')); }
  }
  if (typeof updateFn === 'function') updateFn();
})();

// ── Smart-Paste: Adressblock in Einzelfelder zerlegen ──
const spFeld = document.getElementById('smart-paste');
spFeld.addEventListener('paste', () => setTimeout(() => parseAdresse(spFeld.value), 0));

function parseAdresse(raw) {
  const f = n => document.querySelector(`[name="${n}"]`);
  const gefunden = [];
  let text = raw.trim();
  if (!text) return;

  // E-Mail extrahieren
  const mail = text.match(/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/);
  if (mail) {
    f('email').value = mail[0];
    text = text.replace(mail[0], ' ');
    gefunden.push('E-Mail');
  }

  // In Zeilen zerlegen (Zeilenumbruch/Semikolon, sonst Komma als Trenner)
  let lines = text.split(/[\r\n;]+/).map(l => l.trim().replace(/[,;]\s*$/, '')).filter(Boolean);
  if (lines.length === 1 && lines[0].includes(','))
    lines = lines[0].split(',').map(l => l.trim()).filter(Boolean);

  // PLZ + Ort (5-stellig, optional "D-" davor)
  for (let i = 0; i < lines.length; i++) {
    const m = lines[i].match(/(?:^|\s)(?:D-)?(\d{5})\s+([A-Za-zÄÖÜäöüß()\/.\- ]+)$/);
    if (m) {
      f('plz').value = m[1];
      f('ort').value = m[2].trim();
      gefunden.push('PLZ/Ort');
      lines[i] = lines[i].slice(0, m.index).trim();  // Rest der Zeile behalten (Einzeiler)
      if (!lines[i]) lines.splice(i, 1);
      break;
    }
  }

  // Straße + Hausnummer als eigene Zeile (endet mit Nummer, ggf. 12a oder 12-14)
  for (let i = 0; i < lines.length; i++) {
    const m = lines[i].match(/^([A-Za-zÄÖÜäöüß.\- ]{3,}?)\s+(\d+\s*[a-zA-Z]?(?:\s*[-–\/]\s*\d+\s*[a-zA-Z]?)?)$/);
    if (m && lines.length > 1) {
      f('strasse').value = m[1].trim() + ' ' + m[2].replace(/\s+/g, '');
      gefunden.push('Straße');
      lines.splice(i, 1);
      break;
    }
  }

  // Name: erste verbleibende Zeile ohne Ziffern (letztes Wort = Nachname)
  const nameZeile = lines.find(l => !/\d/.test(l));
  if (nameZeile) {
    const teile = nameZeile.replace(/^(herrn?|frau|familie|hr\.|fr\.)\s+/i, '').split(/\s+/);
    f('nachname').value = teile.pop();          // letztes Wort = Nachname
    f('vorname').value = teile.join(' ');       // Rest = Vorname (kann leer sein)
    gefunden.push('Name');
  } else if (lines[0]) {
    // Einzeiler ohne Trenner: "Vorname Nachname Musterstraße 12"
    const m = lines[0].match(/^(\S+)\s+(\S+)\s+(.+?\d+\s*[a-zA-Z]?)$/);
    if (m) {
      f('vorname').value = m[1];
      f('nachname').value = m[2];
      f('strasse').value = m[3];
      gefunden.push('Name', 'Straße');
    }
  }

  updateFn();
  document.getElementById('sp-result').innerHTML = gefunden.length
    ? `<span style="color:var(--green)">✓ Erkannt: ${gefunden.join(', ')} – bitte Felder prüfen</span>`
    : `<span style="color:var(--red)">✕ Keine Adresse erkannt – bitte manuell eintragen</span>`;
}

// ── PLZ → Ort automatisch nachschlagen (OpenPLZ API, kostenlos, ohne Key) ──
let plzTimer, ortAuto = false;
function plzLookup(plz) {
  clearTimeout(plzTimer);
  plz = plz.trim();
  if (!/^\d{5}$/.test(plz)) return;
  plzTimer = setTimeout(() => {
    fetch('https://openplzapi.org/de/Localities?postalCode=' + plz)
      .then(r => r.ok ? r.json() : [])
      .then(d => {
        const ort = document.querySelector('[name="ort"]');
        const hint = document.getElementById('ort-hint');
        if (!d.length) { hint.textContent = ''; return; }
        // Nur befüllen, wenn Feld leer oder zuletzt automatisch gesetzt
        if (!ort.value || ortAuto) {
          ort.value = d[0].name;
          ortAuto = true;
          hint.textContent = '· automatisch ergänzt';
        }
      })
      .catch(() => {});  // offline / API nicht erreichbar → still ignorieren
  }, 300);
}

// ── Spender-Vervollständigung aus der eigenen Datenbank ──
let acDaten = [], acTimer;
function acSuche(q) {
  clearTimeout(acTimer);
  const box = document.getElementById('ac-box');
  q = q.trim();
  if (q.length < 2) { box.innerHTML = ''; return; }
  acTimer = setTimeout(() => {
    fetch('/api/spender-suche?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(d => {
        acDaten = d.slice(0, 8);
        box.innerHTML = acDaten.map((s, i) => `
          <div class="ac-item" onclick="acWaehle(${i})">
            👤 <strong>${s.vorname} ${s.nachname}</strong>
            <small>${[s.strasse, [s.plz, s.ort].filter(Boolean).join(' ')].filter(Boolean).join(', ')}${s.email ? ' · ' + s.email : ''}</small>
          </div>`).join('');
      });
  }, 250);
}
function acWaehle(i) {
  const s = acDaten[i];
  if (!s) return;
  const f = n => document.querySelector(`[name="${n}"]`);
  f('vorname').value = s.vorname;
  f('nachname').value = s.nachname;
  f('strasse').value = s.strasse;
  f('plz').value = s.plz;
  f('ort').value = s.ort;
  f('email').value = s.email;
  document.getElementById('ac-box').innerHTML = '';
  updateFn();
}
// Vorschläge schließen bei Klick außerhalb
document.addEventListener('click', e => {
  if (!e.target.closest('#ac-box') && e.target.id !== 'fn' && e.target.id !== 'nn')
    document.getElementById('ac-box').innerHTML = '';
});

function selType(t) {
  ['geld','mitglied','sammel'].forEach(x => {
    const el = document.getElementById('opt-' + x);
    el.className = 'type-opt' + (x === t ? ' sel-' + t : '');
  });
  document.getElementById('art-val').value = t;
  //document.getElementById('mitglied-extra').style.display  = t === 'mitglied' ? 'block' : 'none';
  document.getElementById('sammel-bereich').style.display    = t === 'sammel'   ? 'block' : 'none';
  document.getElementById('single-betrag').style.display  = t === 'sammel'   ? 'none'  : 'block';
  const bField = document.querySelector('input[name="betrag"]');
  if (bField) bField.required = (t !== 'sammel');
}
function updateFn() {
  const v = document.getElementById('fn')?.value || 'vorname';
  const n = document.getElementById('nn')?.value || 'nachname';
  const d = document.getElementById('fd')?.value || '';
  let ds = 'ddmmyyyy';
  if (d) { const [y,m,dd] = d.split('-'); ds = dd+m+y; }
  const clean = s => s.toLowerCase()
    .replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss')
    .replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,'');
  document.getElementById('fn-preview').textContent =
    `${ds}_spendenbescheinigung_${clean(v)}_${clean(n)}.pdf`;
}
function addRow() {
  const row = document.createElement('div');
  row.className = 'sammel-row';
  row.innerHTML = `
    <div class="fg" style="margin:0"><label>Betrag (€)</label>
      <input type="number" name="sammel_betrag[]" step="0.01" placeholder="50,00" oninput="updateSammel()"></div>
    <div class="fg" style="margin:0"><label>Datum</label>
      <input type="date" name="sammel_datum[]"></div>
    <button type="button" class="sammel-rm" onclick="this.closest('.sammel-row').remove();updateSammel()">✕</button>`;
  document.getElementById('sammel-rows').appendChild(row);
}
function updateSammel() {
  const vals = [...document.querySelectorAll('input[name="sammel_betrag[]"]')]
    .map(i => parseFloat(i.value) || 0);
  const sum = vals.reduce((a,b) => a+b, 0);
  document.getElementById('sammel-total').textContent = sum.toFixed(2).replace('.',',') + ' €';
  document.getElementById('sammel-count').textContent = vals.length;
}
JS;
require __DIR__ . '/layout.php';
