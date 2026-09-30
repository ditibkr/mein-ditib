<?php
// 1. Bootstrap – immer gleich
define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';
$cfg = require ROOT . '/config/settings.php';

echo "=== TEST: Was du testest ===\n\n";

// 2. Vorbedingung prüfen
// z.B. DB-Verbindung, IMAP-Verbindung

// 3. Aktion ausführen
// z.B. eine Funktion aufrufen

// 4. Ergebnis prüfen
if ($ergebnis === $erwartet) {
    echo "✓ Test bestanden\n";
} else {
    echo "✗ FEHLER: Erwartet '$erwartet', bekommen '$ergebnis'\n";
}

echo "\n=== FERTIG ===\n";