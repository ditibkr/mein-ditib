<?php
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
namespace App\Mail;

use App\DB\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class Sender
{

    private array $cfg;

    public function __construct()
    {
        $this->cfg = require __DIR__ . '/../../config/settings.php';
    }

    // ── Native IMAP-Verbindung (für Gesendete-Ordner) ──────────────────────
    private function imapOeffnen(string $ordner = ''): mixed
    {
        $host = '{' . $this->cfg['imap_host'] . ':' . $this->cfg['imap_port'] . '/imap/ssl/novalidate-cert}';
        return @imap_open($host . $ordner, $this->cfg['imap_user'], $this->cfg['imap_password'], 0, 1);
    }

    // ── Gesendete-Ordner nach Duplikat durchsuchen ─────────────────────────
    // Vergleich per X-Spende-ID (eindeutig pro DB-Eintrag).
    // Gleiche Spende = gleiche ID → Duplikat. Zweite Spende desselben Spenders
    // hat eine andere ID → kein Duplikat, auch wenn Name+Betrag identisch sind.
    public function bereitsGesendet(array $spende): ?array
    {
        $sentFolder = $this->cfg['imap_sent_folder'] ?? 'Gesendete Objekte';
        $imap       = $this->imapOeffnen($sentFolder);
        if (!$imap) return null;

        $spendeId = !empty($spende['id']) ? (string) $spende['id'] : null;
        $name     = trim($spende['vorname'] . ' ' . $spende['nachname']);
        $betrag   = number_format((float) $spende['betrag'], 2, '.', '');
        $email    = $spende['email'] ?? '';

        $gefunden = null;
        try {
            // Alle Mails an diesen Empfänger
            $msgs = @imap_search($imap, 'TO "' . addslashes($email) . '"');
            if ($msgs) {
                foreach ($msgs as $msgNo) {
                    $rawHeader = @imap_fetchheader($imap, $msgNo);
                    if (!$rawHeader) continue;

                    if ($spendeId) {
                        // Primär: Duplikat nur wenn X-Spende-ID übereinstimmt
                        // (verschiedene Spenden desselben Spenders haben andere IDs)
                        $idMatch = preg_match(
                            '/^X-Spende-ID:\s*' . preg_quote($spendeId, '/') . '\s*$/mi',
                            $rawHeader
                        );
                        if (!$idMatch) continue;
                    } else {
                        // Fallback für ältere Mails ohne X-Spende-ID: Name + Betrag
                        $nameMatch   = preg_match(
                            '/^X-Spende-Name:\s*' . preg_quote($name, '/') . '\s*$/mi',
                            $rawHeader
                        );
                        $betragMatch = preg_match(
                            '/^X-Spende-Betrag:\s*' . preg_quote($betrag, '/') . '\s*$/mi',
                            $rawHeader
                        );
                        if (!$nameMatch || !$betragMatch) continue;
                    }

                    $info = @imap_headerinfo($imap, $msgNo);
                    $gefunden = [
                        'datum'   => $info ? date('d.m.Y H:i', strtotime($info->date ?? 'now')) : '?',
                        'betreff' => $info ? @imap_utf8($info->subject ?? '') : '',
                    ];
                    break;
                }
            }
        } catch (\Exception) {
            // Prüfung schlägt fehl → Versand nicht blockieren, null zurück
        }

        imap_close($imap);
        return $gefunden;
    }

    // ── Gesendete Mail in Gesendete-Ordner kopieren ────────────────────────
    private function inGesendeteAblegen(PHPMailer $mail): void
    {
        $sentFolder = $this->cfg['imap_sent_folder'] ?? 'Gesendete Objekte';
        $host       = '{' . $this->cfg['imap_host'] . ':' . $this->cfg['imap_port'] . '/imap/ssl/novalidate-cert}';

        $imap = @imap_open($host, $this->cfg['imap_user'], $this->cfg['imap_password'], 0, 1);
        if (!$imap) {
            Database::logAdd('WARN', 'Gesendete-Ablage: IMAP-Verbindung fehlgeschlagen – ' . imap_last_error());
            return;
        }

        $ok = @imap_append($imap, $host . $sentFolder, $mail->getSentMIMEMessage(), '\\Seen');
        imap_close($imap);

        if ($ok) {
            Database::logAdd('OK', "Mail in '$sentFolder' abgelegt");
        } else {
            Database::logAdd('WARN', 'Gesendete-Ablage fehlgeschlagen: ' . imap_last_error());
        }
    }

    // ── Hauptfunktion: Mail senden ─────────────────────────────────────────
    // Rückgabe: ['ok' => bool, 'duplikat' => bool, 'info' => string]
    public function senden(array $spende, string $pdfPfad, string $sprache = 'tr', bool $skipDuplikat = false): array
    {
        $empfaenger = $spende['email'] ?? '';
        if (!$empfaenger) {
            Database::logAdd('WARN', "Keine E-Mail für {$spende['vorname']} {$spende['nachname']}");
            return ['ok' => false, 'duplikat' => false, 'info' => 'Keine E-Mail-Adresse'];
        }
        if (!file_exists($pdfPfad)) {
            Database::logAdd('ERROR', "PDF nicht gefunden: $pdfPfad");
            return ['ok' => false, 'duplikat' => false, 'info' => 'PDF nicht gefunden'];
        }

        $cfg      = require __DIR__ . '/../../config/settings.php';
        $testmodus = $cfg['testmodus'] ?? false;

        // ── Duplikat-Prüfung (nur im Produktivmodus, überspringbar) ──────
        if (!$testmodus && !$skipDuplikat) {
            $duplikat = $this->bereitsGesendet($spende);
            if ($duplikat) {
                $hinweis = "Duplikat: Mail an $empfaenger bereits gesendet am {$duplikat['datum']}";
                Database::logAdd('WARN', $hinweis);
                return ['ok' => false, 'duplikat' => true, 'info' => "Bereits gesendet am {$duplikat['datum']}"];
            }
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $this->cfg['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->cfg['smtp_user'];
            $mail->Password   = $this->cfg['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $this->cfg['smtp_port'];
            $mail->CharSet    = 'UTF-8';

            if (!empty($spende['mail_id'])) {
                $mail->addCustomHeader('In-Reply-To', $spende['mail_id']);
                $mail->addCustomHeader('References',  $spende['mail_id']);
            }

            $name = trim($spende['vorname'] . ' ' . $spende['nachname']);

            // Custom-Header für Duplikat-Erkennung
            $mail->addCustomHeader('X-Spende-Name',   $name);
            $mail->addCustomHeader('X-Spende-Betrag', number_format((float) $spende['betrag'], 2, '.', ''));
            if (!empty($spende['id'])) {
                $mail->addCustomHeader('X-Spende-ID', (string) $spende['id']);
            }

            $empfaengerMail = $testmodus ? ($cfg['test_email'] ?? $empfaenger) : $empfaenger;

            $mail->setFrom($this->cfg['smtp_from_email'], $this->cfg['smtp_from_name']);
            $mail->addAddress($empfaengerMail, $name);

            // Betreff und Mailtext je Sprache
            if ($sprache === 'de') {
                $mail->Subject = $this->cfg['smtp_subject_de'] ?? 'Herzlichen Dank für Ihre Spende / Spendenbescheinigung im Anhang';
                $mailtext      = $this->cfg['smtp_mailtext_de'] ?? $this->cfg['smtp_mailtext'] ?? '';
            } else {
                $mail->Subject = $this->cfg['smtp_subject_tr'] ?? 'Bağışınız İçin Gönülden Teşekkür Ederiz / Bağış Makbuzu Ektedir';
                $mailtext      = $this->cfg['smtp_mailtext'] ?? '';
            }

            $htmlBody = str_replace('{name}', htmlspecialchars($name), $mailtext);
            $mail->isHTML(true);
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

            if ($testmodus) {
                $mail->Subject = '[TESTMODUS] ' . $mail->Subject;
                $mail->Body    = '<div style="background:#fff3cd;border:2px solid #f0a500;border-radius:6px;'
                    . 'padding:12px 16px;margin-bottom:16px;font-family:Arial,sans-serif;font-size:13px;color:#7d4e00">'
                    . '<strong>⚠ TESTMODUS</strong> – Originalempfänger: ' . htmlspecialchars($empfaenger)
                    . '</div>' . $mail->Body;
                Database::logAdd('WARN', "Testmodus: Mail geht an $empfaengerMail statt $empfaenger");
            }

            // DITIB-Logo als eingebettetes Bild
            $logoPfad = __DIR__ . '/../../static/img/logoDitib.png';
            if (file_exists($logoPfad)) {
                $mail->addEmbeddedImage($logoPfad, 'ditib_logo', 'logoDitib.png', 'base64', 'image/png');
            }

            $mail->addAttachment($pdfPfad, basename($pdfPfad));

            // Mitgliedsantrag als fester zweiter Anhang
            $beilagePfad = $this->cfg['beilage_pfad'] ?? '';
            if ($beilagePfad && file_exists($beilagePfad)) {
                $mail->addAttachment($beilagePfad, basename($beilagePfad));
            }

            $mail->send();

            // In Gesendete-Ordner ablegen (auch im Testmodus für Nachvollziehbarkeit)
            $this->inGesendeteAblegen($mail);

            Database::logAdd('OK', "Mail versendet an $empfaengerMail (" . basename($pdfPfad) . ")");
            return ['ok' => true, 'duplikat' => false, 'info' => ''];

        } catch (\Exception $e) {
            Database::logAdd('ERROR', "Mailversand-Fehler an $empfaenger: " . $e->getMessage());
            return ['ok' => false, 'duplikat' => false, 'info' => $e->getMessage()];
        }
    }

    // ── Adressanfrage bei fehlender Anschrift ──────────────────────────────
    // PayPal liefert nicht immer eine Adresse. Ohne Anschrift ist keine
    // Bescheinigung möglich – wir fragen den Spender, ob er eine wünscht,
    // und bitten in dem Fall um seine Anschrift.
    // Kein PDF-Anhang, keine Duplikat-Prüfung, kein Statuswechsel.
    // Wird ausschließlich manuell über den Button ausgelöst, nie automatisch.
    public function adresseAnfragen(array $spende, string $sprache = 'tr'): array
    {
        $empfaenger = $spende['email'] ?? '';
        if (!$empfaenger) {
            Database::logAdd('WARN', "Adressanfrage ohne E-Mail: {$spende['vorname']} {$spende['nachname']}");
            return ['ok' => false, 'info' => 'Keine E-Mail-Adresse'];
        }

        $testmodus = $this->cfg['testmodus'] ?? false;

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $this->cfg['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->cfg['smtp_user'];
            $mail->Password   = $this->cfg['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $this->cfg['smtp_port'];
            $mail->CharSet    = 'UTF-8';

            $name = trim($spende['vorname'] . ' ' . $spende['nachname']);

            $mail->addCustomHeader('X-Spende-Name', $name);
            $mail->addCustomHeader('X-Mail-Typ',    'adressanfrage');
            if (!empty($spende['id'])) {
                $mail->addCustomHeader('X-Spende-ID', (string) $spende['id']);
            }

            $empfaengerMail = $testmodus ? ($this->cfg['test_email'] ?? $empfaenger) : $empfaenger;

            $mail->setFrom($this->cfg['smtp_from_email'], $this->cfg['smtp_from_name']);
            $mail->addAddress($empfaengerMail, $name);
            // Antwort des Spenders soll im Postfach landen, nicht ins Leere laufen
            $mail->addReplyTo($this->cfg['smtp_from_email'], $this->cfg['smtp_from_name']);

            if ($sprache === 'de') {
                $mail->Subject = $this->cfg['smtp_subject_adresse_de'] ?? 'Spendenbescheinigung gewünscht? – Wir benötigen Ihre Anschrift';
                $mailtext      = $this->cfg['smtp_mailtext_adresse_de'] ?? '';
            } else {
                $mail->Subject = $this->cfg['smtp_subject_adresse_tr'] ?? 'Bağış makbuzu ister misiniz? – Adres bilgilerinize ihtiyacımız var';
                $mailtext      = $this->cfg['smtp_mailtext_adresse'] ?? '';
            }

            if (trim($mailtext) === '') {
                Database::logAdd('ERROR', 'Adressanfrage: kein Mailtext hinterlegt (Einstellungen prüfen)');
                return ['ok' => false, 'info' => 'Kein Mailtext hinterlegt – bitte in den Einstellungen pflegen'];
            }

            // Vorhandene / fehlende Adressfelder ermitteln
            $strasse = trim($spende['strasse'] ?? '');
            $plz     = trim($spende['plz']     ?? '');
            $ort     = trim($spende['ort']      ?? '');

            $ok  = '<span style="color:#27ae60;font-weight:700">✅</span>';
            $nok = '<span style="color:#c0392b;font-weight:700">❌</span>';

            if ($sprache === 'de') {
                $zeilen = [
                    ($strasse ? "$ok Straße: <strong>" . htmlspecialchars($strasse) . '</strong>'
                               : "$nok Straße und Hausnummer: <em style='color:#c0392b'>fehlt</em>"),
                    ($plz     ? "$ok PLZ: <strong>" . htmlspecialchars($plz) . '</strong>'
                               : "$nok Postleitzahl: <em style='color:#c0392b'>fehlt</em>"),
                    ($ort     ? "$ok Ort: <strong>" . htmlspecialchars($ort) . '</strong>'
                               : "$nok Ort: <em style='color:#c0392b'>fehlt</em>"),
                ];
                $adresseHinweis = ($strasse || $plz || $ort)
                    ? 'Ihre Zahlung erreichte uns mit <strong>unvollständiger Anschrift</strong>. Folgende Angaben benötigen wir noch:'
                    : 'Ihre Zahlung erreichte uns <strong>ohne Anschrift</strong>. Bitte teilen Sie uns Ihre vollständige Adresse mit:';
            } else {
                $zeilen = [
                    ($strasse ? "$ok Sokak/No: <strong>" . htmlspecialchars($strasse) . '</strong>'
                               : "$nok Sokak ve kapı numarası: <em style='color:#c0392b'>eksik</em>"),
                    ($plz     ? "$ok Posta kodu: <strong>" . htmlspecialchars($plz) . '</strong>'
                               : "$nok Posta kodu: <em style='color:#c0392b'>eksik</em>"),
                    ($ort     ? "$ok Şehir: <strong>" . htmlspecialchars($ort) . '</strong>'
                               : "$nok Şehir: <em style='color:#c0392b'>eksik</em>"),
                ];
                $adresseHinweis = ($strasse || $plz || $ort)
                    ? 'Ödemeniz bize <strong>eksik adres bilgisiyle</strong> ulaştı. Aşağıdaki bilgilere ihtiyacımız var:'
                    : 'Ödemeniz bize <strong>adres bilgisi olmadan</strong> ulaştı. Lütfen tam adresinizi bize iletiniz:';
            }

            $adresseStatus = '<p style="color:#555;font-size:13px;line-height:2;margin:0">'
                . implode('<br>', $zeilen) . '</p>';

            $htmlBody = str_replace(
                ['{name}', '{betrag}', '{datum}', '{adresse_hinweis}', '{adresse_status}'],
                [
                    htmlspecialchars($name),
                    number_format((float) $spende['betrag'], 2, ',', '.') . ' €',
                    htmlspecialchars($spende['datum'] ?? ''),
                    $adresseHinweis,
                    $adresseStatus,
                ],
                $mailtext
            );

            $mail->isHTML(true);
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

            if ($testmodus) {
                $mail->Subject = '[TESTMODUS] ' . $mail->Subject;
                $mail->Body    = '<div style="background:#fff3cd;border:2px solid #f0a500;border-radius:6px;'
                    . 'padding:12px 16px;margin-bottom:16px;font-family:Arial,sans-serif;font-size:13px;color:#7d4e00">'
                    . '<strong>⚠ TESTMODUS</strong> – Originalempfänger: ' . htmlspecialchars($empfaenger)
                    . '</div>' . $mail->Body;
                Database::logAdd('WARN', "Testmodus: Adressanfrage geht an $empfaengerMail statt $empfaenger");
            }

            $logoPfad = __DIR__ . '/../../static/img/logoDitib.png';
            if (file_exists($logoPfad)) {
                $mail->addEmbeddedImage($logoPfad, 'ditib_logo', 'logoDitib.png', 'base64', 'image/png');
            }

            $mail->send();
            $this->inGesendeteAblegen($mail);

            Database::logAdd('OK', "Adressanfrage versendet an $empfaengerMail (Spende #{$spende['id']})");
            return ['ok' => true, 'info' => ''];

        } catch (\Exception $e) {
            Database::logAdd('ERROR', "Adressanfrage-Fehler an $empfaenger: " . $e->getMessage());
            return ['ok' => false, 'info' => $e->getMessage()];
        }
    }
}
