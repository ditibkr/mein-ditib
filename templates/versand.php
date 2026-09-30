<?php
$title = 'Versand-Freigabe';
$page  = 'versand';
ob_start();
?>

<div class="page-header">
  <div><h2>Versand-Freigabe</h2><p><?= count($spenden) ?> Bescheinigung(en) bereit zum Versand</p></div>
</div>

<?php if (!empty($spenden)): ?>
<div class="alert alert-warn">
  ⚠️ Bitte immer <strong>👁 PDF prüfen</strong> bevor du versendest – die Mail kann nicht zurückgerufen werden.
</div>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;padding:10px 14px;
            background:var(--surface);border:1px solid var(--border);border-radius:8px">
  <span style="font-size:13px;font-weight:600">Mailsprache für alle:</span>
  <select id="mailSprache" style="padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:var(--bg);font-size:13px;cursor:pointer">
    <option value="tr">🇹🇷 Türkisch</option>
    <option value="de">🇩🇪 Deutsch</option>
  </select>
  <span style="font-size:12px;color:var(--muted)">Gilt für alle Versand-Buttons auf dieser Seite</span>
</div>
<?php endif ?>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Spender</th><th>Art</th><th>Betrag</th>
          <th>Datum</th><th>PDF</th><th>Mail an</th><th>Kommentar</th><th>Aktion</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($spenden)): ?>
          <tr><td colspan="8">
            <div class="empty-state">
              <div class="es-icon">✅</div>
              <p>Keine freigegebenen Bescheinigungen – alles erledigt!</p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($spenden as $s): ?>
          <tr>
            <td data-label="Spender"><strong><?= htmlspecialchars($s['vorname'].' '.$s['nachname']) ?></strong></td>
            <td data-label="Art"><?php include __DIR__ . '/parts/art_badge.php' ?></td>
            <td data-label="Betrag"><strong><?= number_format($s['betrag'],2,',','.') ?> €</strong></td>
            <td data-label="Datum"><?= htmlspecialchars($s['datum']) ?></td>
            <td data-label="PDF">
              <?php if ($s['pdf_pfad'] && file_exists($s['pdf_pfad'])): ?>
                <a href="/spende/<?= $s['id'] ?>/pdf" target="_blank" class="icon-btn preview">👁 Prüfen</a>
              <?php else: ?>
                <span class="text-small" style="color:var(--red)">Kein PDF</span>
              <?php endif ?>
            </td>
            <td data-label="Mail"><?= htmlspecialchars($s['email'] ?: '—') ?></td>
            <td data-label="Kommentar">
              <button class="icon-btn" onclick="openComment(<?= $s['id'] ?>,'<?= htmlspecialchars($s['vorname'].' '.$s['nachname']) ?>')">💬</button>
            </td>
            <td>
              <?php if ($s['email'] && $s['pdf_pfad'] && file_exists($s['pdf_pfad'])): ?>
                <button class="btn btn-success btn-sm" onclick="versenden(<?= $s['id'] ?>, this)">✉ Versenden</button>
              <?php elseif (!$s['email']): ?>
                <span class="text-small text-muted">🖨 Nur Druck</span>
              <?php else: ?>
                <span class="text-small" style="color:var(--red)">PDF fehlt</span>
              <?php endif ?>
              <?php if ($s['pdf_pfad'] && file_exists($s['pdf_pfad'])): ?>
                <button class="btn btn-outline btn-sm" onclick="postversand(<?= $s['id'] ?>, this)">📬 Post</button>
              <?php endif ?>
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
require __DIR__ . '/layout.php';
