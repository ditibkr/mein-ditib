<?php
// Erwartet $s als Array mit key 'status'
// Sicher auch wenn $s nicht gesetzt ist
$spende = is_array($spende ?? null) ? $spende : [];
$status = $spende['status'] ?? (is_array($s ?? null) ? ($s['status'] ?? '') : '');
if (!empty($spende['auslaendisch'])) {
    echo '<span class="badge b-ausl">🌍 Ausländisch – keine Bescheinigung</span>';
} else {
    $map = [
        'neu'               => ['b-neu',  '● Neu'],
        'freigegeben'       => ['b-frei', '● Freigegeben'],
        'versendet'         => ['b-vers', '● Versendet'],
        'erledigt'          => ['b-erl',  '✓ Erledigt'],
        'fehler'            => ['b-fehl', '● Fehler'],
        'auslaendisch'      => ['b-ausl', '🌍 Ausländisch – keine Bescheinigung'],
        'nicht_erforderlich'=> ['b-ausl', '— Nicht erforderlich'],
        'sammelbescheinigt' => ['b-vers', '📦 Sammelbescheinigt'],
    ];
    [$cls, $label] = $map[$status] ?? ['b-fehl', '● ' . htmlspecialchars($status)];
    echo "<span class=\"badge $cls\">$label</span>";
}
