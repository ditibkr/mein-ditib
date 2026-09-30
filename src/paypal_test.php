<?php
/**
 * PayPal-API-Verbindungstest (CLI)
 * Aufruf im Container: php /var/www/html/src/paypal_test.php [tage]
 * Liest nur – es werden KEINE Spenden angelegt.
 */
require_once __DIR__ . '/../vendor/autoload.php';
define('ROOT', dirname(__DIR__));

use App\PayPal\ApiClient;

$cfg  = require ROOT . '/config/settings.php';
$tage = min(max((int) ($argv[1] ?? 7), 1), 31);

echo "Modus: " . ($cfg['paypal_mode'] ?? 'sandbox') . "\n";

try {
    $client = new ApiClient($cfg);

    echo "1) OAuth2-Token holen … ";
    $client->tokenHolen();
    echo "OK\n";

    echo "2) Transaktionen der letzten $tage Tage abrufen … ";
    $von = (new DateTime("-$tage days"))->setTime(0, 0);
    $txs = $client->transaktionenAbrufen($von, new DateTime('now'));
    echo count($txs) . " gefunden\n\n";

    foreach ($txs as $tx) {
        $info  = $tx['transaction_info'] ?? [];
        $payer = $tx['payer_info'] ?? [];
        printf(
            "%-20s %8s %s  %-6s %-30s %s\n",
            $info['transaction_id'] ?? '?',
            ($info['transaction_amount']['value'] ?? '?') . ' ' . ($info['transaction_amount']['currency_code'] ?? ''),
            substr($info['transaction_initiation_date'] ?? '', 0, 10),
            $info['transaction_status'] ?? '?',
            trim(($payer['payer_name']['given_name'] ?? '') . ' ' . ($payer['payer_name']['surname'] ?? '')),
            $payer['email_address'] ?? ''
        );
    }

    echo "\n3) Mapping-Vorschau (nur eingehende EUR-Zahlungen ≥ Mindestbetrag, ohne Duplikate):\n";
    foreach ($client->spendenAbrufen($tage) as $s) {
        printf(
            "→ %s %s · %.2f € · %s · %s %s %s\n",
            $s['vorname'], $s['nachname'], $s['betrag'], $s['datum'],
            $s['strasse'], $s['plz'], $s['ort']
        );
    }
    echo "\n✓ Test abgeschlossen\n";
} catch (Exception $e) {
    echo "\n✕ FEHLER: " . $e->getMessage() . "\n";
    exit(1);
}
