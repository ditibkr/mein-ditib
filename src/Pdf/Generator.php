<?php
namespace App\Pdf;

use App\DB\Database;

class Generator {

    private array $cfg;

    public function __construct() {
        $this->cfg = require __DIR__ . '/../../config/settings.php';
    }

    // ── Betrag in deutsche Wörter ──
    private function betragInWorten(float $betrag): string {
        $ganzzahl = (int)round($betrag);
        $wort = ucfirst($this->zahlenWort($ganzzahl));
        return $wort;
    }

    private function zahlenWort(int $n): string {
        if ($n === 0) return 'null';
        $e  = ['','ein','zwei','drei','vier','fünf','sechs','sieben','acht','neun',
               'zehn','elf','zwölf','dreizehn','vierzehn','fünfzehn','sechzehn',
               'siebzehn','achtzehn','neunzehn'];
        $z  = ['','','zwanzig','dreißig','vierzig','fünfzig','sechzig','siebzig','achtzig','neunzig'];
        if ($n < 20) return $e[$n];
        if ($n < 100) {
            $rest = $n % 10;
            return ($rest ? $e[$rest] . 'und' : '') . $z[intdiv($n, 10)];
        }
        if ($n < 1000) {
            $h = intdiv($n, 100);
            $r = $n % 100;
            return $e[$h] . 'hundert' . ($r ? $this->zahlenWort($r) : '');
        }
        if ($n < 1000000) {
            $t = intdiv($n, 1000);
            $r = $n % 1000;
            return $this->zahlenWort($t) . 'tausend' . ($r ? $this->zahlenWort($r) : '');
        }
        return (string)$n;
    }

    // ── Datum normalisieren: immer TT.MM.JJJJ ──
    private function normDatum(string $d): string {
        // ISO "2026-03-06" → "06.03.2026"
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($d), $m)) {
            return "{$m[3]}.{$m[2]}.{$m[1]}";
        }
        // Bereits deutsches Format
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', trim($d))) {
            return trim($d);
        }
        return date('d.m.Y');
    }

    // ── Dateiname erstellen ──
    // $ersetzt = Pfad einer bestehenden PDF, die überschrieben werden darf
    // (Korrektur-Lauf). Nur diese eine Datei blockiert den Namen nicht.
    public function dateiname(array $s, ?string $ersetzt = null): string {
        $datum = $this->normDatum($s['datum'] ?? '');
        $d = str_replace('.', '', $datum); // DDMMYYYY
        if (strlen($d) !== 8) $d = date('YmdHis');

        $clean = function(string $str): string {
            $str = mb_strtolower($str);
            $str = str_replace(['ä','ö','ü','ß',' '], ['ae','oe','ue','ss','_'], $str);
            return preg_replace('/[^a-z0-9_]/', '', $str);
        };

        $base = "{$d}_spendenbescheinigung_{$clean($s['vorname'])}_{$clean($s['nachname'])}";

        // Duplikat-Schutz: wenn Datei schon existiert, Suffix anhängen
        $outDir  = rtrim($this->cfg['output_dir'], '/');
        $alt     = $ersetzt ? realpath($ersetzt) : false;
        $file    = $base . '.pdf';
        $i = 1;
        while (file_exists($outDir . '/' . $file)) {
            // Die zu ersetzende PDF darf überschrieben werden – kein Suffix
            if ($alt && realpath($outDir . '/' . $file) === $alt) break;
            $file = $base . '_' . $i . '.pdf';
            $i++;
        }
        return $file;
    }

    // ── Guilloche-Sicherheitsstreifen ──
    // Zeichnet überlagerte Sinuswellen hinter einem Feld.
    // Wird nach dem Template-Import, aber VOR dem Text gezeichnet,
    // sodass ein weißes Deckfeld das Muster sichtbar unterbricht.
    private function guillocheStreifen(
        \setasign\Fpdi\Tcpdf\Fpdi $pdf,
        float $x, float $y, float $w, float $h
    ): void {
        $pdf->SetDrawColor(175, 175, 175); // Hellgrau – S/W-drucksicher
        $pdf->SetLineWidth(0.12);

        $wellen  = 8;     // Anzahl übereinander gelagerter Kurven
        $step    = 0.35;  // X-Schrittweite in mm (Auflösung)
        $schritte = (int)($w / $step);
        $yMid    = $y + $h / 2;
        $margin  = 0.4;   // Abstand zu Feldrand oben/unten

        for ($wi = 0; $wi < $wellen; $wi++) {
            // Leicht unterschiedliche Frequenzen + Amplituden → Interferenzmuster
            $amplitude = ($h / 2 - $margin) * (0.55 + 0.3 * (($wi % 3) / 2));
            $periode   = 7.5 + $wi * 0.7;
            $phase     = $wi * (2 * M_PI / $wellen);
            // Gleichmäßig über Feldhöhe verteilen
            $yCenter   = $yMid + ($h * 0.28) * (($wi / ($wellen - 1)) * 2 - 1);

            $xi = $x;
            $yi = $yCenter + $amplitude * sin($phase);
            $yi = max($y + $margin, min($y + $h - $margin, $yi));

            for ($i = 1; $i <= $schritte; $i++) {
                $xn = $x + $step * $i;
                if ($xn > $x + $w) $xn = $x + $w;
                $yn = $yCenter + $amplitude * sin($xn / $periode * 2 * M_PI + $phase);
                $yn = max($y + $margin, min($y + $h - $margin, $yn));
                $pdf->Line($xi, $yi, $xn, $yn);
                $xi = $xn;
                $yi = $yn;
            }
        }

        // Zeichenstatus zurücksetzen
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
    }

    // ── Hauptfunktion: PDF erstellen ──
    // $ersetzt = Pfad der bisherigen PDF (bei Korrekturen). Sie wird überschrieben
    // bzw. entfernt, wenn sich der Dateiname durch die Korrektur ändert.
    public function erstellen(array $s, ?string $ersetzt = null): ?string {
        // Auch ausländische Spender erhalten auf Wunsch eine Bescheinigung
        $outDir  = rtrim($this->cfg['output_dir'], '/');
        if (!is_dir($outDir)) mkdir($outDir, 0775, true);

        // Datum immer normalisieren bevor es verwendet wird
        $s['datum'] = $this->normDatum($s['datum'] ?? '');
        $jahr = substr($s['datum'], 6, 4); // JJJJ aus TT.MM.JJJJ

        // Art korrigieren falls noch ISO-Jahr enthalten
        if (isset($s['art'])) {
            $s['art'] = preg_replace('/für \d{1,2}-\d{2}$/', "für $jahr", $s['art']);
        }

        $dateiname = $this->dateiname($s, $ersetzt);
        $outFile   = $outDir . '/' . $dateiname;
        $tmpFile   = $outFile . '.tmp.pdf';
        $template  = $this->cfg['template_path'];

        if (!file_exists($template)) {
            Database::logAdd('ERROR', "Vorlage nicht gefunden: $template");
            return null;
        }

        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            $pdf->SetCreator('SpendenPortal DITIB Krefeld');
            $pdf->SetAuthor('DITIB Krefeld Fatih Cami');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Seite importieren
            $pdf->AddPage();
            $pdf->setSourceFile($template);
            $tplId = $pdf->importPage(1);
            $pdf->useTemplate($tplId, 0, 0, 210, 297); // A4 in mm

            // Schrift
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(0, 0, 0);

            // pt → mm (pdftotext liefert pt von oben, TCPDF arbeitet in mm von oben)
            $pt2mm = fn(float $pt): float => $pt * 25.4 / 72;

            // ── Guilloche hinter den sicherheitsrelevanten Feldern ──
            // Muss VOR dem Text gezeichnet werden, damit Text darüberliegt.
            // Wer im PDF-Editor ein weißes Rechteck über den Text legt,
            // bricht das Muster sichtbar ab (weißes Loch).
            // Koordinaten aus Vorlage ausgemessen (mm, TCPDF-System: Y von oben)
            if (!empty($this->cfg['pdf_guilloche_aktiv'])) {
                $this->guillocheStreifen($pdf,  25.1,  79.1, 159.8, 26.2); // Name + Adresse
                $this->guillocheStreifen($pdf,  65.5, 115.8,  45.5,  7.3); // Betrag
                $this->guillocheStreifen($pdf,  65.3, 124.5, 118.7,  7.7); // Betrag in Worten
            }

            // ── <SPENDER>: Name + Adresse ──
            $name    = trim($s['vorname'] . ' ' . $s['nachname']);
            $strasse = $s['strasse'] ?? '';
            $plzOrt  = trim(($s['plz'] ?? '') . ' ' . ($s['ort'] ?? ''));

            $x = $pt2mm(74.4);
            $y = $pt2mm(228.96); // +2pt
            $pdf->SetXY($x, $y);
            $pdf->Cell(120, 6, $name, 0, 1, 'L');
            if ($strasse) { $pdf->SetXY($x, $y + 6);  $pdf->Cell(120, 6, $strasse, 0, 1, 'L'); }
            if ($plzOrt)  { $pdf->SetXY($x, $y + 12); $pdf->Cell(120, 6, $plzOrt,  0, 1, 'L'); }

            // ── <SPENDENART> ──
            $pdf->SetXY($pt2mm(170.7), $pt2mm(310.0)); // +2pt
            $pdf->Cell(120, 5, $s['art'] ?? "Geldzuwendung für $jahr", 0, 0, 'L');

            // ── <BETRAG> mit 5 Strichen vor + nach ──
            $betrag    = (float)($s['betrag'] ?? 0);
            $betragStr = '***** ' . number_format($betrag, 2, ',', '.') . ' *****';
            $pdf->SetXY($pt2mm(193.1), $pt2mm(330.0)); // +2pt
            $pdf->Cell(100, 5, $betragStr, 0, 0, 'L');

            // ── <BETRAGWORT> mit 5 Strichen ──
            $betragWort = '***** ' . $this->betragInWorten($betrag) . ' *****';
            $pdf->SetXY($pt2mm(192.7), $pt2mm(354.0)); // +2pt
            $pdf->Cell(180, 5, $betragWort, 0, 0, 'L');

            // ── <DATUM> ──
            $pdf->SetXY($pt2mm(133.3), $pt2mm(545.0)); // +2pt
            $pdf->Cell(80, 5, $s['datum'], 0, 0, 'L');

            // Temporäre Datei
            $pdf->Output($tmpFile, 'F');

            // ── qpdf: Schreibschutz AUS (druckbar) ──
            // --print=full erlaubt Drucken, --modify=none verhindert Bearbeiten
            // Alte Version entfernen, sonst bliebe sie bei einem qpdf-Fehler stehen
            // und würde fälschlich als "neu erstellt" durchgehen
            if (file_exists($outFile)) @unlink($outFile);

            // Owner-Passwort (Schreibschutz) aus den Einstellungen – nicht im Code.
            // Fallback auf das bisherige Schema, damit ältere PDFs weiter passen.
            $ownerPw = $this->cfg['pdf_owner_passwort'] ?? '';
            if ($ownerPw === '') {
                $ownerPw = 'DITIB_OWNER_' . $jahr;
            }
            $cmd = sprintf(
                'qpdf --encrypt "" %s 256 --print=full --modify=none --extract=n -- %s %s 2>&1',
                escapeshellarg($ownerPw),
                escapeshellarg($tmpFile),
                escapeshellarg($outFile)
            );
            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($outFile)) {
                rename($tmpFile, $outFile);
                Database::logAdd('WARN', "qpdf nicht verfügbar – PDF ohne Schreibschutz");
            } else {
                @unlink($tmpFile);
            }

            // Bei Korrekturen mit geändertem Namen (z.B. Name/Datum korrigiert)
            // die alte Datei entfernen – sonst bleibt eine Leiche im output-Ordner
            if ($ersetzt && file_exists($ersetzt) && realpath($ersetzt) !== realpath($outFile)) {
                @unlink($ersetzt);
                Database::logAdd('INFO', 'Alte PDF ersetzt: ' . basename($ersetzt));
            }

            Database::logAdd('OK', "PDF erstellt: $dateiname");
            return $outFile;

        } catch (\Exception $e) {
            Database::logAdd('ERROR', "PDF-Fehler ({$s['vorname']} {$s['nachname']}): " . $e->getMessage());
            @unlink($tmpFile);
            return null;
        }
    }
}
