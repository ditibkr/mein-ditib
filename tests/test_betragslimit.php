<?php
/**
 * TEST: Betragslimit-Bewertung
 * Prüft ob Spenden korrekt nach Mindestbetrag sortiert wurden
 */
define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';

$cfg = require ROOT . '/config/settings.php';
$limit = (float) $cfg['mindestbetrag'];
$lowFolder = $cfg['imap_low_folder'] ?? 'spenden-kleinbetrag';

echo "=== TEST: Betragslimit ($limit€) ===\n\n";

$db = new PDO('sqlite:' . $cfg['db_path']);

// 1. Alle Spenden aus DB
$spenden = $db->query("SELECT id, vorname, nachname, betrag, status, quelle FROM spenden ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

echo "Letzte Spenden in DB:\n";
$fehler = 0;
foreach ($spenden as $s) {
    $betrag = (float) $s['betrag'];
    $erwartet = $betrag >= $limit ? 'neu/freigegeben/versendet' : 'geringbetrag/übersprungen';
    $istOk = $betrag >= $limit ? in_array($s['status'], ['neu', 'freigegeben', 'versendet', 'sammelbescheinigt']) : true;
    $symbol = $istOk ? '✓' : '✗';
    if (!$istOk)
        $fehler++;
    printf(
        "  %s ID%-3d | %s %s | %6.2f€ | Status: %s\n",
        $symbol,
        $s['id'],
        $s['vorname'],
        $s['nachname'],
        $betrag,
        $s['status']
    );
}

echo "\n";

// 2. Prüfe Ordner spenden-kleinbetrag im IMAP
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

$folders = $client->getFolders()->map(fn($f) => $f->full_name)->toArray();
if (in_array($lowFolder, $folders)) {
    $lowMails = $client->getFolder($lowFolder)->query()->all()->setFetchBody(false)->get();
    echo "✓ Ordner '$lowFolder': " . $lowMails->count() . " Mails\n";
} else {
    echo "✗ Ordner '$lowFolder' nicht gefunden\n";
}

echo "\nFehler: $fehler\n";
echo "=== TEST ABGESCHLOSSEN ===\n";