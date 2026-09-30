<?php
namespace App\Mail;

use App\DB\Database;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Report-Mail nach dem Cron-Job.
 * Enthält das Ergebnis des Laufs und den aktuellen Bestand
 * (neu / freigegeben / versendet).
 */
class Report
{
    private array $cfg;

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? require __DIR__ . '/../../config/settings.php';
    }

    /**
     * @param array $lauf Kennzahlen des Laufs:
     *                    pdfs, verschoben, uebersprungen, fehler, paypal, dauer
     */
    public function senden(array $lauf): bool
    {
        if (empty($this->cfg['report_aktiv'])) {
            return false;
        }

        $empfaenger = $this->cfg['report_email'] ?: ($this->cfg['smtp_from_email'] ?? '');
        if (!$empfaenger) {
            Database::logAdd('WARN', 'Report-Mail: keine Empfängeradresse konfiguriert');
            return false;
        }

        $zaehler   = Database::spendeZaehler();
        $testmodus = !empty($this->cfg['testmodus']);

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

            $mail->setFrom($this->cfg['smtp_from_email'], $this->cfg['smtp_from_name']);
            $mail->addAddress($empfaenger);

            $mail->Subject = sprintf(
                '%s[Spendenportal] Report %s – %d neu, %d freigegeben, %d versendet',
                $testmodus ? '[TESTMODUS] ' : '',
                date('d.m.Y'),
                $zaehler['neu'],
                $zaehler['freigegeben'],
                $zaehler['versendet']
            );

            $mail->isHTML(true);
            $mail->Body    = $this->body($lauf, $zaehler, $testmodus);
            $mail->AltBody = $this->text($lauf, $zaehler, $testmodus);

            $mail->send();
            Database::logAdd('OK', "Report-Mail versendet an $empfaenger");
            return true;

        } catch (\Exception $e) {
            Database::logAdd('ERROR', 'Report-Mail fehlgeschlagen: ' . $e->getMessage());
            return false;
        }
    }

    // ── HTML-Body ──────────────────────────────────────────────────────────
    private function body(array $lauf, array $zaehler, bool $testmodus): string
    {
        $bestand = [
            ['Neu (warten auf Freigabe)', $zaehler['neu'],         '#f0a500'],
            ['Freigegeben (Versand offen)', $zaehler['freigegeben'], '#3b82f6'],
            ['Versendet',                 $zaehler['versendet'],   '#22c55e'],
        ];

        $zeilenBestand = '';
        foreach ($bestand as [$label, $wert, $farbe]) {
            $zeilenBestand .= '<tr>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee">' . $label . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:right;'
                . 'font-weight:bold;font-size:16px;color:' . $farbe . '">' . $wert . '</td>'
                . '</tr>';
        }

        $lauf_zeilen = [
            'Neue Spenden / PDFs erstellt' => $lauf['pdfs'] ?? 0,
            'Mails verschoben'             => $lauf['verschoben'] ?? 0,
            'Mails übersprungen'           => $lauf['uebersprungen'] ?? 0,
            'Fehler beim Verschieben'      => $lauf['fehler'] ?? 0,
        ];
        if (isset($lauf['paypal'])) {
            $lauf_zeilen['PayPal-API: neue Transaktionen'] = $lauf['paypal'];
        }

        $zeilenLauf = '';
        foreach ($lauf_zeilen as $label => $wert) {
            $zeilenLauf .= '<tr>'
                . '<td style="padding:6px 12px;border-bottom:1px solid #eee;color:#555">' . $label . '</td>'
                . '<td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:right;font-weight:bold">' . $wert . '</td>'
                . '</tr>';
        }

        $warnung = $testmodus
            ? '<div style="background:#fff3cd;border:1px solid #f0a500;border-radius:6px;padding:10px 14px;'
            . 'margin-bottom:16px;font-size:13px;color:#7d4e00"><strong>⚠ TESTMODUS aktiv</strong> – '
            . 'Versandmails gehen an ' . htmlspecialchars($this->cfg['test_email'] ?? '?') . '</div>'
            : '';

        return '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;max-width:600px">'
            . '<h2 style="margin:0 0 4px">Spendenportal – Cron-Report</h2>'
            . '<p style="margin:0 0 16px;color:#777;font-size:13px">Lauf vom '
            . date('d.m.Y \u\m H:i') . ' Uhr · Dauer: ' . ($lauf['dauer'] ?? '?') . 's</p>'
            . $warnung
            . '<h3 style="margin:0 0 6px;font-size:15px">Aktueller Bestand</h3>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;'
            . 'border:1px solid #eee;border-radius:6px">' . $zeilenBestand . '</table>'
            . '<h3 style="margin:0 0 6px;font-size:15px">Dieser Lauf</h3>'
            . '<table style="width:100%;border-collapse:collapse;border:1px solid #eee;border-radius:6px">'
            . $zeilenLauf . '</table>'
            . '<p style="margin-top:20px;color:#999;font-size:12px">'
            . 'Automatisch erzeugt vom Spendenportal. Abschaltbar unter Einstellungen → Report-Mail.</p>'
            . '</div>';
    }

    // ── Text-Fallback ──────────────────────────────────────────────────────
    private function text(array $lauf, array $zaehler, bool $testmodus): string
    {
        $zeilen = [
            'Spendenportal – Cron-Report vom ' . date('d.m.Y H:i'),
            '',
            'Aktueller Bestand:',
            '  Neu:         ' . $zaehler['neu'],
            '  Freigegeben: ' . $zaehler['freigegeben'],
            '  Versendet:   ' . $zaehler['versendet'],
            '',
            'Dieser Lauf:',
            '  Neue PDFs:        ' . ($lauf['pdfs'] ?? 0),
            '  Mails verschoben: ' . ($lauf['verschoben'] ?? 0),
            '  Übersprungen:     ' . ($lauf['uebersprungen'] ?? 0),
            '  Fehler:           ' . ($lauf['fehler'] ?? 0),
        ];
        if (isset($lauf['paypal'])) {
            $zeilen[] = '  PayPal-API:       ' . $lauf['paypal'];
        }
        $zeilen[] = '  Dauer:            ' . ($lauf['dauer'] ?? '?') . 's';
        if ($testmodus) {
            $zeilen[] = '';
            $zeilen[] = '⚠ TESTMODUS aktiv';
        }
        return implode("\n", $zeilen);
    }
}
