<?php
$title = 'Dashboard';
$page = 'dashboard';
ob_start();
$cfg = require ROOT . '/config/settings.php';
?>

<?php if ($cfg['testmodus'] ?? false): ?>
  <div style="background:#f7994f22;border:1px solid var(--orange);border-radius:8px;
            padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
    <span style="font-size:20px">⚠️</span>
    <div>
      <strong style="color:var(--orange)">Testmodus aktiv</strong>
      <span style="color:var(--muted);margin-left:8px;font-size:13px">
        Versandmails gehen an: <code><?= htmlspecialchars($cfg['test_email']) ?></code>
      </span>
    </div>
  </div>
<?php endif ?>

<div class="page-header">
  <div>
    <h2>Dashboard</h2>
    <p>Spendeneingang und PayPal-Gebühren · Stand <?= date('d.m.Y') ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-outline btn-sm" onclick="jobManuell(this)">▶ Job starten</button>
    <a href="/manuell" class="btn btn-primary btn-sm">+ Neue Bescheinigung</a>
  </div>
</div>

<!-- ── Eine Filterzeile für alles darunter ── -->
<div class="filterbar">
  <span class="filterbar-lbl">Zeitraum</span>
  <div class="seg" role="group" aria-label="Zeitraum wählen">
    <button type="button" data-gran="woche" aria-pressed="false">Woche</button>
    <button type="button" data-gran="monat" aria-pressed="true">Monat</button>
    <button type="button" data-gran="jahr" aria-pressed="false">Jahr</button>
    <button type="button" data-gran="frei" aria-pressed="false">Frei wählen</button>
  </div>

  <!-- Freie Zeitraumwahl – für einen einzelnen Tag von und bis gleich setzen -->
  <div class="freiwahl" id="freiwahl" hidden>
    <input type="date" id="von" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-01') ?>" aria-label="Von">
    <span class="bis">bis</span>
    <input type="date" id="bis" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" aria-label="Bis">
    <button type="button" class="btn btn-primary btn-sm" id="freiGo">Anzeigen</button>
    <span class="schnellwahl">
      <button type="button" class="linkbtn" data-schnell="heute">Heute</button>
      <button type="button" class="linkbtn" data-schnell="gestern">Gestern</button>
      <button type="button" class="linkbtn" data-schnell="7">Letzte 7 Tage</button>
      <button type="button" class="linkbtn" data-schnell="30">Letzte 30 Tage</button>
    </span>
  </div>

  <span class="zeitraum-info" id="zeitraumInfo"></span>
</div>

<!-- ── KPIs: gewählter Zeitraum vs. gleich langes Fenster davor ── -->
<div class="kpi-grid" id="kpis"></div>

<!-- ── Diagramme ── -->
<div class="chart-grid">
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Spendeneingang</h3>
        <p id="subSpenden">Brutto je Periode</p>
      </div>
      <button class="linkbtn" type="button" data-toggle="tblSpenden">Tabelle</button>
    </div>
    <div class="chart-wrap">
      <svg id="chartSpenden" viewBox="0 0 520 260" role="img" aria-label="Spendeneingang je Periode"></svg>
      <div class="charttip" id="tipSpenden"></div>
    </div>
    <div class="tbl-wrap" id="tblSpenden" hidden></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div>
        <h3>PayPal-Gebühren</h3>
        <p id="subGebuehr">Abgezogene Gebühren je Periode</p>
      </div>
      <button class="linkbtn" type="button" data-toggle="tblGebuehr">Tabelle</button>
    </div>
    <div class="chart-wrap">
      <svg id="chartGebuehr" viewBox="0 0 520 260" role="img" aria-label="PayPal-Gebühren je Periode"></svg>
      <div class="charttip" id="tipGebuehr"></div>
    </div>
    <div class="tbl-wrap" id="tblGebuehr" hidden></div>
  </div>
</div>

<!-- ── Top-Spender im gewählten Zeitraum ── -->
<div class="chart-grid" id="topGrid">
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Top 10 Spender · nach Häufigkeit</h3>
        <p id="subTopAnzahl">Wie oft im Zeitraum gespendet</p>
      </div>
    </div>
    <div class="toplist" id="topAnzahl"></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div>
        <h3>Top 10 Spender · nach Betrag</h3>
        <p id="subTopSumme">Wie viel im Zeitraum gespendet</p>
      </div>
    </div>
    <div class="toplist" id="topSumme"></div>
  </div>
</div>

<!-- ── Bearbeitungsstand ── -->
<div class="card">
  <div class="card-header">
    <div>
      <h3>Bearbeitungsstand</h3>
      <p>Bescheinigungen – was liegt an?</p>
    </div>
    <a href="/liste" class="btn btn-outline btn-sm">Zur Liste →</a>
  </div>
  <div class="statusbar">
    <a href="/liste?status=neu" class="chip <?= $zaehler['neu'] > 0 ? 'attn' : '' ?>">
      <span class="dot" style="background:var(--accent2)"></span> Neu · wartet auf Prüfung <b><?= $zaehler['neu'] ?></b>
    </a>
    <a href="/liste?status=freigegeben" class="chip">
      <span class="dot" style="background:var(--green)"></span> Freigegeben · Versand offen <b><?= $zaehler['freigegeben'] ?></b>
    </a>
    <a href="/liste?status=versendet" class="chip">
      <span class="dot" style="background:var(--accent)"></span> Versendet <b><?= $zaehler['versendet'] ?></b>
    </a>
    <a href="/liste?status=erledigt" class="chip">
      <span class="dot" style="background:var(--muted)"></span> Erledigt <b><?= $zaehler['erledigt'] ?></b>
    </a>
    <a href="/liste?status=nicht_erforderlich" class="chip">
      <span class="dot" style="background:var(--muted)"></span> Nicht erforderlich <b><?= $zaehler['nicht_erforderlich'] ?></b>
    </a>
    <a href="/liste?status=fehler" class="chip <?= $zaehler['fehler'] > 0 ? 'attn-rot' : '' ?>">
      <span class="dot" style="background:var(--red)"></span> Fehler <b><?= $zaehler['fehler'] ?></b>
    </a>
  </div>
</div>

<!-- ── Letzte Einträge ── -->
<div class="card">
  <div class="card-header">
    <div>
      <h3>Zuletzt verarbeitet</h3>
      <p>Neueste 10 Einträge</p>
    </div>
    <a href="/liste" class="btn btn-outline btn-sm">Alle anzeigen →</a>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Spender</th>
          <th>Art</th>
          <th>Betrag</th>
          <th>Datum</th>
          <th>Status</th>
          <th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($letzte)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <div class="es-icon">📭</div>
                <p>Noch keine Einträge. Starte den Job oder erstelle manuell.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($letzte as $s): ?>
            <tr>
              <td data-label="Spender">
                <a href="/spende/<?= $s['id'] ?>" style="font-weight:600;color:var(--text)">
                  <?= htmlspecialchars($s['vorname'] . ' ' . $s['nachname']) ?>
                </a>
                <?php if ($s['email']): ?>
                  <br><span class="text-muted text-small"><?= htmlspecialchars($s['email']) ?></span>
                <?php endif ?>
              </td>
              <td data-label="Art"><?php include __DIR__ . '/parts/art_badge.php' ?></td>
              <td data-label="Betrag"><strong><?= number_format($s['betrag'], 2, ',', '.') ?> €</strong></td>
              <td data-label="Datum"><?= htmlspecialchars($s['datum']) ?></td>
              <td data-label="Status"><?php include __DIR__ . '/parts/status_badge.php' ?></td>
              <td class="td-actions">
                <?php if ($s['pdf_pfad']): ?>
                  <a href="/spende/<?= $s['id'] ?>/pdf" target="_blank" class="icon-btn preview">👁 PDF</a>
                <?php endif ?>
                <?php if ($s['status'] === 'neu'): ?>
                  <button class="icon-btn approve" onclick="freigeben(<?= $s['id'] ?>)">✓ Freigeben</button>
                <?php endif ?>
                <?php if ($s['status'] === 'freigegeben' && $s['email']): ?>
                  <button class="icon-btn send" onclick="versenden(<?= $s['id'] ?>, this)">✉ Senden</button>
                <?php endif ?>
                <button class="icon-btn"
                  onclick="openComment(<?= $s['id'] ?>, '<?= htmlspecialchars($s['vorname'] . ' ' . $s['nachname']) ?>')">💬</button>
              </td>
            </tr>
          <?php endforeach ?>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  // Startdaten kommen serverseitig mit, danach lädt die Filterzeile per /api/report nach
  let REPORT = <?= json_encode($report, JSON_UNESCAPED_UNICODE) ?>;
  let gran = 'monat';

  const eur = (n, k = 0) => n.toLocaleString('de-DE', { minimumFractionDigits: k, maximumFractionDigits: k }) + ' €';
  const dat = (iso) => new Date(iso + 'T12:00').toLocaleDateString('de-DE');
  const NAME = { woche: 'Woche', monat: 'Monat', jahr: 'Jahr', frei: 'Zeitraum' };
  const VOR = { woche: 'Vorwoche', monat: 'Vormonat', jahr: 'Vorjahr', frei: 'Vorzeitraum' };
  const EINHEIT = { tag: 'Tag', woche: 'Woche', monat: 'Monat', jahr: 'Jahr' };

  // richtung: hoch_gut (Spenden) · neutral (Gebühren folgen dem Volumen) · hoch_schlecht (Quote)
  function deltaChip(wert, richtung) {
    if (wert === null || wert === undefined) return '<span class="delta flat">–</span>';
    const pfeil = wert > 0 ? '▲' : wert < 0 ? '▼' : '■';
    const txt = (wert > 0 ? '+' : '') + wert.toLocaleString('de-DE', { maximumFractionDigits: 1 }) + ' %';
    let cls = 'flat';
    if (richtung === 'hoch_gut') cls = wert > 0 ? 'up' : wert < 0 ? 'down' : 'flat';
    if (richtung === 'hoch_schlecht') cls = wert > 0 ? 'down' : wert < 0 ? 'up' : 'flat';
    return `<span class="delta ${cls}">${pfeil} ${txt}</span>`;
  }

  function kpisZeichnen() {
    const k = REPORT.kpi;
    const quoteDelta = +(k.quote - k.quote_vor).toFixed(2);
    // Ø Spende je Transaktion – ohne Transaktionen gibt es keinen Schnitt, nicht durch 0 teilen
    const schnitt    = k.aktuell.anzahl    ? k.aktuell.brutto    / k.aktuell.anzahl    : 0;
    const schnittVor = k.vorzeitraum.anzahl ? k.vorzeitraum.brutto / k.vorzeitraum.anzahl : 0;
    const titel = gran === 'frei' ? 'Spendeneingang · gewählter Zeitraum'
      : gran === 'woche' ? 'Spendeneingang · laufende Woche'
        : gran === 'monat' ? 'Spendeneingang · laufender Monat' : 'Spendeneingang · laufendes Jahr';

    // Reicht der Vergleichszeitraum vor den ersten Datensatz, ist das Delta wertlos – das sagen wir auch
    const warnung = k.vergleich_unvollstaendig
      ? `<br><span class="warn-hinweis">⚠ Vergleich unvollständig – Daten erst ab ${dat(k.daten_beginn)}</span>`
      : '';

    document.getElementById('zeitraumInfo').innerHTML =
      `<b>${dat(k.zeitraum.akt[0])} – ${dat(k.zeitraum.akt[1])}</b><br>
       <span>Vergleich: ${dat(k.zeitraum.vor[0])} – ${dat(k.zeitraum.vor[1])}</span>${warnung}`;

    document.getElementById('kpis').innerHTML = `
      <div class="kpi hero">
        <div class="lbl">${titel}</div>
        <div class="val">${eur(k.aktuell.brutto)}</div>
        <div class="cmp">${deltaChip(k.delta_brutto, 'hoch_gut')} <span>ggü. ${VOR[gran]} (${eur(k.vorzeitraum.brutto)})</span></div>
      </div>
      <div class="kpi">
        <div class="lbl">Transaktionen</div>
        <div class="val">${k.aktuell.anzahl.toLocaleString('de-DE')} <span class="val-sub">(Ø ${eur(schnitt, 2)})</span></div>
        <div class="cmp">${deltaChip(k.delta_anzahl, 'hoch_gut')} <span>ggü. ${k.vorzeitraum.anzahl.toLocaleString('de-DE')} (Ø ${eur(schnittVor, 2)})</span></div>
      </div>
      <div class="kpi">
        <div class="lbl">PayPal-Gebühren</div>
        <div class="val" style="color:var(--serie-gebuehr)">${eur(k.aktuell.gebuehr)}</div>
        <div class="cmp">${deltaChip(k.delta_gebuehr, 'neutral')} <span>ggü. ${eur(k.vorzeitraum.gebuehr)}</span></div>
      </div>
      <div class="kpi">
        <div class="lbl">Gebührenquote</div>
        <div class="val">${k.quote.toLocaleString('de-DE', { minimumFractionDigits: 2 })} %</div>
        <div class="cmp">${deltaChip(quoteDelta, 'hoch_schlecht')} <span>Punkte ggü. ${k.quote_vor.toLocaleString('de-DE', { minimumFractionDigits: 2 })} %</span></div>
      </div>`;
  }

  // Säulendiagramm: eine Serie, dünne Marken, Haarlinien-Gitter, Ø-Referenzlinie
  function chartZeichnen(svgId, tipId, feld, farbe) {
    const b = REPORT.buckets;
    const svg = document.getElementById(svgId);
    const tip = document.getElementById(tipId);
    const W = 520, H = 260, L = 58, R = 12, T = 18, B = 34;
    const pw = W - L - R, ph = H - T - B;

    if (!b.length) { svg.innerHTML = ''; return; }

    const max = Math.max(...b.map(x => x[feld]), 1);
    const schritt = Math.pow(10, Math.floor(Math.log10(max))) / 2;
    const top = Math.max(Math.ceil(max / schritt) * schritt, schritt);
    const y = v => T + ph - (v / top) * ph;
    const avg = b.reduce((s, x) => s + x[feld], 0) / b.length;

    const band = pw / b.length;
    const bw = Math.min(24, Math.max(3, band - 8));
    const jedes = Math.ceil(b.length / 14);   // bei Tagesansicht nicht jedes Label setzen

    let s = '';
    for (let i = 0; i <= 4; i++) {
      const v = (top / 4) * i, yy = y(v);
      s += `<line x1="${L}" y1="${yy}" x2="${W - R}" y2="${yy}" stroke="var(--border)" stroke-width="1"/>`;
      const t = v >= 1000 ? (v / 1000).toLocaleString('de-DE') + 'k' : v.toLocaleString('de-DE');
      s += `<text x="${L - 8}" y="${yy + 4}" text-anchor="end" class="axis-text">${t}</text>`;
    }
    s += `<line x1="${L}" y1="${y(avg)}" x2="${W - R}" y2="${y(avg)}" stroke="var(--muted)" stroke-width="1"/>`;
    s += `<text x="${W - R}" y="${y(avg) - 5}" text-anchor="end" class="avg-text">Ø ${eur(avg)}</text>`;

    b.forEach((d, i) => {
      const x = L + band * i + (band - bw) / 2;
      const h = Math.max(2, (d[feld] / top) * ph);
      const yy = T + ph - h;
      const r = Math.min(4, h, bw / 2);
      const letzte = i === b.length - 1;
      const pfad = `M${x},${T + ph} L${x},${yy + r} Q${x},${yy} ${x + r},${yy} `
        + `L${x + bw - r},${yy} Q${x + bw},${yy} ${x + bw},${yy + r} L${x + bw},${T + ph} Z`;
      s += `<rect class="hit" x="${L + band * i}" y="${T}" width="${band}" height="${ph}" data-i="${i}"/>`;
      s += `<path class="col" d="${pfad}" fill="${farbe}" opacity="${letzte ? 1 : .85}"/>`;
      if (i % jedes === 0 || letzte) {
        s += `<text x="${x + bw / 2}" y="${H - B + 16}" text-anchor="middle" class="axis-text">${d.label}</text>`;
      }
    });

    // Nur den letzten Wert direkt beschriften – nicht jeden Balken
    if (b.length > 1) {
      const last = b[b.length - 1];
      const lx = L + band * (b.length - 1) + band / 2;
      s += `<text x="${lx}" y="${y(last[feld]) - 8}" text-anchor="middle" class="last-lbl">${eur(last[feld])}</text>`;
    }

    svg.innerHTML = s;

    svg.querySelectorAll('.hit').forEach(h => {
      h.addEventListener('mouseenter', e => {
        const i = +e.target.dataset.i, d = b[i], v = b[i - 1];
        const diff = v && v[feld] > 0 ? ((d[feld] - v[feld]) / v[feld]) * 100 : null;
        tip.innerHTML = `<div class="t-lbl">${d.label} · ab ${dat(d.start)}</div>`
          + `<div class="t-val">${eur(d[feld], 2)}</div>`
          + `<div class="t-cmp">${d.anzahl.toLocaleString('de-DE')} Transaktionen`
          + (diff !== null ? ' · ' + deltaChip(+diff.toFixed(1), feld === 'brutto' ? 'hoch_gut' : 'neutral') : '')
          + `</div>`;
        const box = svg.getBoundingClientRect();
        const px = (L + band * i + band / 2) / 520 * box.width;
        tip.style.left = Math.min(Math.max(px - 75, 0), Math.max(box.width - 160, 0)) + 'px';
        tip.classList.add('an');
      });
      h.addEventListener('mouseleave', () => tip.classList.remove('an'));
    });
  }

  // Tabellen-Zwilling – jeder Wert ist auch ohne Diagramm lesbar
  function tabelleZeichnen(id, feld, titel) {
    const b = REPORT.buckets;
    const kopf = REPORT.gran === 'frei' ? EINHEIT[REPORT.einheit] : NAME[gran];
    let r = '';
    b.forEach((d, i) => {
      const v = b[i - 1];
      const diff = v && v[feld] > 0 ? ((d[feld] - v[feld]) / v[feld]) * 100 : null;
      r += `<tr><td>${d.label}</td><td class="num">${eur(d[feld], 2)}</td>`
        + `<td class="num">${d.anzahl.toLocaleString('de-DE')}</td>`
        + `<td class="num">${diff === null ? '–' : deltaChip(+diff.toFixed(1), feld === 'brutto' ? 'hoch_gut' : 'neutral')}</td></tr>`;
    });
    document.getElementById(id).innerHTML =
      `<table><thead><tr><th>${kopf}</th><th class="num">${titel}</th>`
      + `<th class="num">Transaktionen</th><th class="num">ggü. Vorperiode</th></tr></thead><tbody>${r}</tbody></table>`;
  }

  // Rangliste: Name + Kennzahl, dazu ein proportionaler Balken (Anteil am Spitzenreiter)
  function toplisteZeichnen(id, eintraege, feld, format) {
    if (!eintraege.length) {
      document.getElementById(id).innerHTML =
        '<div class="empty-state"><p>Keine Spenden in diesem Zeitraum.</p></div>';
      return;
    }
    const max = Math.max(...eintraege.map(e => e[feld]));
    let h = '';
    eintraege.forEach((e, i) => {
      const anteil = (e[feld] / max) * 100;
      const zweit = feld === 'anzahl' ? eur(e.summe, 2) : e.anzahl + '×';
      h += `<div class="toprow">
              <span class="rang">${i + 1}</span>
              <span class="tname" title="${e.mail}">${e.name}</span>
              <span class="tbalken"><span class="tfill" style="width:${anteil.toFixed(1)}%"></span></span>
              <span class="twert">${format(e)}</span>
              <span class="tzweit">${zweit}</span>
            </div>`;
    });
    document.getElementById(id).innerHTML = h;
  }

  function topsZeichnen() {
    const t = REPORT.top;
    toplisteZeichnen('topAnzahl', t.nach_anzahl, 'anzahl', e => e.anzahl + '×');
    toplisteZeichnen('topSumme', t.nach_summe, 'summe', e => eur(e.summe, 2));

    document.getElementById('subTopAnzahl').textContent = 'Wie oft im Zeitraum gespendet';
    document.getElementById('subTopSumme').textContent = 'Wie viel im Zeitraum gespendet';
  }

  function alles() {
    const einheit = REPORT.gran === 'frei' ? EINHEIT[REPORT.einheit] : NAME[gran];
    document.getElementById('subSpenden').textContent = `Brutto je ${einheit} · ${REPORT.buckets.length} Balken`;
    document.getElementById('subGebuehr').textContent = `Gebühren je ${einheit} · ${REPORT.buckets.length} Balken`;
    kpisZeichnen();
    chartZeichnen('chartSpenden', 'tipSpenden', 'brutto', 'var(--serie-spenden)');
    chartZeichnen('chartGebuehr', 'tipGebuehr', 'gebuehr', 'var(--serie-gebuehr)');
    tabelleZeichnen('tblSpenden', 'brutto', 'Spenden');
    tabelleZeichnen('tblGebuehr', 'gebuehr', 'Gebühren');
    topsZeichnen();
  }

  // Beim Nachladen die alte Ansicht gedimmt stehen lassen – kein Layout-Sprung
  async function reportLaden(params) {
    const grids = document.querySelectorAll('.chart-grid');
    grids.forEach(g => g.style.opacity = '.55');
    try {
      const r = await fetch('/api/report?' + new URLSearchParams(params));
      REPORT = await r.json();
      alles();
    } catch (e) {
      if (typeof showNotif === 'function') showNotif('Auswertung konnte nicht geladen werden', false);
    } finally {
      grids.forEach(g => g.style.opacity = '1');
    }
  }

  document.querySelectorAll('.seg button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.seg button').forEach(b => b.setAttribute('aria-pressed', 'false'));
      btn.setAttribute('aria-pressed', 'true');
      gran = btn.dataset.gran;
      document.getElementById('freiwahl').hidden = gran !== 'frei';
      if (gran === 'frei') {
        reportLaden({ gran: 'frei', von: document.getElementById('von').value, bis: document.getElementById('bis').value });
      } else {
        reportLaden({ gran });
      }
    });
  });

  document.getElementById('freiGo').addEventListener('click', () => {
    reportLaden({ gran: 'frei', von: document.getElementById('von').value, bis: document.getElementById('bis').value });
  });

  // Schnellwahl – "Heute"/"Gestern" setzen von = bis, also genau ein Tag
  document.querySelectorAll('[data-schnell]').forEach(btn => {
    btn.addEventListener('click', () => {
      const w = btn.dataset.schnell;
      const iso = d => d.toISOString().slice(0, 10);
      let von, bis = iso(new Date());
      if (w === 'heute') {
        von = bis;
      } else if (w === 'gestern') {
        const g = new Date();
        g.setDate(g.getDate() - 1);
        von = bis = iso(g);
      } else {
        const d = new Date();
        d.setDate(d.getDate() - (parseInt(w, 10) - 1));
        von = iso(d);
      }
      document.getElementById('von').value = von;
      document.getElementById('bis').value = bis;
      reportLaden({ gran: 'frei', von, bis });
    });
  });

  document.querySelectorAll('[data-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const el = document.getElementById(btn.dataset.toggle);
      el.hidden = !el.hidden;
      btn.textContent = el.hidden ? 'Tabelle' : 'Diagramm';
    });
  });

  alles();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
