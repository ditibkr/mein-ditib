<?php // erwartet $s['art']
$art = $s['art'];
if (str_contains($art, 'Mitglied')) {
    echo '<span class="type-badge t-mitg">🏷 Mitgliedsbeitrag</span>';
} elseif (str_contains($art, 'Sammel')) {
    echo '<span class="type-badge t-samm">📦 Sammel</span>';
} else {
    echo '<span class="type-badge t-geld">💶 Geldspende</span>';
}
