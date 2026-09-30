<?php
/**
 * TEST: Parser – prüft ob Mails korrekt ausgelesen werden
 * Zeigt was der Parser aus echten Mails extrahiert
 */
define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';
use App\Imap\MailReader;

$cfg = require ROOT . '/config/settings.php';
$filter = $cfg['imap_subject_filter'] ?? 'Zahlungseingang';
$stunden = (int) ($cfg['imap_copy_stunden'] ?? 24);

echo "=== TEST: Parser ===\n\n";

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

$seit = new \DateTime("-{$stunden} hours");
$messages = $client->getFolder('INBOX')
    ->query()->since($seit)->leaveUnread()->get();
$messages = $messages->filter(
    fn($m) =>
    mb_stripos(mb_decode_mimeheader((string) $m->subject), $filter) !== false
);

echo "Gefundene Mails: " . $messages->count() . "\n\n";

// Parser via Reflection aufrufen (private Methode)
$reader = new MailReader();
$ref = new ReflectionClass($reader);

$getBody = $ref->getMethod('getBody');
$getBody->setAccessible(true);

$parse = $ref->getMethod('parse');
$parse->setAccessible(true);

// Client setzen
$clientProp = $ref->getProperty('client');
$clientProp->setAccessible(true);
$clientProp->setValue($reader, $client);

$ok = 0;
$fehler = 0;

foreach ($messages as $msg) {
    $subj = mb_decode_mimeheader((string) $msg->subject);
    echo "── Mail: $subj\n";

    $body = $getBody->invoke($reader, $msg);
    $result = $parse->invoke($reader, $body);

    if ($result) {
        $ok++;
        echo "  ✓ Vorname:    {$result['vorname']}\n";
        echo "  ✓ Nachname:   {$result['nachname']}\n";
        echo "  ✓ Betrag:     {$result['betrag']}€\n";
        echo "  ✓ Datum:      {$result['datum']}\n";
        echo "  ✓ PLZ/Ort:    " . ($result['plz'] ?? '?') . " " . ($result['ort'] ?? '?') . "\n";
        echo "  ✓ E-Mail:     " . ($result['email'] ?? '?') . "\n";
        echo "  ✓ Art:        {$result['art']}\n";
    } else {
        $fehler++;
        echo "  ✗ Parser fehlgeschlagen!\n";
        echo "  Body (erste 300 Zeichen):\n";
        echo "  " . substr(str_replace("\n", "\n  ", $body), 0, 300) . "\n";
    }
    echo "\n";
}

echo "Ergebnis: $ok erfolgreich, $fehler fehlgeschlagen\n";
echo "=== TEST ABGESCHLOSSEN ===\n";