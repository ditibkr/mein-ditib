<?php
/**
 * Nächtlicher Cron-Job
 * Wird von Linux-Cron aufgerufen: php /var/www/html/src/cron_job.php
 * Kann auch manuell gestartet werden!
 */
require_once __DIR__ . '/../vendor/autoload.php';
define('ROOT', dirname(__DIR__));

use App\DB\Database;
use App\Imap\MailReader;
use App\Mail\Report;
use App\Pdf\Generator;

Database::logAdd('INFO', '=== Cron-Job gestartet ===');
$startzeit = microtime(true);
$cfg = require ROOT . '/config/settings.php';
$testmodus = $cfg['testmodus'] ?? false;
$anzahl = 0;
$paypalAnzahl = null;   // bleibt null, wenn die PayPal-API nicht aktiv ist

if ($testmodus) {
    Database::logAdd('WARN', '⚠ TESTMODUS AKTIV – Versandmails gehen an: ' . ($cfg['test_email'] ?? '?'));
}

// ── Schritt 1: Mails IMMER aus der INBOX holen ──
// Läuft auch im reinen PayPal-API-Betrieb, sonst füllt sich der Posteingang.
$reader = new MailReader();
$verschoben = $reader->posteingang_kopieren();
Database::logAdd('OK', "Verschiebe-Job: {$verschoben['kopiert']} verschoben, {$verschoben['uebersprungen']} übersprungen, {$verschoben['fehler']} Fehler");

// ── Schritt 2: Mails aus spenden-eingang verarbeiten (abschaltbar) ──
$spenden = [];
if ($cfg['imap_verarbeitung_aktiv'] ?? true) {
    // Mail-Parsing aktiv → Einträge + PDFs erzeugen
    $spenden = $reader->verarbeitenEingang();
} else {
    // Mail-Parsing aus (PayPal-API liefert die Spenden) → Mails nur archivieren,
    // damit auch spenden-eingang nicht voll läuft. Keine Spenden-Einträge.
    $archiv = $reader->eingangVerschieben();
    Database::logAdd('INFO', "Mail-Parsing deaktiviert (imap_verarbeitung_aktiv=false) – {$archiv['verschoben']} Mails archiviert, Spenden kommen über die PayPal-API");
}

// ── Schritt 2b: PayPal-API-Import (falls in den Einstellungen aktiviert) ──
if (!empty($cfg['paypal_api_aktiv'])) {
    try {
        $paypal = new App\PayPal\ApiClient($cfg);
        $apiSpenden = $paypal->spendenAbrufen((int) ($cfg['paypal_import_tage'] ?? 3));
        $paypalAnzahl = count($apiSpenden);
        Database::logAdd('OK', 'PayPal-API: ' . $paypalAnzahl . ' neue Transaktionen (' . ($cfg['paypal_mode'] ?? 'sandbox') . ')');
        $spenden = array_merge($spenden, $apiSpenden);

        // Lokale Transaktions-Tabelle (PayPal-Suche) für denselben Zeitraum aktuell halten
        $tage = min(max((int) ($cfg['paypal_import_tage'] ?? 3), 1), 31);
        $sync = $paypal->synchronisieren((new DateTime("-$tage days"))->setTime(0, 0), new DateTime('now'));
        Database::logAdd('OK', "PayPal-Sync: {$sync['gespeichert']} Zahlungen in lokaler Tabelle aktualisiert");
    } catch (\Exception $e) {
        Database::logAdd('ERROR', 'PayPal-API: ' . $e->getMessage());
    }
}

$gen = new Generator();
foreach ($spenden as $s) {
    $sid = Database::spendeErstellen($s);
    if ($sid) {
        $pdf = $gen->erstellen($s);
        if ($pdf) {
            Database::spendeUpdate($sid, ['pdf_pfad' => $pdf, 'status' => 'neu']);
            $hinweis = $testmodus ? '[TESTMODUS] ' : '';
            Database::kommentarAdd($sid, $hinweis . "Automatisch erstellt – Mail-ID: " . substr($s['mail_id'] ?? '', 0, 50), 'System', 'system');
            $anzahl++;
        } else {
            Database::spendeUpdate($sid, ['status' => 'fehler']);
        }
    }
}

// ── Schritt 3: Logs aufräumen (älter als 90 Tage) ──
Database::logsAufraumen(90);

// ── Schritt 3b: PayPal-Tabelle aufräumen (nur aktuelles Jahr + Vorjahr behalten) ──
$geloescht = Database::paypalTxAufraumen();
if ($geloescht > 0) {
    Database::logAdd('OK', "PayPal-Tabelle: $geloescht Transaktionen vor dem Vorjahr entfernt");
}

$dauer = round(microtime(true) - $startzeit, 2);
Database::logAdd('OK', implode(' | ', [
    '=== Job abgeschlossen ===',
    "PDFs: $anzahl",
    "Verschoben: {$verschoben['kopiert']}",
    "Übersprungen: {$verschoben['uebersprungen']}",
    "Fehler: {$verschoben['fehler']}",
    "Dauer: {$dauer}s",
    $testmodus ? '⚠ TESTMODUS' : '✓ Produktiv',
]));

// ── Schritt 4: Report-Mail an den Admin (abschaltbar in den Einstellungen) ──
$lauf = [
    'pdfs'          => $anzahl,
    'verschoben'    => $verschoben['kopiert'],
    'uebersprungen' => $verschoben['uebersprungen'],
    'fehler'        => $verschoben['fehler'],
    'dauer'         => $dauer,
];
if ($paypalAnzahl !== null) {
    $lauf['paypal'] = $paypalAnzahl;
}
(new Report($cfg))->senden($lauf);

echo date('Y-m-d H:i:s') . " – Job OK – $anzahl PDFs – {$dauer}s" . ($testmodus ? ' [TESTMODUS]' : '') . "\n";
