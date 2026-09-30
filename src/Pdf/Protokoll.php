<?php
namespace App\Pdf;

/**
 * PDF-Ausgabe eines Sitzungsprotokolls – Layout nach der bisherigen Word-Vorlage:
 * grauer Vereinsname als Kopf, Anwesende/Datum als Tabelle, Trennlinie,
 * nummerierte TOPs (fett) mit a) b) c) Unterpunkten, Seitenzahl unten rechts.
 */
class Protokoll extends \TCPDF
{
    private const ML = 25;   // linker Rand in mm (wie Word-Vorlage)
    private const MR = 20;   // rechter Rand
    private const LABEL_X = 70; // Werte-Spalte bei Anwesende / Datum

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8');
        $this->SetCreator('SpendenPortal DITIB Krefeld');
        $this->SetAuthor('DITIB Türkisch-Islamische Gemeinde zu Krefeld e.V.');
        $this->setPrintHeader(false);
        $this->SetMargins(self::ML, 20, self::MR);
        $this->SetAutoPageBreak(true, 22);
    }

    // Seitenzahl unten rechts (wie in der Vorlage)
    public function Footer(): void
    {
        $this->SetY(-15);
        // DejaVu deckt auch türkische Zeichen ab (ş, ğ, İ …) – helvetica nicht
        $this->SetFont('dejavusans', '', 9);
        $this->SetTextColor(60, 60, 60);
        $this->Cell(0, 6, (string) $this->getAliasNumPage(), 0, 0, 'R');
    }

    private static function datumDe(string $iso): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) ? "$m[3].$m[2].$m[1]" : $iso;
    }

    /**
     * Baut die PDF aus einer Zeile der Tabelle `protokolle` und liefert den Binärinhalt.
     */
    public function erstellen(array $p): string
    {
        $teilnehmer = json_decode($p['teilnehmer'] ?: '[]', true) ?: [];
        $anwesende  = array_values(array_filter($teilnehmer, fn ($t) => !empty($t['anwesend'])));
        $tops       = json_decode($p['tops'] ?: '[]', true) ?: [];

        $this->SetTitle('Protokoll zur Vorstandssitzung ' . self::datumDe($p['datum']));
        $this->AddPage();

        // ── Kopf ──
        $this->SetFont('dejavusans', 'B', 15);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 9, 'DITIB Türkisch-Islamische Gemeinde zu Krefeld e.V.', 0, 1);
        $this->Ln(6);

        $this->SetFont('dejavusans', '', 12.5);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 7, 'Protokoll zur Vorstandssitzung', 0, 1);
        $this->Ln(4);

        // ── Anwesende ──
        $this->SetFont('dejavusans', 'B', 10);
        $labelBreite = self::LABEL_X - self::ML;
        $wertBreite  = 210 - self::MR - self::LABEL_X;
        $this->Cell($labelBreite, 5.5, 'Anwesende:', 0, 0);
        $this->SetFont('dejavusans', '', 10);
        if (!$anwesende) {
            $this->Cell($wertBreite, 5.5, '–', 0, 1);
        } else {
            foreach ($anwesende as $i => $t) {
                if ($i > 0) $this->SetX(self::LABEL_X);
                $zeile = $t['name'] . (($t['funktion'] ?? '') !== '' ? ', ' . $t['funktion'] : '');
                $this->MultiCell($wertBreite, 5.5, $zeile, 0, 'L');
            }
        }
        $this->Ln(3);

        // ── Datum / Uhrzeit ──
        $this->SetFont('dejavusans', 'B', 10);
        $this->Cell($labelBreite, 5.5, 'Datum / Uhrzeit:', 0, 0);
        $this->SetFont('dejavusans', '', 10);
        $zeit = trim(($p['beginn'] ?: '') . (($p['ende'] ?? '') !== '' ? ' – ' . $p['ende'] : ''));
        $this->Cell($wertBreite, 5.5, self::datumDe($p['datum']) . ($zeit !== '' ? "  /  $zeit" : ''), 0, 1);
        $this->Ln(3);

        // ── Trennlinie ──
        $this->SetDrawColor(120, 120, 120);
        $this->SetLineWidth(0.3);
        $this->Line(self::ML, $this->GetY(), 210 - self::MR, $this->GetY());
        $this->Ln(6);

        // ── Tagesordnungspunkte ──
        $numBreite  = 8;   // "1)"
        $subEinzug  = 8;   // Einzug der a)-Ebene unter dem Titel
        $subBreite  = 7;   // "a)"
        foreach ($tops as $nr => $top) {
            $titel = trim((string) ($top['titel'] ?? ''));
            $subs  = array_values(array_filter(array_map('trim', (array) ($top['subs'] ?? [])), fn ($s) => $s !== ''));
            if ($titel === '' && !$subs) continue;

            // TOP nicht direkt an der Seitenunterkante beginnen
            if ($this->GetY() > 250) $this->AddPage();

            $this->SetFont('dejavusans', 'B', 10);
            $this->Cell($numBreite, 5.5, ($nr + 1) . ')', 0, 0);
            $this->MultiCell(0, 5.5, $titel !== '' ? $titel : '(ohne Titel)', 0, 'L');

            $this->SetFont('dejavusans', '', 10);
            foreach ($subs as $si => $s) {
                $this->SetX(self::ML + $subEinzug);
                $this->Cell($subBreite, 5.5, chr(97 + ($si % 26)) . ')', 0, 0);
                // linksbündig statt Blocksatz – TCPDF zieht sonst auch kurze Zeilen auseinander
                $this->MultiCell(210 - self::MR - self::ML - $subEinzug - $subBreite, 5.5, $s, 0, 'L');
                $this->Ln(0.5);
            }
            $this->Ln(4);
        }

        return $this->Output('', 'S');
    }

    public static function dateiname(array $p): string
    {
        return 'protokoll_vorstandssitzung_' . str_replace('.', '', self::datumDe($p['datum'])) . '.pdf';
    }
}
