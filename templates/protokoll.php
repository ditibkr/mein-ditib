<?php
/**
 * Live-Editor für ein Sitzungsprotokoll
 * Erwartet: $protokoll (Zeile aus `protokolle`), $personenVorlagen, $bausteineVorlagen
 */
$title = 'Protokoll ' . $protokoll['datum'];
$page = 'protokolle';
ob_start();

$json = fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="page-header">
  <div>
    <h2>📝 Protokoll zur Vorstandssitzung</h2>
    <p>DITIB Türkisch-Islamische Gemeinde zu Krefeld e.V. · <a href="/protokolle">← zur Übersicht</a></p>
  </div>
  <div class="page-header-actions">
    <a class="btn btn-outline btn-sm" href="/protokoll/<?= (int) $protokoll['id'] ?>/pdf">📄 Als PDF speichern</a>
  </div>
</div>

<!-- ── Sitzungsdaten ── -->
<div class="card pk-card">
  <h3 class="pk-h">📅 Sitzungsdaten</h3>
  <div class="pk-grid3">
    <div>
      <label class="pk-fl">Datum</label>
      <input type="date" id="pk-datum">
    </div>
    <div>
      <label class="pk-fl">Beginn</label>
      <input type="time" id="pk-beginn">
    </div>
    <div>
      <label class="pk-fl">Ende</label>
      <input type="time" id="pk-ende">
    </div>
  </div>
</div>

<!-- ── Teilnehmer ── -->
<div class="card pk-card">
  <h3 class="pk-h">👥 Anwesende <span class="pk-count" id="pk-tn-count"></span>
    <button class="pk-h2-btn" id="tn-mng-btn" onclick="toggleMng('tn')">⚙ Vorlagen verwalten</button>
  </h3>
  <div class="pk-chips" id="pk-chips"></div>
  <div class="pk-npform" id="np-form">
    <input type="text" id="np-name" placeholder="Name, z. B. Temel Fidan">
    <input type="text" id="np-role" placeholder="Funktion (optional)" style="max-width:200px">
    <label class="pk-cb"><input type="checkbox" id="np-save" checked> als Vorlage merken</label>
    <button class="btn btn-primary btn-sm" onclick="addPerson()">Hinzufügen</button>
  </div>
  <div class="pk-mng" id="tn-mng">
    <p class="pk-hint">Vorlagen gelten für alle künftigen Protokolle. „Standard“ = bei neuem Protokoll automatisch angehakt.</p>
    <div id="tn-mng-list"></div>
    <button class="btn btn-outline btn-sm" onclick="mngAddPerson()">＋ Neue Vorlage</button>
  </div>
</div>

<!-- ── Tagesordnung ── -->
<div class="card pk-card">
  <h3 class="pk-h">📋 Tagesordnung
    <button class="pk-h2-btn" id="bs-mng-btn" onclick="toggleMng('bs')">⚙ Vorlagen verwalten</button>
  </h3>
  <div class="pk-bausteine" id="pk-bausteine">
    <span class="pk-bs-label">Textbausteine – Klick fügt den TOP unten an:</span>
  </div>
  <div class="pk-mng" id="bs-mng">
    <p class="pk-hint">TOP-Vorlagen: Symbol per Klick wählen, Standard-Unterpunkte optional (eine Zeile = ein Unterpunkt).
      Tipp: Das ⭐ an jedem TOP speichert einen fertig geschriebenen Punkt direkt als Vorlage.</p>
    <div id="bs-mng-list"></div>
    <button class="btn btn-outline btn-sm" onclick="mngAddBs()">＋ Neue Vorlage</button>
  </div>
  <div id="pk-tops"></div>
  <button class="pk-ghost" onclick="addTop()">＋ Tagesordnungspunkt hinzufügen</button>
</div>

<!-- ── Speicherstatus ── -->
<div class="pk-savebar">
  <span class="pk-dot" id="pk-dot"></span>
  <span class="pk-savehint" id="pk-savehint">Live-Protokoll · Änderungen werden automatisch gespeichert</span>
  <button class="btn btn-primary btn-sm" id="pk-abschluss" onclick="statusWechseln()"></button>
</div>

<script>
'use strict';
const PROT_ID   = <?= (int) $protokoll['id'] ?>;
let   status    = <?= $json($protokoll['status'] ?: 'offen') ?>;
let   stamm     = <?= $json(json_decode($protokoll['teilnehmer'] ?: '[]', true) ?: []) ?>;
let   tops      = <?= $json(json_decode($protokoll['tops'] ?: '[]', true) ?: []) ?>;
let   vorlagenP = <?= $json($personenVorlagen) ?>;   // Vorlagen: übliche Teilnehmer
let   bausteine = <?= $json(array_map(
        fn ($b) => ['id' => (int) $b['id'], 'icon' => $b['icon'], 'titel' => $b['titel'],
                    'subs' => json_decode($b['subs'] ?: '[]', true) ?: []],
        $bausteineVorlagen
      )) ?>;

document.getElementById('pk-datum').value  = <?= $json($protokoll['datum']) ?>;
document.getElementById('pk-beginn').value = <?= $json($protokoll['beginn']) ?>;
document.getElementById('pk-ende').value   = <?= $json($protokoll['ende']) ?>;

/* ── Autosave ── */
let saveTimer = null, saving = false;
const dot = document.getElementById('pk-dot'), hint = document.getElementById('pk-savehint');

function protokollDaten() {
  return {
    datum:  document.getElementById('pk-datum').value,
    beginn: document.getElementById('pk-beginn').value,
    ende:   document.getElementById('pk-ende').value,
    status,
    teilnehmer: stamm,
    tops,
  };
}

function dirty() {
  dot.className = 'pk-dot pk-dot-orange';
  hint.textContent = 'Änderungen …';
  clearTimeout(saveTimer);
  saveTimer = setTimeout(speichern, 800);
}

async function speichern() {
  if (saving) { dirty(); return; }
  saving = true;
  try {
    const r = await fetch(`/api/protokoll/${PROT_ID}/speichern`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(protokollDaten()),
    });
    const j = await r.json();
    if (j.ok) {
      dot.className = 'pk-dot';
      hint.textContent = `Automatisch gespeichert · ${j.gespeichert}`;
    } else throw new Error(j.error || '?');
  } catch (e) {
    dot.className = 'pk-dot pk-dot-red';
    hint.textContent = 'Speichern fehlgeschlagen – Verbindung prüfen';
  }
  saving = false;
}

// Letzter Stand beim Verlassen der Seite (Beacon überlebt den Tab-Wechsel)
window.addEventListener('pagehide', () => {
  if (saveTimer) {
    navigator.sendBeacon(
      `/api/protokoll/${PROT_ID}/speichern`,
      new Blob([JSON.stringify(protokollDaten())], { type: 'application/json' })
    );
  }
});

['pk-datum', 'pk-beginn', 'pk-ende'].forEach(id =>
  document.getElementById(id).addEventListener('input', dirty));

/* ── Abschließen / Wieder öffnen ── */
function statusBtn() {
  const b = document.getElementById('pk-abschluss');
  b.textContent = status === 'offen' ? '✔ Protokoll abschließen' : '🔓 Wieder öffnen';
}
function statusWechseln() {
  if (status === 'offen') {
    if (!document.getElementById('pk-ende').value)
      document.getElementById('pk-ende').value = new Date().toTimeString().slice(0, 5);
    status = 'abgeschlossen';
  } else {
    status = 'offen';
  }
  statusBtn(); dirty();
}

/* ── Vorlagen speichern (Server) ── */
let vpTimer = null, vbTimer = null;
function saveVorlagenP() {
  clearTimeout(vpTimer);
  vpTimer = setTimeout(async () => {
    const r = await fetch('/api/protokoll-vorlagen/personen', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ personen: vorlagenP }),
    });
    const j = await r.json();
    if (j.ok) vorlagenP = j.personen.map(p => ({ ...p, standard: +p.standard }));
  }, 700);
}
function saveVorlagenB() {
  clearTimeout(vbTimer);
  vbTimer = setTimeout(async () => {
    await fetch('/api/protokoll-vorlagen/bausteine', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ bausteine }),
    });
  }, 700);
}

/* ── Teilnehmer-Chips ── */
const chipsEl = document.getElementById('pk-chips');

function renderChips() {
  chipsEl.innerHTML = '';
  stamm.forEach((p, i) => {
    if (!p.name.trim()) return;
    const c = document.createElement('span');
    c.className = 'pk-chip' + (p.anwesend ? ' on' : '');
    c.innerHTML = `<span class="pk-tick">✓</span> ${esc(p.name)} <span class="pk-role">· ${esc(p.funktion || '–')}</span>`
      + (p.extra ? ` <span class="pk-x" title="Entfernen">✕</span>` : '');
    c.onclick = () => { p.anwesend = !p.anwesend; renderChips(); dirty(); };
    const x = c.querySelector('.pk-x');
    if (x) x.onclick = ev => { ev.stopPropagation(); stamm.splice(i, 1); renderChips(); dirty(); };
    chipsEl.appendChild(c);
  });
  const add = document.createElement('span');
  add.className = 'pk-chip add';
  add.textContent = '＋ Weitere Person';
  add.onclick = () => {
    document.getElementById('np-form').classList.toggle('open');
    document.getElementById('np-name').focus();
  };
  chipsEl.appendChild(add);
  document.getElementById('pk-tn-count').textContent =
    stamm.filter(p => p.anwesend).length + ' anwesend';
}

function addPerson() {
  const n = document.getElementById('np-name').value.trim();
  if (!n) return;
  const funktion = document.getElementById('np-role').value.trim();
  const asVorlage = document.getElementById('np-save').checked;
  stamm.push({ name: n, funktion, anwesend: true, extra: !asVorlage });
  if (asVorlage) { vorlagenP.push({ name: n, funktion, standard: 0 }); saveVorlagenP(); renderTnMng(); }
  document.getElementById('np-name').value = '';
  document.getElementById('np-role').value = '';
  document.getElementById('np-form').classList.remove('open');
  renderChips(); dirty();
}
document.getElementById('np-name').addEventListener('keydown', e => { if (e.key === 'Enter') addPerson(); });

/* ── Vorlagen-Verwaltung: Teilnehmer ── */
function toggleMng(which) {
  document.getElementById(which + '-mng').classList.toggle('open');
  document.getElementById(which + '-mng-btn').classList.toggle('on');
  if (which === 'tn') renderTnMng(); else renderBsMng();
}

function renderTnMng() {
  const list = document.getElementById('tn-mng-list');
  list.innerHTML = '';
  vorlagenP.forEach((p, i) => {
    const row = document.createElement('div');
    row.className = 'pk-mng-row';
    const name = document.createElement('input');
    name.className = 'grow'; name.value = p.name; name.placeholder = 'Name';
    name.oninput = () => { p.name = name.value; saveVorlagenP(); };
    const role = document.createElement('input');
    role.className = 'half'; role.value = p.funktion; role.placeholder = 'Funktion';
    role.oninput = () => { p.funktion = role.value; saveVorlagenP(); };
    const std = document.createElement('label');
    std.className = 'pk-cb'; std.title = 'Bei neuem Protokoll automatisch angehakt';
    const cb = document.createElement('input');
    cb.type = 'checkbox'; cb.checked = !!+p.standard;
    cb.onchange = () => { p.standard = cb.checked ? 1 : 0; saveVorlagenP(); };
    std.append(cb, ' Standard');
    const del = document.createElement('button');
    del.className = 'pk-ib'; del.textContent = '🗑'; del.title = 'Vorlage löschen';
    del.onclick = () => {
      if (confirm(`Vorlage „${p.name}“ wirklich löschen?`)) {
        vorlagenP.splice(i, 1); saveVorlagenP(); renderTnMng();
      }
    };
    row.append(name, role, std, del);
    list.appendChild(row);
  });
}

function mngAddPerson() {
  vorlagenP.push({ name: '', funktion: '', standard: 0 });
  renderTnMng();
  const inputs = document.querySelectorAll('#tn-mng-list .pk-mng-row input.grow');
  inputs[inputs.length - 1].focus();
}

/* ── Symbol-Auswahl ── */
const ICONS = [
  '👋','🎪','🕌','💶','🗳️','🤝','💬','📌','📅','👥','📄','⭐',
  '🧕','🧑‍🤝‍🧑','👨‍👩‍👧','🏗️','🚗','📢','✉️','🎓','📖','🍽️','🎉','🕋',
  '🧹','🔧','💻','🖨️','📊','⚖️','🏛️','🌙','🤲','🪧','🧾','🔑',
];

function iconPicker(value, onChange) {
  const wrap = document.createElement('div');
  wrap.className = 'pk-icon-pick';
  const btn = document.createElement('button');
  btn.type = 'button'; btn.title = 'Symbol wählen';
  btn.textContent = value || '📄';
  const pop = document.createElement('div');
  pop.className = 'pk-icon-pop';
  ICONS.forEach(e => {
    const s = document.createElement('span');
    s.textContent = e;
    s.onclick = ev => { ev.stopPropagation(); btn.textContent = e; pop.classList.remove('open'); onChange(e); };
    pop.appendChild(s);
  });
  btn.onclick = ev => {
    ev.stopPropagation();
    document.querySelectorAll('.pk-icon-pop.open').forEach(p => p !== pop && p.classList.remove('open'));
    pop.classList.toggle('open');
  };
  wrap.append(btn, pop);
  return wrap;
}
document.addEventListener('click', () =>
  document.querySelectorAll('.pk-icon-pop.open').forEach(p => p.classList.remove('open')));

/* ── Vorlagen-Verwaltung: Textbausteine ── */
function renderBsMng() {
  const list = document.getElementById('bs-mng-list');
  list.innerHTML = '';
  bausteine.forEach((b, i) => {
    const row = document.createElement('div');
    row.className = 'pk-mng-row';
    const icon = iconPicker(b.icon, v => { b.icon = v; saveVorlagenB(); renderBausteine(); });
    const title = document.createElement('input');
    title.className = 'half'; title.value = b.titel; title.placeholder = 'Titel des TOP';
    title.oninput = () => { b.titel = title.value; saveVorlagenB(); renderBausteine(); };
    const subs = document.createElement('textarea');
    subs.className = 'grow'; subs.rows = 1;
    subs.placeholder = 'Standard-Unterpunkte (eine Zeile = ein Unterpunkt, leer = keiner)';
    subs.value = b.subs.filter(s => s.trim()).join('\n');
    subs.oninput = () => { b.subs = subs.value.split('\n').filter(s => s.trim()); saveVorlagenB(); autoGrow(subs); };
    const del = document.createElement('button');
    del.className = 'pk-ib'; del.textContent = '🗑'; del.title = 'Vorlage löschen';
    del.onclick = () => {
      if (confirm(`Vorlage „${b.titel}“ wirklich löschen?`)) {
        bausteine.splice(i, 1); saveVorlagenB(); renderBausteine(); renderBsMng();
      }
    };
    row.append(icon, title, subs, del);
    list.appendChild(row);
  });
  list.querySelectorAll('textarea').forEach(autoGrow);
}

function mngAddBs() {
  bausteine.push({ icon: '📄', titel: '', subs: [] });
  renderBsMng(); renderBausteine();
  const inputs = document.querySelectorAll('#bs-mng-list .pk-mng-row input.half');
  inputs[inputs.length - 1].focus();
}

function saveTopAsVorlage(t) {
  if (!t.titel.trim()) { alert('Bitte zuerst einen Titel für den TOP vergeben.'); return; }
  const subs = t.subs.filter(s => s.trim());
  const existing = bausteine.find(b => b.titel === t.titel);
  if (existing) {
    if (!confirm(`Vorlage „${t.titel}“ existiert bereits – mit den aktuellen Unterpunkten überschreiben?`)) return;
    existing.subs = subs;
  } else {
    bausteine.push({ icon: '⭐', titel: t.titel, subs });
  }
  saveVorlagenB(); renderBausteine(); renderBsMng();
}

/* ── Textbaustein-Chips ── */
function renderBausteine() {
  const box = document.getElementById('pk-bausteine');
  box.querySelectorAll('.pk-bs').forEach(el => el.remove());
  bausteine.forEach(b => {
    if (!b.titel.trim()) return;
    const el = document.createElement('span');
    const used = tops.some(t => t.titel === b.titel);
    el.className = 'pk-bs' + (used ? ' used' : '');
    el.title = used ? 'Bereits in der Tagesordnung – erneut einfügen möglich' : 'Als TOP einfügen';
    el.textContent = `${b.icon} ${b.titel}`;
    el.onclick = () => {
      tops.push({ titel: b.titel, subs: b.subs.length ? [...b.subs] : [''] });
      renderTops(); dirty();
      if (b.titel.includes('…')) {   // Platzhalter im Titel → erst Titel vervollständigen
        const inputs = topsEl.querySelectorAll('.pk-top-head input');
        const t = inputs[inputs.length - 1];
        t.focus(); t.selectionStart = t.selectionEnd = b.titel.indexOf('…') + 1;
      } else {
        focusSub(tops.length - 1, 0);
      }
    };
    box.appendChild(el);
  });
}

/* ── Tagesordnung ── */
const topsEl = document.getElementById('pk-tops');

function renderTops() {
  topsEl.innerHTML = '';
  tops.forEach((t, ti) => {
    const box = document.createElement('div');
    box.className = 'pk-top';

    const head = document.createElement('div');
    head.className = 'pk-top-head';
    head.innerHTML = `<span class="pk-top-num">${ti + 1}</span>`;
    const title = document.createElement('input');
    title.type = 'text';
    title.placeholder = 'Titel des Tagesordnungspunkts …';
    title.value = t.titel;
    title.oninput = () => { t.titel = title.value; dirty(); };
    title.onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); focusSub(ti, 0); } };
    const star = document.createElement('button');
    star.className = 'pk-star'; star.title = 'Diesen TOP als Vorlage speichern'; star.textContent = '⭐';
    star.onclick = () => saveTopAsVorlage(t);
    const del = document.createElement('button');
    del.className = 'pk-ib'; del.title = 'Punkt entfernen'; del.textContent = '🗑';
    del.onclick = () => {
      const leer = !t.titel.trim() && !t.subs.some(s => s.trim());
      if (leer || confirm(`TOP ${ti + 1} „${t.titel || 'ohne Titel'}“ wirklich entfernen?`)) {
        tops.splice(ti, 1); renderTops(); dirty();
      }
    };
    head.append(title, star, del);
    box.appendChild(head);

    const list = document.createElement('div');
    list.className = 'pk-subs';
    t.subs.forEach((s, si) => {
      const row = document.createElement('div');
      row.className = 'pk-sub';
      const letter = document.createElement('span');
      letter.className = 'pk-sub-letter';
      letter.textContent = String.fromCharCode(97 + (si % 26)) + ')';
      const ta = document.createElement('textarea');
      ta.rows = 1;
      ta.placeholder = 'Beschluss / Notiz … (Enter = nächster Unterpunkt)';
      ta.value = s;
      ta.dataset.ti = ti; ta.dataset.si = si;
      ta.oninput = () => { t.subs[si] = ta.value; autoGrow(ta); dirty(); };
      ta.onkeydown = e => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          t.subs.splice(si + 1, 0, '');
          renderTops(); focusSub(ti, si + 1); dirty();
        }
        if (e.key === 'Backspace' && ta.value === '' && t.subs.length > 1) {
          e.preventDefault();
          t.subs.splice(si, 1);
          renderTops(); focusSub(ti, Math.max(0, si - 1)); dirty();
        }
      };
      const rm = document.createElement('button');
      rm.className = 'pk-ib'; rm.title = 'Unterpunkt entfernen'; rm.textContent = '✕';
      rm.onclick = () => {
        if (t.subs.length > 1) t.subs.splice(si, 1); else t.subs[0] = '';
        renderTops(); dirty();
      };
      row.append(letter, ta, rm);
      list.appendChild(row);
    });

    const addSub = document.createElement('button');
    addSub.className = 'btn btn-outline btn-xs'; addSub.style.marginTop = '4px';
    addSub.textContent = '＋ Unterpunkt';
    addSub.onclick = () => { t.subs.push(''); renderTops(); focusSub(ti, t.subs.length - 1); dirty(); };
    list.appendChild(addSub);

    box.appendChild(list);
    topsEl.appendChild(box);
  });
  topsEl.querySelectorAll('textarea').forEach(autoGrow);
  renderBausteine();
}

function addTop() {
  tops.push({ titel: '', subs: [''] });
  renderTops(); dirty();
  const t = topsEl.querySelectorAll('.pk-top-head input');
  t[t.length - 1].focus();
}
function focusSub(ti, si) {
  const ta = topsEl.querySelector(`textarea[data-ti="${ti}"][data-si="${si}"]`);
  if (ta) { ta.focus(); ta.selectionStart = ta.value.length; }
}
function autoGrow(ta) { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; }
function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

statusBtn();
renderChips();
renderTops();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
