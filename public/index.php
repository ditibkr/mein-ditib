<?php
/**
 * Front-Controller – alle Requests laufen hier durch
 */
define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';

use App\DB\Database;
use App\Pdf\Generator;
use App\Pdf\Importer;
use App\Imap\MailReader;
use App\Imap\AnhangIndex;
use App\Mail\Sender;
use App\Report\Statistik;

// Session nur für Flash-Nachrichten (kein Auth-State)
session_name('sp_flash');
session_start();

$cfg = require ROOT . '/config/settings.php';

// Aktuell eingeloggter User (Cloudflare ZT E-Mail)
$currentUser = getCurrentUser();

// ── Auth via Cloudflare Zero Trust ──
// Cloudflare ZT setzt diesen Header nach erfolgreicher Authentifizierung.
// Ohne ZT (lokale Entwicklung) kann in settings.php ein Dev-User gesetzt werden.
function getCurrentUser(): string
{
    if (!empty($_SERVER['HTTP_CF_ACCESS_AUTHENTICATED_USER_EMAIL'])) {
        return $_SERVER['HTTP_CF_ACCESS_AUTHENTICATED_USER_EMAIL'];
    }
    $cfg = require ROOT . '/config/settings.php';
    return $cfg['dev_user'] ?? 'admin@lokal';
}

// Anzeigename aus Mapping (settings.php → 'user_namen'), sonst E-Mail
function getCurrentUserName(): string
{
    $email = getCurrentUser();
    $cfg   = require ROOT . '/config/settings.php';
    return $cfg['user_namen'][$email] ?? $email;
}

function requireLogin(): void
{
    // Mit Cloudflare ZT ist der User immer authentifiziert wenn er die App erreicht.
    // Diese Funktion bleibt als Sicherheitsnetz falls ZT umgangen wird.
    $user = getCurrentUser();
    if (empty($user)) {
        http_response_code(403);
        echo '403 – Kein Zugriff. Bitte über Cloudflare Zero Trust einloggen.';
        exit;
    }
}

// Ohne vollständige Anschrift darf keine Bescheinigung ausgestellt werden.
// PayPal liefert die Adresse nicht immer mit – dann fragen wir beim Spender nach.
function adresseFehlt(array $spende): bool
{
    return trim((string) ($spende['strasse'] ?? '')) === ''
        || trim((string) ($spende['plz'] ?? '')) === ''
        || trim((string) ($spende['ort'] ?? '')) === '';
}

function adresseFehlendeFelderText(array $spende): string
{
    $fehlt = [];
    if (trim((string) ($spende['strasse'] ?? '')) === '') $fehlt[] = 'Straße';
    if (trim((string) ($spende['plz'] ?? '')) === '')     $fehlt[] = 'PLZ';
    if (trim((string) ($spende['ort'] ?? '')) === '')     $fehlt[] = 'Ort';
    return implode(', ', $fehlt);
}

// Eine Adressanfrage lohnt nur, wenn wir den Spender überhaupt erreichen können und
// eine Bescheinigung noch aussteht. Nicht bei ausländischen Spendern (keine Bescheinigung
// zulässig) und nicht bei 'nicht_erforderlich' / 'sammelbescheinigt' – da wäre die Frage
// nach einer Bescheinigung widersprüchlich. Zum Anschreiben vorher reaktivieren.
function adresseAnfragbar(array $spende): bool
{
    return adresseFehlt($spende)
        && !empty($spende['email'])
        && empty($spende['auslaendisch'])
        && !in_array($spende['status'], ['versendet', 'erledigt', 'nicht_erforderlich', 'sammelbescheinigt'], true);
}

// ── Router ──
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Statische Dateien direkt
if (str_starts_with($uri, '/static/')) {
    $file = ROOT . $uri;
    if (file_exists($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mime = match ($ext) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'woff2' => 'font/woff2',
            default => 'text/plain'
        };
        header("Content-Type: $mime");
        readfile($file);
        exit;
    }
}

// Hilfsfunktionen
function render(string $tpl, array $vars = []): void
{
    $cfg = require ROOT . '/config/settings.php';
    extract($vars);
    require ROOT . "/templates/$tpl.php";
}

function jsonOut(array $data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Bescheinigung ist beim Spender – die PDF ist ab hier ein Archiv-Dokument
// und darf nicht mehr überschrieben oder gelöscht werden (Finanzamt-Nachweis).
function versendet(?array $spende): bool
{
    return in_array($spende['status'] ?? '', ['versendet', 'erledigt', 'sammelbescheinigt'], true);
}

function redirect(string $to): void
{
    header("Location: $to");
    exit;
}

// ── Routes ──
match (true) {

    // Logout: Cloudflare Zero Trust übernimmt den Logout
    $uri === '/logout' => (function () {
            $cfg = require ROOT . '/config/settings.php';
            $ztLogout = $cfg['cf_logout_url'] ?? '/';
            header("Location: $ztLogout");
            exit;
        })(),

    // Health
    $uri === '/health' => (function () {
            echo 'OK';
            exit;
        })(),

    // Dashboard
    $uri === '/' => (function () {
            requireLogin();
            $zaehler = Database::spendeZaehler();
            $letzte  = array_slice(Database::spendeAlle(), 0, 10);
            // Startansicht: laufender Monat (Logs haben auf dem Dashboard nichts verloren)
            $report  = (new Statistik())->auswerten('monat');
            render('dashboard', compact('zaehler', 'letzte', 'report'));
        })(),

    // ── Import bestehender PDF-Bescheinigungen ──
    $uri === '/import' => (function () {
            requireLogin();
            $eintraege = Database::get()->query(
                "SELECT i.*, s.id AS dup_id, s.vorname AS dup_vorname, s.nachname AS dup_nachname,
                        s.betrag AS dup_betrag, s.datum AS dup_datum
                 FROM import_pdf i
                 LEFT JOIN spenden s ON s.id = i.duplikat_id
                 WHERE i.status != 'verworfen'
                 ORDER BY i.id DESC"
            )->fetchAll();
            render('import', compact('eintraege'));
        })(),

    // Upload – mehrere Dateien auf einmal
    $uri === '/import/upload' && $method === 'POST' => (function () {
            requireLogin();
            $importer = new Importer();
            $ok = $fehler = 0;
            $meldungen = [];

            foreach ($_FILES['pdfs']['tmp_name'] ?? [] as $i => $tmp) {
                if (($_FILES['pdfs']['error'][$i] ?? 1) !== UPLOAD_ERR_OK) {
                    $fehler++;
                    continue;
                }
                $name = $_FILES['pdfs']['name'][$i];
                if (!preg_match('/\.pdf$/i', $name)) {
                    $meldungen[] = "$name: keine PDF";
                    $fehler++;
                    continue;
                }
                $r = $importer->aufnehmen($tmp, $name);
                if ($r['ok']) {
                    $ok++;
                } else {
                    $fehler++;
                    $meldungen[] = "$name: " . ($r['grund'] ?: 'nicht lesbar');
                }
            }

            Database::logAdd('OK', "PDF-Import: $ok eingelesen, $fehler mit Problemen", getCurrentUser());
            $_SESSION['flash'][] = [
                'type' => $fehler ? 'error' : 'success',
                'msg'  => "$ok eingelesen, $fehler mit Problemen" . ($meldungen ? ' – ' . implode('; ', array_slice($meldungen, 0, 3)) : ''),
            ];
            redirect('/import');
        })(),

    // Massenimport: alle PDFs aus import_eingang/ einlesen (in Blöcken)
    $uri === '/api/import/verzeichnis' && $method === 'POST' => (function () {
            requireLogin();
            jsonOut((new Importer())->verzeichnisEinlesen(100));
        })(),

    // Anhang-Index aus "Gesendete Objekte" aufbauen (Grundlage für den Mail-Abgleich)
    $uri === '/api/import/mailindex' && $method === 'POST' => (function () {
            requireLogin();
            set_time_limit(0);
            jsonOut((new AnhangIndex())->aufbauen('1-Jan-2024'));
        })(),

    // Staging-Einträge den gesendeten Mails zuordnen → E-Mail ergänzen
    $uri === '/api/import/mailabgleich' && $method === 'POST' => (function () {
            requireLogin();
            set_time_limit(0);
            jsonOut((new Importer())->mailAbgleich());
        })(),

    // Korrektur der ausgelesenen Daten (vor der Freigabe)
    (bool) preg_match('#^/api/import/(\d+)/bearbeiten$#', $uri, $mImpEdit) && $method === 'POST' => (function () use ($mImpEdit) {
            requireLogin();
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $felder = ['vorname', 'nachname', 'strasse', 'plz', 'ort', 'email', 'art', 'zahlungsweg', 'datum'];
            $set = [];
            $par = [':id' => (int) $mImpEdit[1]];
            foreach ($felder as $f) {
                if (isset($body[$f])) {
                    $set[] = "$f = :$f";
                    $par[":$f"] = trim((string) $body[$f]);
                }
            }
            if (isset($body['betrag'])) {
                $set[] = 'betrag = :betrag';
                $par[':betrag'] = (float) str_replace(',', '.', (string) $body['betrag']);
            }
            if (!$set) {
                jsonOut(['ok' => false, 'error' => 'Nichts zu speichern']);
            }
            $set[] = "status = 'geprueft'";
            Database::get()->prepare("UPDATE import_pdf SET " . implode(', ', $set) . " WHERE id = :id")->execute($par);
            jsonOut(['ok' => true]);
        })(),

    // Freigabe → Eintrag in der Spenderübersicht, mit dem Original-PDF
    (bool) preg_match('#^/api/import/(\d+)/uebernehmen$#', $uri, $mImpOk) && $method === 'POST' => (function () use ($mImpOk) {
            requireLogin();
            jsonOut((new Importer())->uebernehmen((int) $mImpOk[1]));
        })(),

    (bool) preg_match('#^/api/import/(\d+)/verwerfen$#', $uri, $mImpNo) && $method === 'POST' => (function () use ($mImpNo) {
            requireLogin();
            Database::get()->prepare("UPDATE import_pdf SET status = 'verworfen' WHERE id = ?")
                ->execute([(int) $mImpNo[1]]);
            jsonOut(['ok' => true]);
        })(),

    // PDF-Vorschau eines Import-Eintrags (Original, unverändert)
    (bool) preg_match('#^/import/(\d+)/pdf$#', $uri, $mImpPdf) => (function () use ($mImpPdf) {
            requireLogin();
            $st = Database::get()->prepare("SELECT pdf_pfad FROM import_pdf WHERE id = ?");
            $st->execute([(int) $mImpPdf[1]]);
            $pfad = $st->fetchColumn();
            if (!$pfad || !file_exists($pfad)) {
                http_response_code(404);
                echo 'PDF nicht gefunden';
                exit;
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($pfad) . '"');
            header('Cache-Control: no-store');
            readfile($pfad);
            exit;
        })(),

    // Guilloche-Testseite (temporär)
    $uri === '/guilloche-test' => (function () {
            requireLogin();
            $gen  = new \App\Pdf\Generator();
            $pfad = $gen->erstellen([
                'vorname' => 'Max', 'nachname' => 'Mustermann',
                'strasse' => 'Musterstraße 1', 'plz' => '47800', 'ort' => 'Krefeld',
                'betrag'  => '150.00', 'datum' => date('Y-m-d'),
                'art'     => 'Geldzuwendung für ' . date('Y'), 'email' => '',
            ]);
            if (!$pfad || !file_exists($pfad)) { http_response_code(500); echo 'Fehler'; exit; }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="guilloche_test.pdf"');
            header('Cache-Control: no-store');
            readfile($pfad);
            exit;
        })(),

    // Sammel-PDF: alle versendet-PDFs zusammenführen
    $uri === '/api/sammel-druck' => (function () {
            requireLogin();
            $rows = Database::get()
                ->query("SELECT pdf_pfad, vorname, nachname FROM spenden WHERE status='versendet' AND pdf_pfad IS NOT NULL AND pdf_pfad != '' ORDER BY datum, nachname")
                ->fetchAll(\PDO::FETCH_ASSOC);

            $valid = array_filter($rows, fn($r) => file_exists($r['pdf_pfad']));
            if (empty($valid)) { http_response_code(404); echo 'Keine PDFs gefunden'; exit; }

            $tmp = sys_get_temp_dir() . '/sammel_druck_' . time() . '.pdf';
            $paths = array_map(fn($r) => escapeshellarg($r['pdf_pfad']), $valid);
            $cmd = 'qpdf --empty --pages ' . implode(' ', $paths) . ' -- ' . escapeshellarg($tmp) . ' 2>&1';
            exec($cmd, $out, $rc);

            if ($rc !== 0 || !file_exists($tmp)) {
                http_response_code(500);
                echo 'Fehler beim Zusammenführen: ' . implode("\n", $out);
                exit;
            }

            $count = count($valid);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="sammel_druck_' . date('Ymd') . '_' . $count . 'x.pdf"');
            header('Content-Length: ' . filesize($tmp));
            header('Cache-Control: no-store');
            readfile($tmp);
            @unlink($tmp);
            exit;
        })(),

    // Sammel-Erledigt: alle versendet → erledigt
    $uri === '/api/sammel-erledigt' && $method === 'POST' => (function () {
            requireLogin();
            $stmt = Database::get()->prepare(
                "UPDATE spenden SET status='erledigt' WHERE status='versendet'"
            );
            $stmt->execute();
            $anzahl = $stmt->rowCount();
            Database::logAdd('OK', "Sammel-Erledigt: $anzahl Einträge von versendet → erledigt", getCurrentUser());
            jsonOut(['ok' => true, 'anzahl' => $anzahl]);
        })(),

    // API: Auswertung für die Dashboard-Diagramme
    $uri === '/api/report' => (function () {
            requireLogin();
            $gran = $_GET['gran'] ?? 'monat';
            if (!in_array($gran, ['woche', 'monat', 'jahr', 'frei'], true)) {
                $gran = 'monat';
            }
            $pruefe = fn (string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
            jsonOut((new Statistik())->auswerten(
                $gran,
                $pruefe($_GET['von'] ?? ''),
                $pruefe($_GET['bis'] ?? '')
            ));
        })(),

    // Spendenliste
    $uri === '/liste' => (function () {
            requireLogin();
            $status = $_GET['status'] ?? 'neu';
            $suche = $_GET['q'] ?? '';
            $sortMap = [
            'datum_desc' => 'erstellt_am DESC',
            'datum' => 'erstellt_am ASC',
            'name' => 'nachname ASC',
            'name_desc' => 'nachname DESC',
            'betrag' => 'betrag ASC',
            'betrag_desc' => 'betrag DESC',
            ];
            $sortKey = $_GET['sort'] ?? 'datum_desc';
            $sort = $sortMap[$sortKey] ?? 'erstellt_am DESC';
            $datumVon = $_GET['datum_von'] ?? '';
            $datumBis = $_GET['datum_bis'] ?? '';
            $spenden = Database::spendeAlle($status, $suche, $sort, $datumVon, $datumBis);
            $zaehler = Database::spendeZaehler();
            render('liste', compact('spenden', 'zaehler', 'status', 'suche', 'sortKey'));
        })(),

    // Manuell erstellen
    $uri === '/manuell' && $method === 'GET' => (function () {
            requireLogin();
            render('manuell');
        })(),

    $uri === '/manuell' && $method === 'POST' => (function () {
            requireLogin();
            $artRaw = $_POST['art'] ?? 'geldspende';
            // <input type="date"> liefert ISO-Format "2026-03-06" → umwandeln in "06.03.2026"
            $datumRaw = $_POST['datum'] ?? '';
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datumRaw, $dm)) {
                $datum = "{$dm[3]}.{$dm[2]}.{$dm[1]}";
            } else {
                $datum = $datumRaw ?: date('d.m.Y');
            }
            $jahrPost = (int)($_POST['kalenderjahr'] ?? 0);
            $jahr = ($jahrPost >= 2000 && $jahrPost <= 2099) ? (string)$jahrPost : (substr($datum, 6, 4) ?: date('Y'));
    
            if ($artRaw === 'mitglied') {
                $art = "Mitgliedsbeitrag für $jahr";
            } elseif ($artRaw === 'sammel') {
                $artSammel = ($_POST['sammel_art'] ?? 'geld') === 'geld' ? 'Geldzuwendung' : 'Mitgliedsbeitrag';
                $art = "$artSammel (Sammelbescheinigung) für $jahr";
                // Gesamt aus Einzelbeträgen
                $betraege = array_map('floatval', $_POST['sammel_betrag'] ?? []);
                $_POST['betrag'] = array_sum($betraege);
                $_POST['sammel_ids'] = json_encode(array_map(null, $betraege, $_POST['sammel_datum'] ?? []));
            } else {
                $art = "Geldzuwendung für $jahr";
            }

            $spende = [
            'vorname' => trim($_POST['vorname'] ?? ''),
            'nachname' => trim($_POST['nachname'] ?? ''),
            'strasse' => trim($_POST['strasse'] ?? ''),
            'plz' => trim($_POST['plz'] ?? ''),
            'ort' => trim($_POST['ort'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'betrag' => (float) str_replace(',', '.', $_POST['betrag'] ?? '0'),
            'datum' => $datum,
            'art' => $art,
            'zahlungsweg' => $_POST['zahlungsweg'] ?? 'Überweisung',
            'mitgliedsnr' => $_POST['mitgliedsnr'] ?? '',
            'zeitraum_von' => $_POST['zeitraum_von'] ?? '',
            'zeitraum_bis' => $_POST['zeitraum_bis'] ?? '',
            'sammel_ids' => $_POST['sammel_ids'] ?? '',
            'quelle' => 'manuell',
            'status' => 'neu',
            'kommentar' => trim($_POST['kommentar'] ?? ''),
            ];

            $sid = Database::spendeErstellen($spende);
            if ($sid) {
                $gen = new Generator();
                $pdf = $gen->erstellen($spende);
                if ($pdf) {
                    Database::spendeUpdate($sid, ['pdf_pfad' => $pdf]);
                    Database::kommentarAdd($sid, 'Manuell erstellt', getCurrentUser(), 'system');
                    if ($spende['kommentar'])
                        Database::kommentarAdd($sid, $spende['kommentar']);
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => '✓ PDF erstellt: ' . basename($pdf)];
                } else {
                    Database::spendeUpdate($sid, ['status' => 'fehler']);
                    $_SESSION['flash'] = ['type' => 'error', 'msg' => '⚠ PDF-Erstellung fehlgeschlagen'];
                }
                redirect("/spende/$sid");
            }
            redirect('/manuell');
        })(),

    // Versand-Freigabe
    $uri === '/versand' => (function () {
            requireLogin();
            $spenden = Database::spendeAlle('freigegeben');
            render('versand', compact('spenden'));
        })(),

    // PayPal-Transaktionssuche
    $uri === '/paypal' => (function () {
            requireLogin();
            $suche = trim($_GET['suche'] ?? '');
            $von   = trim($_GET['von'] ?? '');
            $bis   = trim($_GET['bis'] ?? '');
            // Standardansicht ohne Filter: nur der heutige Tag – Rest über Suche/Zeitraum
            $standard = ($suche === '' && $von === '' && $bis === '');
            if ($standard) {
                $von = $bis = date('Y-m-d');
            }
            $transaktionen = Database::paypalTxSuchen($suche, $von, $bis);
            $txInfo = Database::paypalTxInfo();
            render('paypal', compact('transaktionen', 'txInfo', 'suche', 'von', 'bis', 'standard'));
        })(),

    // PayPal-Summen pro Spender (Report für Jahres-/Sammelbescheinigungen)
    $uri === '/paypal-summen' => (function () {
            requireLogin();
            $suche = trim($_GET['suche'] ?? '');
            $von   = trim($_GET['von'] ?? '');
            $bis   = trim($_GET['bis'] ?? '');
            // Standardansicht ohne Filter: aktuelles Jahr (01.01. bis heute)
            $standard = ($suche === '' && $von === '' && $bis === '');
            if ($standard) {
                $von = date('Y-01-01');
                $bis = date('Y-m-d');
            }
            $gruppen = Database::paypalSummenSuchen($suche, $von, $bis);
            $txInfo  = Database::paypalTxInfo();
            render('paypal_summen', compact('gruppen', 'txInfo', 'suche', 'von', 'bis', 'standard'));
        })(),

    // Seite: Anonyme Zahlungen
    $uri === '/paypal-anonym' => (function () {
            requireLogin();
            $db  = Database::get();
            $alle = $db->query(
                "SELECT pt.* FROM paypal_transaktionen pt
                 LEFT JOIN spenden s ON s.mail_id = 'paypal-' || pt.tx_id
                 WHERE pt.vorname = 'Anonym' AND s.id IS NULL AND pt.status = 'S'
                 ORDER BY pt.datum DESC"
            )->fetchAll();

            $daten = ['gebuehr_spende' => [], 'gebuehr_sonstige' => [], 'ohne_gebuehr' => []];
            foreach ($alle as $t) {
                $hatGebuehr = (float) $t['gebuehr'] > 0;
                $hatSpende  = mb_stripos($t['betreff'] ?? '', 'spende') !== false;
                if ($hatGebuehr && $hatSpende)  $daten['gebuehr_spende'][]    = $t;
                elseif ($hatGebuehr)            $daten['gebuehr_sonstige'][]  = $t;
                else                            $daten['ohne_gebuehr'][]      = $t;
            }
            $summen = ['anzahl' => count($alle), 'gesamt' => array_sum(array_column($alle, 'brutto'))];
            render('paypal_anonym', compact('daten', 'summen'));
        })(),

    // API: Anonyme Gruppe importieren
    $uri === '/api/paypal/anonym/importieren' && $method === 'POST' => (function () {
            requireLogin();
            $gruppe  = trim($_POST['gruppe'] ?? '');
            $erlaubt = ['gebuehr_spende', 'gebuehr_sonstige', 'ohne_gebuehr'];
            if (!in_array($gruppe, $erlaubt, true)) {
                echo json_encode(['ok' => false, 'error' => 'Unbekannte Gruppe']);
                exit;
            }
            $db   = Database::get();
            $alle = $db->query(
                "SELECT pt.* FROM paypal_transaktionen pt
                 LEFT JOIN spenden s ON s.mail_id = 'paypal-' || pt.tx_id
                 WHERE pt.vorname = 'Anonym' AND s.id IS NULL AND pt.status = 'S'"
            )->fetchAll();

            $eintraege = [];
            foreach ($alle as $t) {
                $hatGebuehr = (float) $t['gebuehr'] > 0;
                $hatSpende  = mb_stripos($t['betreff'] ?? '', 'spende') !== false;
                $key = match(true) {
                    $hatGebuehr && $hatSpende => 'gebuehr_spende',
                    $hatGebuehr              => 'gebuehr_sonstige',
                    default                  => 'ohne_gebuehr',
                };
                if ($key === $gruppe) $eintraege[] = $t;
            }

            $importiert = 0;
            $summe      = 0.0;
            foreach ($eintraege as $t) {
                $datum = date('d.m.Y');
                if (!empty($t['datum'])) {
                    try {
                        $datum = (new DateTime($t['datum']))
                            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                            ->format('d.m.Y');
                    } catch (Exception) {}
                }
                $jahr = substr($datum, -4);
                Database::spendeErstellen([
                    'vorname'      => 'Anonym',
                    'nachname'     => 'Anonym',
                    'email'        => trim($t['email']),
                    'strasse'      => '',
                    'plz'          => '',
                    'ort'          => '',
                    'land'         => '',
                    'auslaendisch' => 0,
                    'betrag'       => (float) $t['brutto'],
                    'datum'        => $datum,
                    'art'          => "Geldzuwendung für $jahr",
                    'zahlungsweg'  => 'PayPal',
                    'quelle'       => 'paypal_api',
                    'status'       => 'nicht_erforderlich',
                    'mail_id'      => 'paypal-' . $t['tx_id'],
                    'mail_body'    => '',
                ]);
                $summe += (float) $t['brutto'];
                $importiert++;
            }
            Database::logAdd('OK', "Anonyme Gruppe '$gruppe': $importiert Einträge importiert (" . number_format($summe, 2, ',', '.') . " €)");
            echo json_encode([
                'ok'         => true,
                'importiert' => $importiert,
                'summe'      => number_format($summe, 2, ',', '.') . ' €',
            ]);
            exit;
        })(),

    // API: Einzeltransaktionen eines Spenders (für aufklappbare Detailansicht im Summen-Report)
    $uri === '/api/paypal/spender-tx' && $method === 'GET' => (function () {
            requireLogin();
            $tx = Database::paypalTxFuerSpender(
                trim($_GET['email'] ?? ''),
                trim($_GET['vorname'] ?? ''),
                trim($_GET['nachname'] ?? ''),
                trim($_GET['von'] ?? ''),
                trim($_GET['bis'] ?? '')
            );
            jsonOut(['ok' => true, 'transaktionen' => $tx]);
        })(),

    // Logs
    $uri === '/logs' => (function () {
            requireLogin();
            $logs = Database::logsAlle(500);
            render('logs', compact('logs'));
        })(),

    // Einstellungen (direkte Anzeige der config-Datei)
    $uri === '/einstellungen' => (function () {
            requireLogin();
            render('einstellungen');
        })(),

    // Einstellungen (direkte Anzeige der config-Datei)
    $uri === '/test_parser' => (function () {
            requireLogin();
            render('test_parser');
        })(),

    // Spende Detail
    (bool) preg_match('#^/spende/(\d+)$#', $uri, $mDetail) && $method === 'GET' => (function () use ($mDetail) {
            requireLogin();
            $spende = Database::spendeById((int) $mDetail[1]);
            if (!$spende) {
                http_response_code(404);
                echo 'Nicht gefunden';
                exit; }
            $kommentare = Database::kommentareBySpende((int) $mDetail[1]);
            render('detail', compact('spende', 'kommentare'));
        })(),

    // API: Spende bearbeiten + PDF neu erstellen
    (bool) preg_match('#^/api/spende/(\d+)/bearbeiten$#', $uri, $mBearbeiten) && $method === 'POST' => (function () use ($mBearbeiten) {
            requireLogin();
            $sid  = (int) $mBearbeiten[1];
            $body = json_decode(file_get_contents('php://input'), true);

            $felder = ['vorname', 'nachname', 'strasse', 'plz', 'ort', 'email', 'art', 'zahlungsweg'];
            $update = [];
            foreach ($felder as $f) {
                if (isset($body[$f])) $update[$f] = trim((string) $body[$f]);
            }
            if (isset($body['betrag']))
                $update['betrag'] = (float) $body['betrag'];
            if (isset($body['datum']))
                $update['datum'] = trim($body['datum']);

            if (empty($update['vorname']) || empty($update['nachname']))
                jsonOut(['ok' => false, 'error' => 'Vor- und Nachname sind Pflichtfelder']);

            $vorher = Database::spendeById($sid);
            Database::spendeUpdate($sid, $update);
            $spende = Database::spendeById($sid);

            // ── Archivschutz ──
            // Eine bereits versendete Bescheinigung wird NIE neu erzeugt. Sie muss
            // bei einer Finanzamtsprüfung exakt so nachweisbar sein, wie sie beim
            // Spender angekommen ist. Die Daten werden gespeichert, die PDF bleibt.
            if (versendet($vorher)) {
                Database::kommentarAdd($sid, 'Daten bearbeitet – PDF NICHT neu erstellt (bereits versendet, Archiv unverändert)', getCurrentUser(), 'system');
                Database::logAdd('WARN', "Spende #$sid bearbeitet, PDF unverändert (Status: {$vorher['status']})");
                jsonOut([
                    'ok'      => true,
                    'hinweis' => 'Daten gespeichert. Die versendete PDF bleibt unverändert (Nachweis fürs Finanzamt).',
                ]);
            }

            // PDF neu generieren – ersetzt die bisherige Datei statt eine Kopie anzulegen
            $alt    = $spende['pdf_pfad'] ?? null;
            $gen    = new Generator();
            $pdf    = $gen->erstellen($spende, $alt);
            if ($pdf) {
                Database::spendeUpdate($sid, ['pdf_pfad' => $pdf]);
                Database::kommentarAdd($sid, 'Manuell bearbeitet & PDF neu erstellt', getCurrentUser(), 'system');
                Database::logAdd('OK', "Spende #$sid manuell bearbeitet: {$update['vorname']} {$update['nachname']}", getCurrentUser());
                jsonOut(['ok' => true]);
            } else {
                Database::logAdd('ERROR', "Spende #$sid: Bearbeitung OK, PDF-Generierung fehlgeschlagen", getCurrentUser());
                jsonOut(['ok' => false, 'error' => 'Daten gespeichert, aber PDF-Erstellung fehlgeschlagen']);
            }
        })(),

    // API: Freigeben
    (bool) preg_match('#^/api/spende/(\d+)/freigeben$#', $uri, $mFreigeben) && $method === 'POST' => (function () use ($mFreigeben) {
            requireLogin();
            $sid = (int) $mFreigeben[1];
            Database::spendeUpdate($sid, ['status' => 'freigegeben']);
            Database::kommentarAdd($sid, 'Freigegeben durch Admin', getCurrentUser(), 'system');
            Database::logAdd('OK', "Spende #$sid freigegeben", getCurrentUser());
            jsonOut(['ok' => true]);
        })(),

    // API: Versenden
    (bool) preg_match('#^/api/spende/(\d+)/versenden$#', $uri, $mVersenden) && $method === 'POST' => (function () use ($mVersenden) {
            requireLogin();
            $sid = (int) $mVersenden[1];
            $spende = Database::spendeById($sid);
            if (!$spende)
                jsonOut(['ok' => false, 'error' => 'Nicht gefunden']);
            $pdf = $spende['pdf_pfad'] ?? '';
            if (!$pdf || !file_exists($pdf))
                jsonOut(['ok' => false, 'error' => 'PDF nicht gefunden']);
            $cfg2     = require ROOT . '/config/settings.php';
            $istTest  = $cfg2['testmodus'] ?? false;
            $reqBody  = json_decode(file_get_contents('php://input'), true) ?? [];
            $sprache  = in_array($reqBody['sprache'] ?? '', ['tr', 'de']) ? $reqBody['sprache'] : 'tr';
            $sender   = new Sender();
            $result   = $sender->senden($spende, $pdf, $sprache);

            // Duplikat erkannt → Status auf versendet setzen, Meldung zurückgeben
            if ($result['duplikat'] ?? false) {
                Database::spendeUpdate($sid, ['status' => 'versendet']);
                Database::kommentarAdd($sid, "Duplikat erkannt: {$result['info']} – kein erneuter Versand", getCurrentUser(), 'system');
                jsonOut(['ok' => false, 'duplikat' => true, 'info' => $result['info']]);
            }

            if ($result['ok']) {
                $now          = date('d.m.Y H:i');
                $spracheLabel = $sprache === 'de' ? 'Deutsch' : 'Türkisch';
                if ($istTest) {
                    $testMail = $cfg2['test_email'] ?? 'test';
                    Database::kommentarAdd($sid, "TESTMODUS: Mail an $testMail (Original: {$spende['email']}) am $now", getCurrentUser(), 'system');
                    // Status bleibt 'freigegeben' – Prod-Versand noch möglich
                } else {
                    Database::spendeUpdate($sid, ['status' => 'versendet', 'versendet_am' => $now]);
                    Database::kommentarAdd($sid, "Mail versendet an {$spende['email']} am $now (Sprache: $spracheLabel)", getCurrentUser(), 'system');
                }
            }
            jsonOut(['ok' => $result['ok'], 'error' => $result['info'] ?? '']);
        })(),

    // API: Postversand – Status auf versendet ohne Mail
    (bool) preg_match('#^/api/spende/(\d+)/postversand$#', $uri, $mPost) && $method === 'POST' => (function () use ($mPost) {
            requireLogin();
            $sid = (int) $mPost[1];
            $spende = Database::spendeById($sid);
            if (!$spende) jsonOut(['ok' => false, 'error' => 'Nicht gefunden']);
            $now = date('d.m.Y H:i');
            Database::spendeUpdate($sid, ['status' => 'versendet', 'versendet_am' => $now]);
            Database::kommentarAdd($sid, "Bescheinigung per Post versendet am $now", getCurrentUser(), 'system');
            Database::logAdd('OK', "Spende #$sid per Post versendet", getCurrentUser());
            jsonOut(['ok' => true]);
        })(),

    // API: Adressanfrage – Spender ohne Anschrift fragen, ob er eine Bescheinigung wünscht.
    // Läuft nur auf Klick, nie automatisch. Ändert den Status nicht.
    (bool) preg_match('#^/api/spende/(\d+)/adresse-anfrage$#', $uri, $mAdr) && $method === 'POST' => (function () use ($mAdr) {
            requireLogin();
            $sid    = (int) $mAdr[1];
            $spende = Database::spendeById($sid);
            if (!$spende) jsonOut(['ok' => false, 'error' => 'Nicht gefunden']);
            if (!adresseFehlt($spende)) jsonOut(['ok' => false, 'error' => 'Adresse ist bereits vollständig hinterlegt']);
            if (empty($spende['email'])) jsonOut(['ok' => false, 'error' => 'Keine E-Mail-Adresse – Nachfrage nicht möglich']);

            $reqBody = json_decode(file_get_contents('php://input'), true) ?? [];
            $sprache = in_array($reqBody['sprache'] ?? '', ['tr', 'de']) ? $reqBody['sprache'] : 'tr';

            $result = (new Sender())->adresseAnfragen($spende, $sprache);
            if ($result['ok']) {
                $now  = date('d.m.Y H:i');
                $lang = $sprache === 'de' ? 'Deutsch' : 'Türkisch';
                Database::kommentarAdd($sid, "Adressanfrage per Mail an {$spende['email']} versendet am $now ($lang)", getCurrentUser(), 'system');
                Database::get()->prepare('UPDATE spenden SET adresse_angefragt = 1 WHERE id = ?')->execute([$sid]);
            }
            jsonOut(['ok' => $result['ok'], 'error' => $result['info'] ?? '']);
        })(),

    // API: Adresse-Flag manuell togglen
    (bool) preg_match('#^/api/spende/(\d+)/adresse-flag$#', $uri, $mAdrF) && $method === 'POST' => (function () use ($mAdrF) {
            requireLogin();
            $sid    = (int) $mAdrF[1];
            $spende = Database::spendeById($sid);
            if (!$spende) jsonOut(['ok' => false, 'error' => 'Nicht gefunden']);
            $neu = (int)$spende['adresse_angefragt'] === 1 ? 0 : 1;
            Database::get()->prepare('UPDATE spenden SET adresse_angefragt = ? WHERE id = ?')->execute([$neu, $sid]);
            jsonOut(['ok' => true, 'aktiv' => $neu]);
        })(),

    // API: Vorschau der Adressanfrage für eine konkrete Spende
    (bool) preg_match('#^/api/spende/(\d+)/adresse-vorschau$#', $uri, $mAdrV) && $method === 'GET' => (function () use ($mAdrV) {
            requireLogin();
            $sid    = (int) $mAdrV[1];
            $spende = Database::spendeById($sid);
            if (!$spende) { http_response_code(404); echo 'Nicht gefunden'; exit; }
            $cfg2    = require ROOT . '/config/settings.php';
            $sprache = in_array($_GET['sprache'] ?? '', ['tr', 'de']) ? $_GET['sprache'] : 'tr';
            if ($sprache === 'de') {
                $subject  = $cfg2['smtp_subject_adresse_de'] ?? '';
                $mailtext = $cfg2['smtp_mailtext_adresse_de'] ?? '';
            } else {
                $subject  = $cfg2['smtp_subject_adresse_tr'] ?? '';
                $mailtext = $cfg2['smtp_mailtext_adresse'] ?? '';
            }
            $logoPfad = ROOT . '/static/img/logoDitib.png';
            $logoSrc  = file_exists($logoPfad)
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPfad))
                : '';
            $body = str_replace(
                ['{name}', '{betrag}', '{datum}', 'cid:ditib_logo'],
                [
                    htmlspecialchars(trim($spende['vorname'] . ' ' . $spende['nachname'])),
                    number_format((float) $spende['betrag'], 2, ',', '.') . ' €',
                    htmlspecialchars($spende['datum'] ?? ''),
                    $logoSrc,
                ],
                $mailtext
            );
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>body{margin:0;padding:20px;background:#e8e8e8;font-family:Arial,sans-serif}
.meta{max-width:600px;margin:0 auto 12px;background:#fff;border-radius:8px;padding:14px 18px;font-size:13px;border:1px solid #ddd}
.meta b{color:#555;display:inline-block;width:80px}</style></head><body>
<div class="meta">
  <div><b>An:</b> ' . htmlspecialchars($spende['email']) . '</div>
  <div style="margin-top:6px"><b>Betreff:</b> ' . htmlspecialchars($subject) . '</div>
</div>' . $body . '</body></html>';
            exit;
        })(),

    // API: Erneut versenden (überspringt Duplikat-Prüfung)
    (bool) preg_match('#^/api/spende/(\d+)/erneut-senden$#', $uri, $mErneutSenden) && $method === 'POST' => (function () use ($mErneutSenden) {
            requireLogin();
            $sid    = (int) $mErneutSenden[1];
            $spende = Database::spendeById($sid);
            if (!$spende) jsonOut(['ok' => false, 'error' => 'Nicht gefunden']);
            $pdf = $spende['pdf_pfad'] ?? '';
            if (!$pdf || !file_exists($pdf)) jsonOut(['ok' => false, 'error' => 'PDF nicht gefunden']);
            $cfg2    = require ROOT . '/config/settings.php';
            $istTest = $cfg2['testmodus'] ?? false;
            $reqBody = json_decode(file_get_contents('php://input'), true) ?? [];
            $sprache = in_array($reqBody['sprache'] ?? '', ['tr', 'de']) ? $reqBody['sprache'] : 'tr';
            $sender  = new Sender();
            $result  = $sender->senden($spende, $pdf, $sprache, true);
            if ($result['ok']) {
                $now          = date('d.m.Y H:i');
                $spracheLabel = $sprache === 'de' ? 'Deutsch' : 'Türkisch';
                if ($istTest) {
                    $testMail = $cfg2['test_email'] ?? 'test';
                    Database::kommentarAdd($sid, "TESTMODUS: Erneut an $testMail am $now", getCurrentUser(), 'system');
                } else {
                    Database::spendeUpdate($sid, ['status' => 'versendet', 'versendet_am' => $now]);
                    Database::kommentarAdd($sid, "Erneut versendet an {$spende['email']} am $now (Sprache: $spracheLabel)", getCurrentUser(), 'system');
                }
            }
            jsonOut(['ok' => $result['ok'], 'error' => $result['info'] ?? '']);
        })(),

    // API: Kommentar
    (bool) preg_match('#^/api/spende/(\d+)/kommentar$#', $uri, $mKommentar) && $method === 'POST' => (function () use ($mKommentar) {
            requireLogin();
            $sid = (int) $mKommentar[1];
            $body = json_decode(file_get_contents('php://input'), true);
            $text = trim($body['text'] ?? '');
            if ($text)
                Database::kommentarAdd($sid, $text, getCurrentUser());
            jsonOut(Database::kommentareBySpende($sid));
        })(),

    // PDF Vorschau / Download
    (bool) preg_match('#^/spende/(\d+)/pdf$#', $uri, $mPdf) => (function () use ($mPdf) {
            requireLogin();
            $cfg = require ROOT . '/config/settings.php';
            $spende = Database::spendeById((int) $mPdf[1]);
            $stored = $spende['pdf_pfad'] ?? '';
            $outDir = rtrim($cfg['output_dir'], '/');

            // Auflösung: 1) gespeicherter Pfad direkt, 2) Dateiname im output-Ordner
            $pdf = null;
            if ($stored && file_exists($stored)) {
                $pdf = $stored;
            } elseif ($stored) {
                $candidate = $outDir . '/' . basename($stored);
                if (file_exists($candidate))
                    $pdf = $candidate;
            }

            if (!$pdf) {
                http_response_code(404);
                echo 'PDF nicht gefunden.'
                . ' Gespeichert: ' . htmlspecialchars($stored ?: 'leer')
                . ' | Gesucht in: ' . htmlspecialchars($outDir);
                exit;
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($pdf) . '"');
            // Kein Caching: nach einer Korrektur muss die Vorschau die neue PDF zeigen
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            readfile($pdf);
            exit;
        })(),

    // API: Spende löschen
    (bool) preg_match('#^/api/spende/(\d+)/loeschen$#', $uri, $mLoeschen) && $method === 'POST' => (function () use ($mLoeschen) {
            requireLogin();
            $sid = (int) $mLoeschen[1];
            Database::spendeLoeschen($sid);
            Database::logAdd('WARN', "Spende #$sid gelöscht", getCurrentUser());
            jsonOut(['ok' => true]);
        })(),

    // API: Alles zurücksetzen (Produktions-Reset)
    $uri === '/api/reset' && $method === 'POST' => (function () {
            requireLogin();
            $body = json_decode(file_get_contents('php://input'), true);
            // Sicherheits-Bestätigung erforderlich
            if (($body['bestaetigung'] ?? '') !== 'RESET_BESTAETIGT') {
                jsonOut(['ok' => false, 'error' => 'Bestätigung fehlt']);
            }
            Database::allesZuruecksetzen();
            Database::logAdd('WARN', 'Kompletter Reset durchgeführt durch Admin', getCurrentUser());
            jsonOut(['ok' => true]);
        })(),

    // API: Eingang importieren (aus spenden-eingang Ordner)
    $uri === '/api/eingang/verarbeiten' && $method === 'POST' => (function () use ($cfg) {
            requireLogin();
            if (!($cfg['imap_verarbeitung_aktiv'] ?? true)) {
                jsonOut(['ok' => false, 'error' => 'Mail-Parsing ist in den Einstellungen deaktiviert – Spenden kommen nur über die PayPal-API']);
            }
            $reader = new MailReader();
            $spenden = $reader->verarbeitenEingang();
            $gen = new Generator();
            $erstellt = 0;
            $fehler = 0;
            foreach ($spenden as $s) {
                $sid = Database::spendeErstellen($s);
                if ($sid) {
                    $pdf = $gen->erstellen($s);
                    if ($pdf) {
                        Database::spendeUpdate($sid, ['pdf_pfad' => $pdf, 'status' => 'neu']);
                        Database::kommentarAdd($sid, "Aus spenden-eingang importiert – Mail-ID: " . substr($s['mail_id'] ?? '', 0, 50), getCurrentUser(), 'system');
                        $erstellt++;
                    } else {
                        Database::spendeUpdate($sid, ['status' => 'fehler']);
                        $fehler++;
                    }
                }
            }
            Database::logAdd('OK', "Eingang-Import: $erstellt erstellt, $fehler Fehler", getCurrentUser());
            jsonOut(['ok' => true, 'erstellt' => $erstellt, 'fehler' => $fehler, 'gefunden' => count($spenden)]);
        })(),

    // API: Manueller Job (synchron, mit Ergebnis-Rückmeldung)
    // Verhält sich wie der Nacht-Cron: Mail-Parsing und PayPal-API-Import
    // laufen nur, wenn sie in den Einstellungen aktiviert sind.
    $uri === '/api/job/manuell' && $method === 'POST' => (function () use ($cfg) {
            requireLogin();
            $gen = new Generator();

            // ── Schritt 1: Mails IMMER aus der INBOX holen (auch im PayPal-API-Betrieb) ──
            $reader     = new MailReader();
            $verschoben = $reader->posteingang_kopieren();

            // ── Schritt 2: Mails verarbeiten (abschaltbar) ──
            $spenden = [];
            if ($cfg['imap_verarbeitung_aktiv'] ?? true) {
                $spenden = $reader->verarbeitenEingang();
            } else {
                // Kein Parsing → Mails nur archivieren, damit spenden-eingang nicht voll läuft
                $archiv = $reader->eingangVerschieben();
                Database::logAdd('INFO', "Manueller Job: Mail-Parsing deaktiviert – {$archiv['verschoben']} Mails archiviert, Spenden kommen über die PayPal-API", getCurrentUser());
            }

            // ── PayPal-API-Import (falls in den Einstellungen aktiviert) ──
            $apiNeu = 0;
            if (!empty($cfg['paypal_api_aktiv'])) {
                try {
                    $paypal     = new App\PayPal\ApiClient($cfg);
                    $apiSpenden = $paypal->spendenAbrufen((int) ($cfg['paypal_import_tage'] ?? 3));
                    $apiNeu     = count($apiSpenden);
                    Database::logAdd('OK', "Manueller Job – PayPal-API: $apiNeu neue Transaktionen (" . ($cfg['paypal_mode'] ?? 'sandbox') . ')');
                    $spenden = array_merge($spenden, $apiSpenden);

                    // Lokale Transaktions-Tabelle (PayPal-Suche) aktuell halten
                    $tage = min(max((int) ($cfg['paypal_import_tage'] ?? 3), 1), 31);
                    $paypal->synchronisieren((new DateTime("-$tage days"))->setTime(0, 0), new DateTime('now'));
                } catch (\Exception $e) {
                    Database::logAdd('ERROR', 'Manueller Job – PayPal-API: ' . $e->getMessage());
                }
            }

            $erstellt = 0;
            $fehler   = 0;
            foreach ($spenden as $s) {
                $sid = Database::spendeErstellen($s);
                if ($sid) {
                    $pdf = $gen->erstellen($s);
                    if ($pdf) {
                        Database::spendeUpdate($sid, ['pdf_pfad' => $pdf, 'status' => 'neu']);
                        Database::kommentarAdd($sid, "Manueller Job – Mail-ID: " . substr($s['mail_id'] ?? '', 0, 50), getCurrentUser(), 'system');
                        $erstellt++;
                    } else {
                        Database::spendeUpdate($sid, ['status' => 'fehler']);
                        $fehler++;
                    }
                }
            }

            Database::logAdd('OK', "Manueller Job: {$verschoben['kopiert']} verschoben, $apiNeu per API, $erstellt PDFs, $fehler Fehler", getCurrentUser());
            jsonOut(['ok' => true, 'verschoben' => $verschoben['kopiert'], 'api' => $apiNeu, 'erstellt' => $erstellt, 'fehler' => $fehler]);
        })(),

    // API: Job starten (Nacht-Cron, Hintergrund – bleibt für cron_job.php)
    $uri === '/api/job/starten' && $method === 'POST' => (function () {
            requireLogin();
            exec('php ' . ROOT . '/src/cron_job.php > /dev/null 2>&1 &');
            jsonOut(['ok' => true, 'msg' => 'Job gestartet']);
        })(),

    // Hilfsfunktion: Logo als Data-URI für Browser-Vorschau
    // (PHPMailer verwendet addEmbeddedImage; Browser brauchen Data-URI)
    // API: Template-Vorschau aus Einstellungen (ohne Spende)
    $uri === '/api/spende/vorschau-template' && $method === 'GET' => (function () {
            requireLogin();
            $cfg2    = require ROOT . '/config/settings.php';
            $sprache = in_array($_GET['sprache'] ?? '', ['tr', 'de']) ? $_GET['sprache'] : 'tr';
            // typ=adresse → Vorschau der Adressanfrage statt des Begleittexts zum Versand
            $adresse = ($_GET['typ'] ?? '') === 'adresse';
            if ($adresse) {
                $subject  = $cfg2[$sprache === 'de' ? 'smtp_subject_adresse_de' : 'smtp_subject_adresse_tr'] ?? '';
                $mailtext = $cfg2[$sprache === 'de' ? 'smtp_mailtext_adresse_de' : 'smtp_mailtext_adresse'] ?? '';
            } elseif ($sprache === 'de') {
                $subject  = $cfg2['smtp_subject_de'] ?? '';
                $mailtext = $cfg2['smtp_mailtext_de'] ?? $cfg2['smtp_mailtext'] ?? '';
            } else {
                $subject  = $cfg2['smtp_subject_tr'] ?? '';
                $mailtext = $cfg2['smtp_mailtext'] ?? '';
            }
            $logoPfad = ROOT . '/static/img/logoDitib.png';
            $logoSrc  = file_exists($logoPfad)
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPfad))
                : '';
            $body = str_replace(
                ['{name}', '{betrag}', '{datum}', 'cid:ditib_logo'],
                ['Max Mustermann', '50,00 €', date('d.m.Y'), $logoSrc],
                $mailtext
            );
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
<style>body{margin:0;padding:20px;background:#e8e8e8;font-family:Arial,sans-serif}
.meta{max-width:600px;margin:0 auto 12px;background:#fff;border-radius:8px;padding:14px 18px;font-size:13px;border:1px solid #ddd}
.meta b{color:#555;display:inline-block;width:80px}</style></head><body>
<div class="meta">
  <div><b>Betreff:</b> ' . htmlspecialchars($subject) . '</div>
  <div style="margin-top:6px;color:#888;font-size:11px">Beispiel-Empfänger: Max Mustermann</div>
</div>' . $body . '</body></html>';
            exit;
        })(),

    // API: Mail-Vorschau für konkrete Spende
    (bool) preg_match('#^/api/spende/(\d+)/vorschau$#', $uri, $mVorschau) && $method === 'GET' => (function () use ($mVorschau) {
            requireLogin();
            $sid    = (int) $mVorschau[1];
            $spende = Database::spendeById($sid);
            if (!$spende) { http_response_code(404); echo 'Nicht gefunden'; exit; }
            $cfg2    = require ROOT . '/config/settings.php';
            $sprache = in_array($_GET['sprache'] ?? '', ['tr', 'de']) ? $_GET['sprache'] : 'tr';
            $name    = htmlspecialchars(trim($spende['vorname'] . ' ' . $spende['nachname']));
            if ($sprache === 'de') {
                $subject  = $cfg2['smtp_subject_de'] ?? '';
                $mailtext = $cfg2['smtp_mailtext_de'] ?? $cfg2['smtp_mailtext'] ?? '';
            } else {
                $subject  = $cfg2['smtp_subject_tr'] ?? '';
                $mailtext = $cfg2['smtp_mailtext'] ?? '';
            }
            $logoPfad = ROOT . '/static/img/logoDitib.png';
            $logoSrc  = file_exists($logoPfad)
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPfad))
                : '';
            $body = str_replace(['{name}', 'cid:ditib_logo'], [$name, $logoSrc], $mailtext);

            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{margin:0;padding:20px;background:#e8e8e8;font-family:Arial,sans-serif}
  .meta{max-width:600px;margin:0 auto 12px;background:#fff;border-radius:8px;padding:14px 18px;font-size:13px;border:1px solid #ddd}
  .meta b{color:#555;display:inline-block;width:80px}
  .badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;margin-left:8px}
  .badge-de{background:#8B1A1A;color:#fff} .badge-tr{background:#c8a95a;color:#333}
</style></head><body>
<div class="meta">
  <div><b>An:</b> ' . htmlspecialchars($spende['email'] ?: '(keine E-Mail)') . '</div>
  <div style="margin-top:6px"><b>Betreff:</b> ' . htmlspecialchars($subject) . '
    <span class="badge ' . ($sprache === 'de' ? 'badge-de' : 'badge-tr') . '">' . ($sprache === 'de' ? '🇩🇪 Deutsch' : '🇹🇷 Türkisch') . '</span>
  </div>
  <div style="margin-top:6px"><b>Anhänge:</b> <span style="color:#666">' . basename($spende['pdf_pfad'] ?? 'Spendenbescheinigung.pdf') . ', Mitgliedsantrag - Spendeformular.pdf</span></div>
</div>
' . $body . '</body></html>';
            exit;
        })(),

    // API: Original-Mail aus IMAP-Archiv anzeigen
    (bool) preg_match('#^/api/spende/(\d+)/original-mail$#', $uri, $mOrgMail) && $method === 'GET' => (function () use ($mOrgMail) {
            requireLogin();
            $sid    = (int) $mOrgMail[1];
            $spende = Database::spendeById($sid);
            if (!$spende || empty($spende['mail_id'])) {
                http_response_code(404);
                echo '<p style="font-family:Arial;padding:20px;color:#888">Keine Original-Mail vorhanden.</p>';
                exit;
            }
            header('Content-Type: text/html; charset=UTF-8');
            if (!empty($spende['mail_body'])) {
                // PayPal-API-Import: mail_body enthält Transaktions-JSON → als deutsche Tabelle anzeigen
                $tx = json_decode($spende['mail_body'], true);
                if (is_array($tx) && (isset($tx['transaction_info']) || isset($tx['payer_info']))) {
                    render('paypal_mail', ['tx' => $tx, 'spende' => $spende]);
                } else {
                    echo $spende['mail_body'];
                }
            } else {
                echo '<p style="font-family:Arial;padding:20px;color:#888">Mail-Inhalt nicht gespeichert (vor dem Update importiert). Bitte Mail erneut importieren.</p>';
            }
            exit;
        })(),

    // API: PayPal-Transaktionsdetail (für Popup in der Transaktionsübersicht)
    (bool) preg_match('#^/api/paypal/tx/([0-9A-Z]+)/detail$#', $uri, $mTxDetail) && $method === 'GET' => (function () use ($mTxDetail) {
            requireLogin();
            $txId = $mTxDetail[1];
            $row  = Database::paypalTxById($txId);
            if (!$row) {
                http_response_code(404);
                echo '<p style="font-family:Arial;padding:20px;color:#888">Transaktion nicht gefunden.</p>';
                exit;
            }
            header('Content-Type: text/html; charset=UTF-8');
            // Wenn vollständiges PayPal-JSON vorhanden → bestehendes paypal_mail-Template nutzen
            if (!empty($row['mail_body'])) {
                $tx = json_decode($row['mail_body'], true);
                if (is_array($tx) && isset($tx['transaction_info'])) {
                    render('paypal_mail', ['tx' => $tx, 'spende' => ['id' => $row['spende_id']]]);
                    exit;
                }
            }
            // Fallback: Daten aus paypal_transaktionen als einfache Tabelle
            $eur = fn($v) => number_format((float) $v, 2, ',', '.') . ' €';
            $felder = [
                'Transaktions-ID'  => htmlspecialchars($row['tx_id']),
                'Datum'            => htmlspecialchars(substr($row['datum'], 0, 16)),
                'Name'             => htmlspecialchars(trim($row['vorname'] . ' ' . $row['nachname'])),
                'E-Mail'           => htmlspecialchars($row['email']),
                'Betreff'          => htmlspecialchars($row['betreff']),
                'Brutto'           => $eur($row['brutto']),
                'PayPal-Gebühr'    => '− ' . $eur($row['gebuehr']),
                'Netto'            => $eur($row['netto']),
                'Währung'          => htmlspecialchars($row['waehrung']),
                'Status'           => $row['status'] === 'S' ? '✅ Abgeschlossen' : htmlspecialchars($row['status']),
                'Geladen am'       => htmlspecialchars(substr($row['geladen_am'] ?? '', 0, 16)),
            ];
            if ($row['spende_id']) {
                $felder['Spende'] = '<a href="/spende/' . (int) $row['spende_id'] . '" target="_top">#' . (int) $row['spende_id'] . ' öffnen</a>';
            }
            echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>TX-Detail</title>';
            echo '<style>body{font-family:Arial,sans-serif;font-size:14px;color:#333;margin:16px;background:#fff}';
            echo 'h2{font-size:16px;color:#003087;border-bottom:2px solid #0070ba;padding-bottom:4px;margin-bottom:8px}';
            echo 'table{border-collapse:collapse;width:100%;max-width:720px}';
            echo 'th,td{text-align:left;padding:6px 10px;border:1px solid #e0e0e0;vertical-align:top}';
            echo 'th{width:200px;background:#f5f8fa;font-weight:600;color:#555}';
            echo 'tr:nth-child(even) td{background:#fafcfe}</style></head><body>';
            echo '<h2>💳 Transaktion</h2><table>';
            foreach ($felder as $label => $wert) {
                if ($wert === '' || $wert === '− 0,00 €') continue;
                echo '<tr><th>' . htmlspecialchars($label) . '</th><td>' . $wert . '</td></tr>';
            }
            echo '</table>';
            echo '<p style="color:#888;font-size:12px;margin-top:14px">Quelle: paypal_transaktionen (lokale Kopie)</p>';
            echo '</body></html>';
            exit;
        })(),

    // API: Dateiname-Vorschau
    $uri === '/api/dateiname' => (function () {
            requireLogin();
            $gen = new Generator();
            $fn = $gen->dateiname([
            'vorname' => $_GET['vorname'] ?? '',
            'nachname' => $_GET['nachname'] ?? '',
            'datum' => $_GET['datum'] ?? date('d.m.Y'),
            ]);
            jsonOut(['dateiname' => $fn]);
        })(),

    // API: Status auf "nicht_erforderlich" setzen
    (bool) preg_match('#^/api/spende/(\d+)/nicht-erforderlich$#', $uri, $mNE) && $method === 'POST' => (function () use ($mNE) {
            requireLogin();
            $sid = (int) $mNE[1];
            $spende = Database::spendeById($sid);
            if (!$spende) jsonOut(['ok' => false, 'error' => 'Nicht gefunden']);
            // PDF löschen falls vorhanden
            $pdf = $spende['pdf_pfad'] ?? '';
            if ($pdf && file_exists($pdf)) unlink($pdf);
            Database::spendeUpdate($sid, ['status' => 'nicht_erforderlich', 'pdf_pfad' => '']);
            Database::kommentarAdd($sid, 'Als „Nicht erforderlich" markiert – keine Bescheinigung ausgestellt', getCurrentUser(), 'system');
            Database::logAdd('OK', "Spende #$sid: Nicht erforderlich", getCurrentUser());
            jsonOut(['ok' => true]);
        })(),

    // API: Fall reaktivieren – setzt nicht_erforderlich zurück, generiert PDF neu
    (bool) preg_match('#^/api/spende/(\d+)/reaktivieren$#', $uri, $mReakt) && $method === 'POST' => (function () use ($mReakt) {
            requireLogin();
            $sid = (int) $mReakt[1];
            $spende = Database::spendeById($sid);
            if (!$spende) jsonOut(['ok' => false, 'error' => 'Nicht gefunden']);
            // Archivschutz: eine versendete Bescheinigung darf nicht gelöscht werden
            if (versendet($spende)) {
                jsonOut(['ok' => false, 'error' => 'Bereits versendet – die Bescheinigung bleibt als Nachweis erhalten und wird nicht neu erstellt.']);
            }
            // Altes PDF löschen falls vorhanden (nur bei noch nicht versendeten Fällen)
            $altPdf = $spende['pdf_pfad'] ?? '';
            if ($altPdf && file_exists($altPdf)) unlink($altPdf);
            // Ausländisch-Flag zurücksetzen und Status auf neu
            Database::spendeUpdate($sid, ['auslaendisch' => 0, 'land' => '', 'status' => 'neu', 'pdf_pfad' => '']);
            // Aktuellen Datensatz holen und PDF generieren
            $spende = Database::spendeById($sid);
            $gen = new Generator();
            $pdf = $gen->erstellen($spende);
            if ($pdf) {
                Database::spendeUpdate($sid, ['pdf_pfad' => $pdf]);
                Database::kommentarAdd($sid, 'Fall reaktiviert – PDF neu erstellt', getCurrentUser(), 'system');
                Database::logAdd('OK', "Spende #$sid reaktiviert, PDF: " . basename($pdf));
                jsonOut(['ok' => true]);
            } else {
                Database::spendeUpdate($sid, ['status' => 'fehler']);
                Database::kommentarAdd($sid, 'Fall reaktiviert – PDF-Erstellung fehlgeschlagen', getCurrentUser(), 'system');
                Database::logAdd('ERROR', "Spende #$sid reaktiviert, aber PDF-Fehler", getCurrentUser());
                jsonOut(['ok' => false, 'error' => 'PDF-Erstellung fehlgeschlagen']);
            }
        })(),

    // API: Als erledigt markieren – nach Versand interne Abschlussarbeiten abgeschlossen
    (bool) preg_match('#^/api/spende/(\d+)/erledigt$#', $uri, $mErl) && $method === 'POST' => (function () use ($mErl) {
            requireLogin();
            $sid = (int) $mErl[1];
            $spende = Database::spendeById($sid);
            if (!$spende) jsonOut(['ok' => false, 'error' => 'Nicht gefunden']);
            $now = date('d.m.Y H:i');
            Database::spendeUpdate($sid, ['status' => 'erledigt']);
            Database::kommentarAdd($sid, "Als erledigt markiert am $now – interne Abschlussarbeiten abgeschlossen", getCurrentUser(), 'system');
            Database::logAdd('OK', "Spende #$sid als erledigt markiert", getCurrentUser());
            jsonOut(['ok' => true]);
        })(),

    // API: Ausländisch-Flag manuell zurücksetzen
    (bool) preg_match('#^/api/spende/(\d+)/inlaendisch$#', $uri, $mInl) && $method === 'POST' => (function () use ($mInl) {
            requireLogin();
            $sid = (int) $mInl[1];
            Database::spendeUpdate($sid, ['auslaendisch' => 0, 'land' => 'Deutschland', 'status' => 'neu']);
            Database::kommentarAdd($sid, 'Manuell als inländischer Spender markiert – Bescheinigung kann erstellt werden', getCurrentUser(), 'system');
            jsonOut(['ok' => true]);
        })(),

    // API: Ausländische Spender erkennen + PDFs löschen
    $uri === '/api/auslaendisch-scan' && $method === 'POST' => (function () {
            requireLogin();
            $db      = \App\DB\Database::get();
            $spenden = $db->query("SELECT id, plz, pdf_pfad FROM spenden WHERE auslaendisch = 0 AND (plz = '' OR plz NOT REGEXP '^\d{5}$')")->fetchAll();
            // SQLite hat kein REGEXP – manuell filtern
            $alle    = $db->query("SELECT id, plz, pdf_pfad FROM spenden WHERE auslaendisch = 0")->fetchAll();
            $markiert = 0;
            foreach ($alle as $row) {
                $plz = trim($row['plz'] ?? '');
                if ($plz !== '' && preg_match('/^\d{5}$/', $plz)) continue; // deutsch → überspringen
                // Kein oder nicht-deutsches PLZ → ausländisch
                $pdf = trim($row['pdf_pfad'] ?? '');
                if ($pdf && file_exists($pdf)) {
                    unlink($pdf);
                }
                $db->prepare("UPDATE spenden SET auslaendisch = 1, pdf_pfad = '', status = CASE WHEN status = 'versendet' THEN 'versendet' ELSE 'nicht_erforderlich' END WHERE id = ?")->execute([$row['id']]);
                \App\DB\Database::kommentarAdd((int)$row['id'], 'Als ausländischer Spender markiert – keine Bescheinigung ausgestellt', getCurrentUser(), 'system');
                $markiert++;
            }
            \App\DB\Database::logAdd('OK', "Ausländisch-Scan: $markiert Einträge markiert", getCurrentUser());
            jsonOut(['ok' => true, 'markiert' => $markiert]);
        })(),

    // API: Mail-Body Backfill (einmalig für bestehende Mails)
    $uri === '/api/mailbody-backfill' && $method === 'POST' => (function () {
            requireLogin();
            $cfg    = require ROOT . '/config/settings.php';
            $host   = '{' . $cfg['imap_host'] . ':' . $cfg['imap_port'] . '/imap/ssl/novalidate-cert}';
            $ordner = [
                $cfg['imap_done_folder']     ?? 'spenden-archiv',
                $cfg['imap_copy_folder']     ?? 'spenden-eingang',
                $cfg['imap_low_folder']      ?? 'spenden-kleinbetrag',
            ];

            // Alle Spenden mit mail_id aber ohne mail_body laden
            $db      = \App\DB\Database::get();
            $pending = $db->query("SELECT id, mail_id FROM spenden WHERE mail_id != '' AND mail_id IS NOT NULL AND (mail_body = '' OR mail_body IS NULL)")->fetchAll();
            if (!$pending) jsonOut(['ok' => true, 'aktualisiert' => 0, 'msg' => 'Alle Mails haben bereits einen gespeicherten Body.']);

            // Index: mail_id → spende_id
            $lookup = [];
            foreach ($pending as $row) {
                $clean = trim($row['mail_id'], '<>');
                $lookup[$clean] = (int) $row['id'];
                $lookup[$row['mail_id']] = (int) $row['id']; // auch mit Klammern
            }

            $decode = function (string $raw, int $enc): string {
                if ($enc === 3) return base64_decode($raw);
                if ($enc === 4) return quoted_printable_decode($raw);
                return $raw;
            };

            $aktualisiert = 0;

            foreach ($ordner as $folder) {
                if (empty($lookup)) break;
                $imap = @imap_open($host . $folder, $cfg['imap_user'], $cfg['imap_password'], 0, 1);
                if (!$imap) continue;

                $alle = @imap_search($imap, 'ALL');
                if (!$alle) { @imap_close($imap); continue; }

                foreach ($alle as $msgNo) {
                    if (empty($lookup)) break;
                    $hdr = @imap_fetchheader($imap, $msgNo);
                    if (!$hdr) continue;

                    // Message-ID aus Header extrahieren
                    $mid = null;
                    if (preg_match('/^Message-ID:\s*(.+)$/mi', $hdr, $m)) {
                        $mid = trim($m[1]);
                    }
                    if (!$mid) continue;

                    $sid = $lookup[trim($mid, '<>')] ?? $lookup[$mid] ?? null;
                    if (!$sid) continue;

                    // Body holen
                    $struct = @imap_fetchstructure($imap, $msgNo);
                    $body   = '';
                    $isHtml = false;

                    if ($struct && !empty($struct->parts)) {
                        foreach ($struct->parts as $i => $part) {
                            if (strtoupper($part->subtype ?? '') === 'HTML') {
                                $body   = $decode(imap_fetchbody($imap, $msgNo, $i + 1), $part->encoding ?? 0);
                                $isHtml = true;
                                break;
                            }
                        }
                        if (!$body) {
                            $part = $struct->parts[0];
                            $body = $decode(imap_fetchbody($imap, $msgNo, 1), $part->encoding ?? 0);
                        }
                    } else {
                        $body   = $decode(imap_body($imap, $msgNo), $struct->encoding ?? 0);
                        $isHtml = strtoupper($struct->subtype ?? '') === 'HTML';
                    }

                    if (!$isHtml && $body) {
                        $body = '<pre style="white-space:pre-wrap;font-family:monospace;padding:16px">' . htmlspecialchars($body) . '</pre>';
                    }

                    if ($body) {
                        $db->prepare("UPDATE spenden SET mail_body = ? WHERE id = ?")->execute([$body, $sid]);
                        unset($lookup[trim($mid, '<>')], $lookup[$mid]);
                        $aktualisiert++;
                    }
                }
                @imap_close($imap);
            }

            \App\DB\Database::logAdd('OK', "Mail-Body Backfill: $aktualisiert von " . count($pending) . " aktualisiert");
            jsonOut(['ok' => true, 'aktualisiert' => $aktualisiert, 'gesamt' => count($pending)]);
        })(),

    // API: IMAP testen
    $uri === '/api/imap/test' && $method === 'POST' => (function () {
            requireLogin();
            $reader = new App\Imap\MailReader();
            jsonOut($reader->test());
        })(),

    // API: PayPal-Transaktionen für Zeitraum in lokale Tabelle laden
    $uri === '/api/paypal/sync' && $method === 'POST' => (function () {
            requireLogin();
            set_time_limit(300); // lange Zeiträume = viele API-Blöcke
            try {
                $von = new DateTime(!empty($_POST['von']) ? $_POST['von'] : '-31 days');
                $bis = !empty($_POST['bis']) ? (new DateTime($_POST['bis']))->setTime(23, 59, 59) : new DateTime('now');
                if ($bis > new DateTime('now')) {
                    $bis = new DateTime('now');
                }
                $client = new App\PayPal\ApiClient();
                $stat = $client->synchronisieren($von, $bis);
                \App\DB\Database::logAdd('OK', "PayPal-Sync: {$stat['gespeichert']} Zahlungen gespeichert ({$stat['gesamt']} Transaktionen geprüft)");
                jsonOut(['ok' => true] + $stat);
            } catch (Exception $e) {
                \App\DB\Database::logAdd('ERROR', 'PayPal-Sync: ' . $e->getMessage());
                jsonOut(['ok' => false, 'error' => $e->getMessage()]);
            }
        })(),

    // API: PayPal-Verbindung testen (Token + Transaktionsabruf)
    $uri === '/api/paypal/test' && $method === 'POST' => (function () {
            requireLogin();
            $cfg = require ROOT . '/config/settings.php';
            try {
                $client = new App\PayPal\ApiClient($cfg);
                $client->tokenHolen();
                $tage = min(max((int) ($cfg['paypal_import_tage'] ?? 3), 1), 31);
                $von  = (new DateTime("-$tage days"))->setTime(0, 0);
                $txs  = $client->transaktionenAbrufen($von, new DateTime('now'));
                jsonOut([
                    'ok'     => true,
                    'modus'  => $cfg['paypal_mode'] ?? 'sandbox',
                    'tage'   => $tage,
                    'anzahl' => count($txs),
                ]);
            } catch (Exception $e) {
                jsonOut(['ok' => false, 'error' => $e->getMessage()]);
            }
        })(),

    // API: Spender-Suche (Autocomplete)
    $uri === '/api/spender-suche' => (function () {
            requireLogin();
            $q = trim($_GET['q'] ?? '');
            $spenden = Database::spendeAlle();
            $spender = [];
            $seen = [];
            foreach ($spenden as $s) {
                $key = mb_strtolower($s['vorname'] . $s['nachname']);
                if (
                !isset($seen[$key]) && (
                    mb_stripos($s['vorname'], $q) !== false ||
                    mb_stripos($s['nachname'], $q) !== false
                )
                ) {
                    $spender[] = [
                    'vorname' => $s['vorname'],
                    'nachname' => $s['nachname'],
                    'strasse' => $s['strasse'] ?? '',
                    'plz' => $s['plz'] ?? '',
                    'ort' => $s['ort'] ?? '',
                    'email' => $s['email'] ?? '',
                    ];
                    $seen[$key] = true;
                }
            }
            jsonOut($spender);
        })(),

    // API: Sammel-Vorschau
    $uri === '/api/sammel-vorschau' => (function () {
            requireLogin();
            $von = $_GET['von'] ?? '';
            $bis = $_GET['bis'] ?? '';
            $name = trim($_GET['name'] ?? '');
            $teile = preg_split('/\s+/', $name, 2);
            $vorname = $teile[0] ?? '';
            $nachname = $teile[1] ?? '';

            $alle = Database::spendeAlle('alle', '', 'datum ASC', $von, $bis);
            $gefunden = array_values(array_filter(
            $alle,
            fn($s) =>
            mb_strtolower($s['vorname']) === mb_strtolower($vorname) &&
            mb_strtolower($s['nachname']) === mb_strtolower($nachname)
            ));

            $bereits = count(array_filter($gefunden, fn($s) => $s['status'] === 'versendet'));

            jsonOut([
            'spenden' => $gefunden,
            'gesamt' => array_sum(array_column($gefunden, 'betrag')),
            'anzahl' => count($gefunden),
            'bereits_bescheinigt' => $bereits,
            ]);
        })(),
    // API: Jahres-Sammelbescheinigungen
    $uri === '/api/jahresbescheinigungen' && $method === 'POST' => (function () {
            requireLogin();
            $body = json_decode(file_get_contents('php://input'), true);
            $jahr = (int) ($body['jahr'] ?? date('Y'));

            // Alle Spenden des Jahres holen
            $alle = Database::spendeAlle();
            $alleJahr = array_filter($alle, function ($s) use ($jahr) {
                // Datum ist TT.MM.JJJJ
                return substr($s['datum'], 6, 4) === (string) $jahr;
            });

            // Nach Spender gruppieren
            $perSpender = [];
            foreach ($alleJahr as $s) {
                $key = mb_strtolower($s['vorname'] . '|' . $s['nachname']);
                if (!isset($perSpender[$key])) {
                    $perSpender[$key] = [
                    'vorname' => $s['vorname'],
                    'nachname' => $s['nachname'],
                    'strasse' => $s['strasse'],
                    'plz' => $s['plz'],
                    'ort' => $s['ort'],
                    'email' => $s['email'],
                    'spenden' => [],
                    ];
                }
                $perSpender[$key]['spenden'][] = $s;
            }

            $erstellt = 0;
            $uebersprungen = 0;
            $fehler = 0;
            $gen = new Generator();

            foreach ($perSpender as $key => $spender) {
                // Nur Spender mit mehr als einer Spende
                if (count($spender['spenden']) < 2) {
                    $uebersprungen++;
                    continue;
                }

                // Prüfen ob bereits eine Sammelbescheinigung für dieses Jahr existiert
                $hatSammel = false;
                foreach ($spender['spenden'] as $s) {
                    if (
                    str_contains($s['art'] ?? '', 'Sammelbescheinigung')
                    && str_contains($s['art'] ?? '', (string) $jahr)
                    ) {
                        $hatSammel = true;
                        break;
                    }
                }
                if ($hatSammel) {
                    $uebersprungen++;
                    Database::logAdd('INFO', "Jahresbescheinigung {$jahr}: {$spender['vorname']} {$spender['nachname']} bereits vorhanden", getCurrentUser());
                    continue;
                }

                // Gesamtbetrag berechnen – nur nicht-versendet Spenden
                // Ausschließen:
                // - versendet       → bereits beim Spender angekommen
                // - sammelbescheinigt → bereits in einer Sammelbescheinigung erfasst
                // - art enthält "Sammelbescheinigung" → keine Sammel-von-Sammel
                $zuErfassen = array_filter(
                $spender['spenden'],
                fn($s) =>
                $s['status'] !== 'versendet' &&
                $s['status'] !== 'sammelbescheinigt' &&
                !str_contains($s['art'] ?? '', 'Sammelbescheinigung')
                );

                if (empty($zuErfassen)) {
                    $uebersprungen++;
                    continue;
                }

                $gesamt = array_sum(array_column($zuErfassen, 'betrag'));
                $ids = array_column($zuErfassen, 'id');

                $sammelSpende = [
                'vorname' => $spender['vorname'],
                'nachname' => $spender['nachname'],
                'strasse' => $spender['strasse'],
                'plz' => $spender['plz'],
                'ort' => $spender['ort'],
                'email' => $spender['email'],
                'betrag' => $gesamt,
                'datum' => '31.12.' . $jahr,
                'art' => "Geldzuwendung (Sammelbescheinigung) für $jahr",
                'zahlungsweg' => 'Verschiedene',
                'quelle' => 'manuell',
                'status' => 'neu',
                'kommentar' => '',
                'mitgliedsnr' => '',
                'zeitraum_von' => '01.01.' . $jahr,
                'zeitraum_bis' => '31.12.' . $jahr,
                'sammel_ids' => json_encode($ids),
                'mail_id' => null,
                ];

                $sid = Database::spendeErstellen($sammelSpende);
                if ($sid) {
                    $pdf = $gen->erstellen($sammelSpende);
                    if ($pdf) {
                        Database::spendeUpdate($sid, ['pdf_pfad' => $pdf, 'status' => 'neu']);
                        Database::kommentarAdd(
                        $sid,
                        "Jahres-Sammelbescheinigung $jahr – " . count($zuErfassen) . " Spenden – IDs: " . implode(', ', $ids),
                        'System',
                        'system'
                        );
                        // Einzelspenden als "in Sammelbescheinigung erfasst" markieren
                        foreach ($ids as $id) {
                            Database::spendeUpdate($id, ['status' => 'sammelbescheinigt']);
                        }
                        $erstellt++;
                        Database::logAdd('OK', "Jahresbescheinigung {$jahr} erstellt: {$spender['vorname']} {$spender['nachname']} – {$gesamt}€", getCurrentUser());
                    } else {
                        $fehler++;
                        Database::logAdd('ERROR', "Jahresbescheinigung {$jahr} PDF-Fehler: {$spender['vorname']} {$spender['nachname']}", getCurrentUser());
                    }
                } else {
                    $fehler++;
                }
            }

            Database::logAdd('OK', "Jahresbescheinigungen $jahr abgeschlossen: $erstellt erstellt, $uebersprungen übersprungen, $fehler Fehler", getCurrentUser());
            jsonOut([
            'ok' => true,
            'erstellt' => $erstellt,
            'uebersprungen' => $uebersprungen,
            'fehler' => $fehler,
            ]);
        })(),
    // ── Sitzungsprotokolle ──

    // Übersicht aller Protokolle
    $uri === '/protokolle' => (function () {
            requireLogin();
            $protokolle = Database::protokollAlle();
            render('protokolle', compact('protokolle'));
        })(),

    // Neues Protokoll anlegen (Standard-Teilnehmer vorab angehakt) und direkt öffnen
    $uri === '/protokoll/neu' && $method === 'POST' => (function () {
            requireLogin();
            $id = Database::protokollErstellen();
            Database::logAdd('OK', "Protokoll #$id angelegt", getCurrentUser());
            redirect("/protokoll/$id");
        })(),

    // Live-Editor
    (bool) preg_match('#^/protokoll/(\d+)$#', $uri, $mProt) && $method === 'GET' => (function () use ($mProt) {
            requireLogin();
            $protokoll = Database::protokollById((int) $mProt[1]);
            if (!$protokoll) { http_response_code(404); echo 'Protokoll nicht gefunden'; exit; }
            $personenVorlagen  = Database::protokollPersonen();
            $bausteineVorlagen = Database::protokollBausteine();
            render('protokoll', compact('protokoll', 'personenVorlagen', 'bausteineVorlagen'));
        })(),

    // PDF-Ausgabe im Layout der Word-Vorlage
    (bool) preg_match('#^/protokoll/(\d+)/pdf$#', $uri, $mProtPdf) => (function () use ($mProtPdf) {
            requireLogin();
            $protokoll = Database::protokollById((int) $mProtPdf[1]);
            if (!$protokoll) { http_response_code(404); echo 'Protokoll nicht gefunden'; exit; }
            $pdf = (new \App\Pdf\Protokoll())->erstellen($protokoll);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . \App\Pdf\Protokoll::dateiname($protokoll) . '"');
            header('Cache-Control: no-store');
            echo $pdf;
            exit;
        })(),

    // Autosave: kompletter Protokoll-Stand als JSON
    (bool) preg_match('#^/api/protokoll/(\d+)/speichern$#', $uri, $mProtSave) && $method === 'POST' => (function () use ($mProtSave) {
            requireLogin();
            $id = (int) $mProtSave[1];
            if (!Database::protokollById($id)) jsonOut(['ok' => false, 'error' => 'Nicht gefunden']);
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $update = [];
            foreach (['datum', 'beginn', 'ende'] as $f) {
                if (isset($body[$f])) $update[$f] = trim((string) $body[$f]);
            }
            if (isset($body['status']) && in_array($body['status'], ['offen', 'abgeschlossen'], true)) {
                $update['status'] = $body['status'];
            }
            if (isset($body['teilnehmer']) && is_array($body['teilnehmer'])) {
                $update['teilnehmer'] = json_encode(array_values($body['teilnehmer']), JSON_UNESCAPED_UNICODE);
            }
            if (isset($body['tops']) && is_array($body['tops'])) {
                $update['tops'] = json_encode(array_values($body['tops']), JSON_UNESCAPED_UNICODE);
            }
            Database::protokollUpdate($id, $update);
            jsonOut(['ok' => true, 'gespeichert' => date('H:i:s')]);
        })(),

    (bool) preg_match('#^/api/protokoll/(\d+)/loeschen$#', $uri, $mProtDel) && $method === 'POST' => (function () use ($mProtDel) {
            requireLogin();
            Database::get()->prepare("DELETE FROM protokolle WHERE id = ?")->execute([(int) $mProtDel[1]]);
            Database::logAdd('OK', "Protokoll #{$mProtDel[1]} gelöscht", getCurrentUser());
            jsonOut(['ok' => true]);
        })(),

    // Vorlagen (übliche Teilnehmer / TOP-Bausteine) komplett speichern
    $uri === '/api/protokoll-vorlagen/personen' && $method === 'POST' => (function () {
            requireLogin();
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            Database::protokollPersonenSpeichern(is_array($body['personen'] ?? null) ? $body['personen'] : []);
            jsonOut(['ok' => true, 'personen' => Database::protokollPersonen()]);
        })(),

    $uri === '/api/protokoll-vorlagen/bausteine' && $method === 'POST' => (function () {
            requireLogin();
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            Database::protokollBausteineSpeichern(is_array($body['bausteine'] ?? null) ? $body['bausteine'] : []);
            jsonOut(['ok' => true, 'bausteine' => Database::protokollBausteine()]);
        })(),

    // 404
    default => (function () {
            http_response_code(404);
            echo '<h1>404 – Seite nicht gefunden</h1>';
        })(),
};
