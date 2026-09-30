<?php
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
namespace App\Imap;

use App\DB\Database;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;

/**
 * IMAP-Mail-Reader (via webklex/php-imap)
 * Parser optimiert für PayPal-Zahlungsbenachrichtigungen
 *
 * Beispiel-Format:
 *   Sie haben eine Zahlung über €107,39 EUR von cemil bayram (cemil.Bayram.cb@gmail.com) erhalten
 *   Käufer
 *   cemil bayram
 *   cemil.Bayram.cb@gmail.com
 *   Lieferadresse
 *   cemil bayram
 *   Erzbergerstr. 22
 *   55120 Mainz
 *   Deutschland
 */
class MailReader
{

    private array $cfg;
    private ?Client $client = null;

    public function __construct()
    {
        $this->cfg = require __DIR__ . '/../../config/settings.php';
    }

    private function verbinden(): bool
    {
        try {
            $cm = new ClientManager();
            $this->client = $cm->make([
                'host' => $this->cfg['imap_host'],
                'port' => $this->cfg['imap_port'],
                'encryption' => 'ssl',
                'validate_cert' => false,
                'username' => $this->cfg['imap_user'],
                'password' => $this->cfg['imap_password'],
                'protocol' => 'imap',
            ]);
            $this->client->connect();
            Database::logAdd('INFO', "IMAP Login OK als {$this->cfg['imap_user']}");

            $folders = $this->client->getFolders();
            $namen = $folders->map(fn($f) => $f->full_name)->toArray();
            Database::logAdd('INFO', "Verfügbare Ordner: " . implode(', ', $namen));

            return true;
        } catch (\Exception $e) {
            Database::logAdd('ERROR', "IMAP-Verbindung fehlgeschlagen: " . $e->getMessage());
            return false;
        }
    }

    private function seitDatum(): \DateTime
    {
        $datum = $this->cfg['imap_seit_datum'] ?? '';
        if ($datum && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            return new \DateTime($datum);
        }
        $stunden = (int) ($this->cfg['imap_copy_stunden'] ?? 24);
        return new \DateTime("-{$stunden} hours");
    }

    private function ordnerErstellen(string $name): void
    {
        try {
            $this->client->createFolder($name);
        } catch (\Exception) {
        }
    }

    public function posteingang_kopieren(): array
    {
        $ergebnisse = ['kopiert' => 0, 'fehler' => 0, 'uebersprungen' => 0];
        if (!$this->verbinden())
            return $ergebnisse;

        $ziel = $this->cfg['imap_copy_folder'] ?? 'spenden-eingang';
        $filter = $this->cfg['imap_subject_filter'] ?? 'Zahlungseingang';
        $seit = $this->seitDatum();

        $this->ordnerErstellen($ziel);
        Database::logAdd('INFO', "Kopier-Job: Suche Mails ab " . $seit->format('d.m.Y') . " mit Filter '$filter'");

        try {
            $folder = $this->client->getFolder($this->cfg['imap_folder']);

            // fetchBody(false) = nur Header laden, KEIN Body → kein Speicherproblem
            $messages = $folder->query()
                ->all()
                ->since($seit)
                ->leaveUnread()
                ->setFetchBody(false)
                ->get();

            // Betreff-Filter
            $messages = $messages->filter(
                fn($m) =>
                mb_stripos(
                    mb_decode_mimeheader((string) $m->subject),
                    $filter
                ) !== false
            );

            Database::logAdd('INFO', "Verschie-Job: {$messages->count()} passende Mails gefunden");

            foreach ($messages as $msg) {
                $mid = trim((string) ($msg->message_id ?? 'noID_' . $msg->uid));
                $moveKey = 'move_' . $mid;

                if (Database::mailIdBekannt($moveKey)) {
                    $ergebnisse['uebersprungen']++;
                    continue;
                }

                try {
                    $msg->move($ziel);
                    Database::mailIdSpeichern($moveKey);
                    $ergebnisse['kopiert']++;
                    Database::logAdd('OK', "Verschoben: " . mb_decode_mimeheader((string) $msg->subject));
                } catch (\Exception $e) {
                    $ergebnisse['fehler']++;
                    Database::logAdd('ERROR', "Kopier-Fehler: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Database::logAdd('ERROR', "Kopier-Job fehlgeschlagen: " . $e->getMessage());
        }

        Database::logAdd('OK', "Kopier-Job: {$ergebnisse['kopiert']} kopiert, {$ergebnisse['uebersprungen']} übersprungen, {$ergebnisse['fehler']} Fehler");
        return $ergebnisse;
    }
    private function getRawHtml(\Webklex\PHPIMAP\Message $msg): string
    {
        $html = $msg->bodies['html'] ?? null;
        if ($html && strlen((string) $html) > 10) return (string) $html;
        $plain = $msg->bodies['text'] ?? null;
        if ($plain) return '<pre style="white-space:pre-wrap;font-family:monospace;padding:16px">'
            . htmlspecialchars((string) $plain) . '</pre>';
        return '';
    }

    private function getBody(\Webklex\PHPIMAP\Message $msg): string
    {
        $plain = $msg->bodies['text'] ?? null;
        if ($plain && strlen((string) $plain) > 50) {
            return (string) $plain;
        }

        $html = $msg->bodies['html'] ?? null;
        if ($html) {
            $h = (string) $html;
            // CSS und JS komplett entfernen
            $h = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $h);
            $h = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $h);
            // Block-Elemente → Zeilenumbruch
            $h = preg_replace('/<br\s*\/?>/i', "\n", $h);
            $h = preg_replace('/<\/p>/i', "\n", $h);
            $h = preg_replace('/<\/div>/i', "\n", $h);
            $h = preg_replace('/<\/tr>/i', "\n", $h);
            $h = preg_replace('/<\/td>/i', " ", $h);
            $h = strip_tags($h);
            $h = html_entity_decode($h, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Leere Zeilen entfernen
            $lines = array_filter(array_map('trim', explode("\n", $h)), fn($l) => $l !== '');
            return implode("\n", $lines);
        }
        return '';
    }

    // ══════════════════════════════════════════════════════
    //  PARSER – optimiert für PayPal-Zahlungsmails
    //  Format: "Sie haben eine Zahlung über €X EUR von NAME (EMAIL) erhalten"
    //  Adresse steht unter "Lieferadresse"-Block
    //  Name steht unter "Käufer"-Block
    // ══════════════════════════════════════════════════════
    private function parse(string $text): ?array
    {
        $data = [];
        $zeilen = array_map('trim', explode("\n", str_replace("\r\n", "\n", $text)));

        // ── Betrag ──
        // "Sie haben eine Zahlung über €107,39 EUR von ..."
        // oder "€107,39 EUR"
        // PayPal-Mails nutzen je nach Locale "53,89" (DE) oder "53.89" (US).
        // Hat der Wert ein Komma → europäisches Format (Punkt=Tausender, Komma=Dezimal).
        // Nur Punkt, kein Komma → US-Format (Punkt=Dezimal, nicht entfernen).
        $betragParsen = function (string $raw): float {
            if (str_contains($raw, ',')) {
                return (float) str_replace(['.', ','], ['', '.'], $raw);
            }
            return (float) $raw;
        };
        if (preg_match('/Zahlung[^€]*€\s*([\d.,]+)\s*EUR/u', $text, $m)) {
            $data['betrag'] = $betragParsen($m[1]);
        } elseif (preg_match('/€\s*([\d.,]+)\s*EUR/u', $text, $m)) {
            $data['betrag'] = $betragParsen($m[1]);
        } else {
            Database::logAdd('WARN', "Parser: Betrag nicht gefunden");
            return null;
        }

        // ── Datum ──
        // "Transaktionsdatum\n03.03.2026"
        if (preg_match('/Transaktionsdatum\s*\n\s*(\d{2}\.\d{2}\.\d{4})/u', $text, $m)) {
            $data['datum'] = $m[1];
        } elseif (preg_match('/(\d{2}\.\d{2}\.\d{4})/', $text, $m)) {
            $data['datum'] = $m[1];
        } else {
            $data['datum'] = date('d.m.Y');
        }

        // ── Name aus "Käufer"-Block ──
        // Format: "Käufer\ncemil bayram\ncemil.bayram@gmail.com"
        if (preg_match('/Käufer\s*\n\s*([^\n]+)\n\s*([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/u', $text, $m)) {
            $vollname = trim($m[1]);
            $data['email'] = trim($m[2]);
            $teile = explode(' ', $vollname, 2);
            $data['vorname'] = ucfirst(strtolower($teile[0]));
            $data['nachname'] = isset($teile[1]) ? ucwords(strtolower($teile[1])) : '';
        }
        // Fallback: aus "von NAME (EMAIL) erhalten"
        elseif (preg_match('/von\s+([^(]+)\s*\(([^)]+@[^)]+)\)\s*erhalten/u', $text, $m)) {
            $vollname = trim($m[1]);
            $data['email'] = trim($m[2]);
            $teile = explode(' ', $vollname, 2);
            $data['vorname'] = ucfirst(strtolower($teile[0]));
            $data['nachname'] = isset($teile[1]) ? ucwords(strtolower($teile[1])) : '';
        } else {
            Database::logAdd('WARN', "Parser: Name nicht gefunden");
            return null;
        }

        // ── Adresse aus "Lieferadresse"-Block ──
        // Robuste Variante: erfasst beliebig viele Zeilen zwischen Name und PLZ,
        // damit Sonderfälle wie Firmenzeilen oder zweigeteilte Straßen funktionieren.
        if (preg_match('/Lieferadresse\s*\n\s*[^\n]+\n((?:[^\n]+\n)*?)(\d{5})\s+([^\n]+)\n?([^\n]*)/u', $text, $m)) {
            $rawLines = array_values(array_filter(
                array_map('trim', explode("\n", trim($m[1]))),
                fn($l) => $l !== ''
            ));

            // Firmenzeilen (GmbH, AG, KG …) ignorieren
            $rawLines = array_values(array_filter(
                $rawLines,
                fn($l) => !preg_match('/\b(GmbH|AG|KG|OHG|e\.V\.|Inc\.|Ltd\.)\b/i', $l)
            ));

            // Hausnummer auf Folgezeile (z. B. "Grefrather Str.\n158a") anhängen
            if (count($rawLines) >= 2 && preg_match('/^\d+\s*[a-zA-Z]?\s*$/', $rawLines[1])) {
                $data['strasse'] = $rawLines[0] . ' ' . trim($rawLines[1]);
            } else {
                $data['strasse'] = $rawLines[0] ?? '';
            }

            $data['plz'] = trim($m[2]);
            $data['ort'] = trim(preg_replace('/\s*(Deutschland|Germany|DE)\s*/i', '', $m[3]));
            $land = trim($m[4] ?? '');
            // Immer als Inland behandeln – auch ausländische Spender erhalten eine Bescheinigung
            $data['land'] = (preg_match('/^(Deutschland|Germany|DE)$/i', $land) || $land === '')
                ? 'Deutschland' : $land;
            $data['auslaendisch'] = 0;
        } else {
            // Fallback: PLZ + Ort irgendwo im Text
            if (preg_match('/(\d{5})\s+([A-ZÄÖÜ][a-zäöüß\s\-]+?)(?:\n|,|$)/u', $text, $m)) {
                $data['plz'] = $m[1];
                $data['ort'] = trim($m[2]);
            }
            if (empty($data['land'])) {
                $data['land']        = '';
                $data['auslaendisch'] = 0;
            }
        }

        // Felder vervollständigen
        $jahr = substr($data['datum'], -4);
        $data['art'] = "Geldzuwendung für $jahr";
        $data['quelle'] = 'mail';
        $data['zahlungsweg'] = 'PayPal';
        $data['status'] = 'neu';

        Database::logAdd('INFO', sprintf(
            "Parser OK: %s %s · %s € · %s · %s %s",
            $data['vorname'],
            $data['nachname'],
            $data['betrag'],
            $data['datum'],
            $data['plz'] ?? '',
            $data['ort'] ?? ''
        ));

        return $data;
    }

    // ── Mails aus spenden-eingang verarbeiten ──
    public function verarbeitenEingang(): array
    {
        $ergebnisse = [];
        if (!$this->verbinden())
            return [];

        $eingangsOrdner = $this->cfg['imap_copy_folder'] ?? 'spenden-eingang';
        $minBetrag = (float) $this->cfg['mindestbetrag'];
        $this->ordnerErstellen($this->cfg['imap_done_folder']);
        $this->ordnerErstellen($this->cfg['imap_low_folder']);

        try {
            $folder = $this->client->getFolder($eingangsOrdner);
            $messages = $folder->query()->all()->leaveUnread()->get();

            Database::logAdd('INFO', "Eingang '{$eingangsOrdner}': {$messages->count()} Mails gefunden");

            foreach ($messages as $msg) {
                try {
                    $mid = (string) ($msg->message_id ?? 'noID_' . $msg->uid);

                    if (Database::mailIdBekannt($mid)) {
                        Database::logAdd('WARN', "Duplikat – verschiebe ins Archiv: " . substr($mid, 0, 60));
                        try { $msg->move($this->cfg['imap_done_folder']); } catch (\Exception) {}
                        continue;
                    }

                    $fullMsg = $folder->query()->whereUid($msg->uid)->setFetchBody(true)->get()->first();
                    if (!$fullMsg)
                        continue;

                    $body = $this->getBody($fullMsg);
                    $spende = $this->parse($body);

                    if (!$spende) {
                        Database::logAdd('ERROR', "Parse-Fehler: " . mb_decode_mimeheader((string) $msg->subject));
                        continue;
                    }

                    $spende['mail_id']   = $mid;
                    $spende['mail_body'] = $this->getRawHtml($fullMsg);
                    Database::mailIdSpeichern($mid);

                    if ($spende['betrag'] < $minBetrag) {
                        Database::logAdd('WARN', "Geringbetrag {$spende['betrag']}€ → '{$this->cfg['imap_low_folder']}'");
                        $msg->move($this->cfg['imap_low_folder']);
                        continue;
                    }

                    $ergebnisse[] = $spende;
                    $msg->move($this->cfg['imap_done_folder']);
                    Database::logAdd('OK', "Eingang verarbeitet: {$spende['vorname']} {$spende['nachname']} – {$spende['betrag']}€");

                } catch (\Exception $e) {
                    Database::logAdd('ERROR', "Fehler bei Eingang-Mail: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Database::logAdd('ERROR', "Eingang-Verarbeitung fehlgeschlagen: " . $e->getMessage());
        }

        return $ergebnisse;
    }

    // ── Alle Mails aus spenden-eingang in Unterordner verschieben (ohne DB-Duplikatprüfung) ──
    public function eingangVerschieben(): array
    {
        $ergebnisse = ['verschoben' => 0, 'fehler' => 0, 'gesamt' => 0];
        if (!$this->verbinden()) return $ergebnisse;

        $eingangsOrdner = $this->cfg['imap_copy_folder'] ?? 'spenden-eingang';
        $doneOrdner     = $this->cfg['imap_done_folder'];
        $lowOrdner      = $this->cfg['imap_low_folder'];
        $minBetrag      = (float) $this->cfg['mindestbetrag'];

        $this->ordnerErstellen($doneOrdner);
        $this->ordnerErstellen($lowOrdner);

        try {
            $folder   = $this->client->getFolder($eingangsOrdner);
            $messages = $folder->query()->all()->leaveUnread()->get();
            $ergebnisse['gesamt'] = $messages->count();

            Database::logAdd('INFO', "Eingang-Verschieben: {$messages->count()} Mails in '{$eingangsOrdner}'");

            foreach ($messages as $msg) {
                try {
                    $fullMsg = $folder->query()->whereUid($msg->uid)->setFetchBody(true)->get()->first();
                    if (!$fullMsg) { $ergebnisse['fehler']++; continue; }

                    $body   = $this->getBody($fullMsg);
                    $spende = $this->parse($body);

                    // Betrag < Mindestbetrag → Kleinbetrags-Ordner, sonst Archiv
                    if ($spende && $spende['betrag'] < $minBetrag) {
                        $msg->move($lowOrdner);
                        Database::logAdd('OK', "Kleinbetrag {$spende['betrag']}€ → '{$lowOrdner}'");
                    } else {
                        $msg->move($doneOrdner);
                        Database::logAdd('OK', "Verschoben → '{$doneOrdner}': " . mb_decode_mimeheader((string) $msg->subject));
                    }
                    $ergebnisse['verschoben']++;
                } catch (\Exception $e) {
                    $ergebnisse['fehler']++;
                    Database::logAdd('ERROR', "Eingang-Verschieben Fehler: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Database::logAdd('ERROR', "Eingang-Verschieben fehlgeschlagen: " . $e->getMessage());
        }

        Database::logAdd('OK', "Eingang-Verschieben: {$ergebnisse['verschoben']} verschoben, {$ergebnisse['fehler']} Fehler");
        return $ergebnisse;
    }

    // ── Hauptfunktion: Mails verarbeiten ──
    public function verarbeiten(): array
    {
        $ergebnisse = [];
        if (!$this->verbinden())
            return [];

        $this->ordnerErstellen($this->cfg['imap_done_folder']);
        $this->ordnerErstellen($this->cfg['imap_low_folder']);

        $filter = $this->cfg['imap_subject_filter'];
        $limit = (int) $this->cfg['mail_limit'];
        $minBetrag = (float) $this->cfg['mindestbetrag'];

        try {
            $folder = $this->client->getFolder($this->cfg['imap_folder']);
            $gefilterteUids = $folder->query()
                ->all()
                ->since($this->seitDatum())
                ->leaveUnread()
                ->setFetchBody(false)
                ->get();

            if ($filter) {
                $gefilterteUids = $gefilterteUids->filter(
                    fn($m) =>
                    mb_stripos(mb_decode_mimeheader((string) $m->subject), $filter) !== false
                );
            }
            Database::logAdd('INFO', "Verarbeite " . $gefilterteUids->count() . " Mails (Filter: '$filter')");

            if ($gefilterteUids->isEmpty()) {
                Database::logAdd('INFO', "Keine Mails mit Betreff '$filter'");
                return [];
            }
            $messages = $gefilterteUids->map(
                fn($m) =>
                $folder->query()->whereUid($m->uid)->setFetchBody(true)->get()->first()
            )->filter();

            if ($limit > 0)
                $messages = $messages->take($limit);
            Database::logAdd('INFO', "Verarbeite " . $messages->count() . " Mails");

            foreach ($messages as $msg) {
                try {
                    $mid = (string) ($msg->message_id ?? 'noID_' . $msg->uid);

                    if (Database::mailIdBekannt($mid)) {
                        Database::logAdd('WARN', "Duplikat: " . substr($mid, 0, 60));
                        continue;
                    }

                    $body = $this->getBody($msg);
                    $spende = $this->parse($body);

                    if (!$spende) {
                        Database::logAdd('ERROR', "Parse-Fehler: " . (string) $msg->subject);
                        continue;
                    }

                    $spende['mail_id'] = $mid;
                    Database::mailIdSpeichern($mid);

                    if ($spende['betrag'] < $minBetrag) {
                        Database::logAdd('WARN', "Geringbetrag {$spende['betrag']}€ → '{$this->cfg['imap_low_folder']}'");
                        $msg->move($this->cfg['imap_done_folder']);
                        continue;
                    }

                    $ergebnisse[] = $spende;
                    $msg->move($this->cfg['imap_done_folder']);
                    Database::logAdd('OK', "Verarbeitet: {$spende['vorname']} {$spende['nachname']} – {$spende['betrag']}€");

                } catch (\Exception $e) {
                    Database::logAdd('ERROR', "Fehler bei Mail: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Database::logAdd('ERROR', "IMAP-Fehler: " . $e->getMessage());
        }

        return $ergebnisse;
    }

    // ── Gesendete Objekte prüfen ──
    public function gesendeteAuslesen(): array
    {
        $ergebnisse = [];
        if (!$this->client && !$this->verbinden())
            return [];

        $limit = (int) $this->cfg['mail_limit'];
        $outDir = $this->cfg['output_dir'];

        try {
            $folder = $this->client->getFolder($this->cfg['imap_sent_folder']);
            $stunden = (int) ($this->cfg['imap_copy_stunden'] ?? 72);
            $seit = new \DateTime("-{$stunden} hours");
            $messages = $folder->query()->since($seit)->leaveUnread()->get();
            if ($limit > 0)
                $messages = $messages->take($limit);

            foreach ($messages as $msg) {
                try {
                    $mid = "sent_" . (string) ($msg->message_id ?? 'noID_' . $msg->uid);
                    if (Database::mailIdBekannt($mid))
                        continue;

                    foreach ($msg->attachments() as $att) {
                        $fn = (string) $att->name;
                        if (stripos($fn, 'spendenbescheinigung') !== false && str_ends_with(strtolower($fn), '.pdf')) {
                            if (!is_dir($outDir))
                                mkdir($outDir, 0775, true);
                            $pdfPath = $outDir . '/' . $fn;
                            file_put_contents($pdfPath, $att->content);

                            $body = $this->getBody($msg);
                            $spende = $this->parse($body) ?? [];
                            $spende['mail_id'] = $mid;
                            $spende['quelle'] = 'mail_gesendet';
                            $spende['status'] = 'versendet';
                            $spende['pdf_pfad'] = $pdfPath;

                            $to = $msg->to->first();
                            if ($to)
                                $spende['email'] = $to->mail;

                            Database::mailIdSpeichern($mid);
                            $ergebnisse[] = $spende;
                            Database::logAdd('OK', "Gesendete Bescheinigung erfasst: $fn");
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    Database::logAdd('WARN', "Gesendete: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Database::logAdd('WARN', "Gesendete-Ordner: " . $e->getMessage());
        }

        return $ergebnisse;
    }

    // ── IMAP-Test ──
    public function test(): array
    {
        try {
            if (!$this->verbinden())
                return ['ok' => false, 'error' => 'Verbindung fehlgeschlagen'];
            $folders = $this->client->getFolders();
            $namen = $folders->map(fn($f) => $f->full_name)->toArray();
            return ['ok' => true, 'ordner' => $namen];
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
