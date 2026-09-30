<?php
/**
 * Einmaliger PayPal-Import-Test (CLI) – repliziert Schritt 2b des Cron-Jobs,
 * ohne paypal_api_aktiv in den Einstellungen zu ändern.
 * Aufruf im Container: php /var/www/html/src/paypal_import_einmalig.php
 */
require_once __DIR__ . '/../vendor/autoload.php';
define('ROOT', dirname(__DIR__));

use App\DB\Database;
use App\PayPal\ApiClient;
use App\Pdf\Generator;

$cfg = require ROOT . '/config/settings.php';
echo "Modus: " . ($cfg['paypal_mode'] ?? 'sandbox') . "\n";

$paypal  = new ApiClient($cfg);
$spenden = $paypal->spendenAbrufen((int) ($cfg['paypal_import_tage'] ?? 3));
echo count($spenden) . " neue Transaktionen zum Import\n";

$gen = new Generator();
foreach ($spenden as $s) {
    $sid = Database::spendeErstellen($s);
    if (!$sid) {
        echo "✕ Anlegen fehlgeschlagen: {$s['vorname']} {$s['nachname']} {$s['betrag']} €\n";
        continue;
    }
    $pdf = $gen->erstellen($s);
    if ($pdf) {
        Database::spendeUpdate($sid, ['pdf_pfad' => $pdf, 'status' => 'neu']);
        Database::kommentarAdd($sid, "[SANDBOX-TEST] Automatisch erstellt – Mail-ID: " . substr($s['mail_id'] ?? '', 0, 50), 'System', 'system');
        echo "✓ Spende #$sid angelegt · {$s['betrag']} € · PDF: $pdf\n";
    } else {
        Database::spendeUpdate($sid, ['status' => 'fehler']);
        echo "✕ Spende #$sid angelegt, aber PDF fehlgeschlagen\n";
    }
}

$tage = min(max((int) ($cfg['paypal_import_tage'] ?? 3), 1), 31);
$sync = $paypal->synchronisieren((new DateTime("-$tage days"))->setTime(0, 0), new DateTime('now'));
echo "Sync: {$sync['gespeichert']} Zahlungen in paypal_transaktionen aktualisiert\n";
echo "✓ Fertig\n";
