<?php
/**
 * TEST: Ordner-Verschiebung
 * Prüft ob Mails im richtigen IMAP-Ordner gelandet sind
 */
define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';

$cfg = require ROOT . '/config/settings.php';

echo "=== TEST: IMAP-Ordner ===\n\n";

$cm = new \Webklex\PHPIMAP\ClientManager();
$client = $cm->make([
    'host' => $cfg['imap_host'],
    'port' => $cfg['imap_port'],
    'encryption' => 'ssl',
    'validate_cert' => false,
    'username' => $cfg['imap_user'],
    'password' => $cfg['imap_password'],
    'protocol' => 'imap',
]);
$client->connect();

$zuPruefen = [
    'INBOX' => 'Posteingang',
    $cfg['imap_copy_folder'] => 'Kopier-Ziel',
    $cfg['imap_done_folder'] => 'Erledigt',
    $cfg['imap_low_folder'] => 'Geringbetrag',
    $cfg['imap_sent_folder'] => 'Gesendete',
];

$filter = $cfg['imap_subject_filter'] ?? 'Zahlungseingang';
$folders = $client->getFolders()->map(fn($f) => $f->full_name)->toArray();

foreach ($zuPruefen as $ordner => $label) {
    if (!in_array($ordner, $folders)) {
        echo "✗ $label ('$ordner'): nicht gefunden\n";
        continue;
    }
    $mails = $client->getFolder($ordner)->query()->all()->setFetchBody(false)->get();
    $paypal = $mails->filter(
        fn($m) =>
        mb_stripos(mb_decode_mimeheader((string) $m->subject), $filter) !== false
    );
    echo "✓ $label ('$ordner'): " . $mails->count() . " Mails gesamt, " . $paypal->count() . " PayPal\n";
}

echo "\n=== TEST ABGESCHLOSSEN ===\n";