<?php
/**
 * TEST: Kopiervorgang
 * Prüft ob Mails korrekt nach spenden-eingang kopiert wurden
 */
define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';

$cfg = require ROOT . '/config/settings.php';
$ziel = $cfg['imap_copy_folder'] ?? 'spenden-eingang';
$filter = $cfg['imap_subject_filter'] ?? 'Zahlungseingang';

echo "=== TEST: Kopiervorgang ===\n\n";

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
echo "✓ IMAP verbunden\n";

// 1. Prüfe Zielordner existiert
$folders = $client->getFolders()->map(fn($f) => $f->full_name)->toArray();
if (in_array($ziel, $folders)) {
    echo "✓ Zielordner '$ziel' existiert\n";
} else {
    echo "✗ Zielordner '$ziel' nicht gefunden!\n";
    echo "  Verfügbare Ordner: " . implode(', ', $folders) . "\n";
    exit(1);
}

// 2. Mails im Zielordner zählen
$zielFolder = $client->getFolder($ziel);
$zielMails = $zielFolder->query()->all()->setFetchBody(false)->get();
$paypalMails = $zielMails->filter(
    fn($m) =>
    mb_stripos(mb_decode_mimeheader((string) $m->subject), $filter) !== false
);
echo "✓ Mails in '$ziel': " . $zielMails->count() . " gesamt, " . $paypalMails->count() . " mit Filter '$filter'\n";

// 3. Prüfe ob copy_ Einträge in DB vorhanden
$db = new PDO('sqlite:' . $cfg['db_path']);
$rows = $db->query("SELECT COUNT(*) FROM mail_ids WHERE mail_id LIKE 'copy_%'")->fetchColumn();
echo "✓ DB: $rows copy_-Einträge in mail_ids\n";

// 4. Zeige kopierte Mails
echo "\nKopierte Mails:\n";
foreach ($paypalMails as $msg) {
    $mid = trim((string) ($msg->message_id ?? 'noID'));
    $subj = mb_decode_mimeheader((string) $msg->subject);
    $inDb = $db->prepare("SELECT 1 FROM mail_ids WHERE mail_id = ?")->execute(['copy_' . $mid]);
    $stmt = $db->prepare("SELECT 1 FROM mail_ids WHERE mail_id = ?");
    $stmt->execute(['copy_' . $mid]);
    $bekannt = $stmt->fetch() ? '✓ in DB' : '✗ nicht in DB';
    echo "  $bekannt | $subj\n";
}

echo "\n=== TEST ABGESCHLOSSEN ===\n";