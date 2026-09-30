<?php
namespace App\Pdf;

use Smalot\PdfParser\Parser;

/**
 * Liest eine bestehende (manuell erstellte) Spendenbescheinigung aus.
 *
 * Vorlage: pdf_templates/Geldzuwendung_template_alt.pdf – ab 2024 die einzige.
 *
 * Ablauf:
 *   1. qpdf --decrypt in eine TEMPORÄRE Kopie. Die alten PDFs haben nur ein
 *      Owner-Passwort (Schreibschutz), das User-Passwort ist leer – zum Lesen wird
 *      also kein Passwort gebraucht. Ist doch eins gesetzt, greift pdf_import_passwort.
 *   2. Text mit Koordinaten auslesen (getDataTm).
 *   3. Felder über ANKERTEXTE finden ("Betrag der Zuwendung:"), nicht über feste
 *      Y-Werte – das überlebt kleine Layout-Verschiebungen.
 *   4. Plausibilisieren. Die Vorlage enthält den Betrag zweimal (Ziffern +
 *      Buchstaben) – das ist eine geschenkte Gegenprobe.
 *
 * Das Original-PDF wird NIE verändert. Die entschlüsselte Kopie wird nach dem
 * Lesen gelöscht.
 */
class Leser
{
    private array $cfg;

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? require __DIR__ . '/../../config/settings.php';
    }

    /**
     * @return array{
     *   ok: bool, fehler: string, warnungen: string[],
     *   vorname: string, nachname: string, strasse: string, plz: string, ort: string,
     *   betrag: float, betrag_wort: string, datum: string, art: string, jahr: string,
     *   text: string
     * }
     */
    public function auslesen(string $pdfPfad): array
    {
        $leer = [
            'ok' => false, 'fehler' => '', 'warnungen' => [],
            'vorname' => '', 'nachname' => '', 'strasse' => '', 'plz' => '', 'ort' => '',
            'betrag' => 0.0, 'betrag_wort' => '', 'datum' => '', 'art' => '', 'jahr' => '',
            'text' => '',
        ];

        if (!file_exists($pdfPfad)) {
            return ['fehler' => 'Datei nicht gefunden'] + $leer;
        }

        $tmp = sys_get_temp_dir() . '/import_' . bin2hex(random_bytes(6)) . '.pdf';

        try {
            if (!$this->entschluesseln($pdfPfad, $tmp)) {
                return ['fehler' => 'PDF konnte nicht entschlüsselt werden (Passwort?)'] + $leer;
            }

            $seite = (new Parser())->parseFile($tmp)->getPages()[0] ?? null;
            if (!$seite) {
                return ['fehler' => 'PDF enthält keine Seite'] + $leer;
            }

            // Textblöcke mit Koordinaten
            $bloecke = [];
            foreach ($seite->getDataTm() as $e) {
                $txt = trim($e[1]);
                if ($txt === '') {
                    continue;
                }
                $bloecke[] = ['x' => (float) $e[0][4], 'y' => (float) $e[0][5], 'text' => $txt];
            }
            if (!$bloecke) {
                // Kein Text = vermutlich ein Scan. Raten wäre hier gefährlich.
                return ['fehler' => 'Kein Text im PDF (Scan?) – bitte manuell erfassen'] + $leer;
            }

            $zeilen   = $this->zeilenBilden($bloecke);
            $volltext = $seite->getText();
            $daten    = $this->felderLesen($zeilen);
            $daten['text'] = $volltext;

            return $daten + $leer;

        } catch (\Throwable $e) {
            return ['fehler' => 'Lesefehler: ' . $e->getMessage()] + $leer;
        } finally {
            // Die entschlüsselte Kopie hat keinen Schreibschutz mehr – sie darf nicht liegen bleiben
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    // ── Schreibschutz entfernen (nur in der temporären Kopie) ──
    private function entschluesseln(string $quelle, string $ziel): bool
    {
        // Erst ohne Passwort – bei den alten PDFs ist das User-Passwort leer
        $cmd = sprintf('qpdf --decrypt %s %s 2>&1', escapeshellarg($quelle), escapeshellarg($ziel));
        exec($cmd, $out, $code);
        if ($code === 0 && file_exists($ziel)) {
            return true;
        }

        // Fallback: doch ein Öffnen-Passwort gesetzt
        $pw = $this->cfg['pdf_import_passwort'] ?? '';
        if ($pw !== '') {
            $cmd = sprintf(
                'qpdf --password=%s --decrypt %s %s 2>&1',
                escapeshellarg($pw),
                escapeshellarg($quelle),
                escapeshellarg($ziel)
            );
            exec($cmd, $out2, $code2);
            return $code2 === 0 && file_exists($ziel);
        }

        return false;
    }

    /**
     * Blöcke zu Zeilen clustern.
     *
     * Der Parser liefert zersplitterte Blöcke ("Einhundert" + "sie" + "ben"), und
     * Label und Wert liegen minimal versetzt (Anker y=480,5 / Wert y=478,7).
     * Deshalb: nach Y gruppieren (Toleranz 4), innerhalb der Zeile nach X sortieren.
     *
     * @return array<int, array{y: float, text: string}> von oben nach unten
     */
    private function zeilenBilden(array $bloecke): array
    {
        usort($bloecke, fn ($a, $b) => $b['y'] <=> $a['y']);

        $zeilen = [];
        foreach ($bloecke as $b) {
            $treffer = null;
            foreach ($zeilen as $i => $z) {
                if (abs($z['y'] - $b['y']) <= 4.0) {
                    $treffer = $i;
                    break;
                }
            }
            if ($treffer === null) {
                $zeilen[] = ['y' => $b['y'], 'teile' => [$b]];
            } else {
                $zeilen[$treffer]['teile'][] = $b;
            }
        }

        $fertig = [];
        foreach ($zeilen as $z) {
            usort($z['teile'], fn ($a, $b) => $a['x'] <=> $b['x']);
            $fertig[] = [
                'y'    => $z['y'],
                'text' => trim(preg_replace('/\s+/u', ' ', implode(' ', array_column($z['teile'], 'text')))),
            ];
        }

        return $fertig;
    }

    // Erste Zeile, deren Text zum Muster passt
    private function zeileMit(array $zeilen, string $muster): string
    {
        foreach ($zeilen as $z) {
            if (preg_match($muster, $z['text'], $m)) {
                return trim($m[1] ?? $z['text']);
            }
        }
        return '';
    }

    // ── Felder über Ankertexte einsammeln ──
    private function felderLesen(array $zeilen): array
    {
        $warnungen = [];

        // Betrag: "Betrag der Zuwendung: 107,39 ,- ------- €"
        $betragRoh = $this->zeileMit($zeilen, '/Betrag\s+der\s+Zuwendung\s*:?\s*(.*)/u');
        $betrag = 0.0;
        if (preg_match('/(\d{1,3}(?:\.\d{3})*|\d+)\s*,\s*(\d{2})/u', $betragRoh, $m)) {
            $betrag = (float) (str_replace('.', '', $m[1]) . '.' . $m[2]);
        } elseif (preg_match('/(\d{1,3}(?:\.\d{3})*|\d+)\s*,?\s*-/u', $betragRoh, $m)) {
            $betrag = (float) str_replace('.', '', $m[1]);   // glatter Betrag ohne Cent
        }
        if ($betrag <= 0) {
            $warnungen[] = 'Betrag nicht erkannt';
        }

        // Betrag in Buchstaben – die eingebaute Gegenprobe der Vorlage
        $wort = $this->zeileMit($zeilen, '/Betrag\s+in\s+Buchstaben\s*:?\s*(.*)/u');
        $wort = trim(preg_replace('/[-\s]+$/u', '', $wort));

        if ($wort === '') {
            $warnungen[] = 'Betrag in Buchstaben fehlt – Gegenprobe nicht möglich';
        } elseif ($betrag > 0 && !$this->betragPasstZuWort($betrag, $wort)) {
            $warnungen[] = sprintf(
                'Gegenprobe fehlgeschlagen: %s € vs. "%s"',
                number_format($betrag, 2, ',', '.'),
                $wort
            );
        }

        // Art der Zuwendung – trägt auch "Mitgliedsbeitrag für 2024"
        $artRoh = $this->zeileMit($zeilen, '/Art\s+der\s+Zuwendung\s*:?\s*(.*)/u');
        $artRoh = preg_replace('/(\d)\s+(\d)/u', '$1$2', $artRoh);   // "202 6" → "2026"
        $art    = $artRoh !== '' ? trim($artRoh) : 'Geldzuwendung';

        // Ausstellungsdatum: die unterste Zeile, die nur aus einem Datum besteht
        $datum = '';
        foreach ($zeilen as $z) {
            $t = preg_replace('/\s+/u', '', $z['text']);
            if (preg_match('/^(\d{2}\.\d{2}\.\d{4})$/', $t, $m)) {
                $datum = $m[1];   // Zeilen kommen von oben nach unten → letzter Treffer gewinnt
            }
        }
        if ($datum === '') {
            $warnungen[] = 'Datum nicht erkannt';
        }

        // Jahr aus der Art-Zeile – aber nur ein plausibles Kalenderjahr übernehmen.
        // Sonst fischt die Regex Zahlen aus dem Fließtext (etwa die Steuer-Nr. 117/5875/0404).
        $jahr = $datum ? substr($datum, 6, 4) : '';
        if (preg_match('/\b(20[1-4]\d)\b/', $art, $j)) {
            $jahr = $j[1];
        }

        // Fehlt die Betragszeile ganz, ist es vermutlich ein anderes Formular
        if ($betragRoh === '') {
            $warnungen[] = 'Formular nicht erkannt – Zeile "Betrag der Zuwendung" fehlt';
        }

        [$vorname, $nachname, $strasse, $plz, $ort] = $this->anschriftLesen($zeilen, $warnungen);

        return [
            'ok'          => $betrag > 0 && $datum !== '' && $nachname !== '',
            'fehler'      => '',
            'warnungen'   => $warnungen,
            'vorname'     => $vorname,
            'nachname'    => $nachname,
            'strasse'     => $strasse,
            'plz'         => $plz,
            'ort'         => $ort,
            'betrag'      => $betrag,
            'betrag_wort' => $wort,
            'datum'       => $datum,
            'art'         => $art,
            'jahr'        => $jahr,
        ];
    }

    /**
     * Anschrift: der Block unter dem Anker "Name und Anschrift des Zuwendenden:".
     * Überschrift und Datumszeile werden übersprungen.
     */
    private function anschriftLesen(array $zeilen, array &$warnungen): array
    {
        $ab = null;
        foreach ($zeilen as $i => $z) {
            if (str_contains($z['text'], 'Name und Anschrift')) {
                $ab = $i;
                break;
            }
        }
        if ($ab === null) {
            $warnungen[] = 'Anker "Name und Anschrift" fehlt – anderes Formular?';
            return ['', '', '', '', ''];
        }

        $adresse = [];
        foreach (array_slice($zeilen, $ab + 1) as $z) {
            $t = trim($z['text']);
            if ($t === '' || str_contains($t, 'Zuwendungsbestätigung')) {
                continue;
            }
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', preg_replace('/\s+/u', '', $t))) {
                continue;   // Ausstellungsdatum
            }
            $adresse[] = $t;
        }

        $name    = $adresse[0] ?? '';
        $strasse = $adresse[1] ?? '';
        $plzOrt  = $adresse[2] ?? '';

        $plz = $ort = '';
        if (preg_match('/(\d{5})\s+(.+)/u', $plzOrt, $m)) {
            $plz = $m[1];
            $ort = trim($m[2]);
        } elseif ($plzOrt !== '') {
            $ort = trim($plzOrt);
            $warnungen[] = 'PLZ nicht erkannt';
        }

        // Letztes Wort = Nachname
        $teile    = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $nachname = array_pop($teile) ?? '';
        $vorname  = implode(' ', $teile);

        if ($nachname === '') {
            $warnungen[] = 'Name nicht erkannt';
        }
        if ($strasse === '') {
            $warnungen[] = 'Straße nicht erkannt';
        }

        return [$vorname, $nachname, $strasse, $plz, $ort];
    }


    /**
     * Gegenprobe: Passt der Ziffernbetrag zum Betrag in Buchstaben?
     * Verglichen wird nur der EURO-Teil (die Vorlage schreibt die Cent nicht aus).
     */
    private function betragPasstZuWort(float $betrag, string $wort): bool
    {
        $wort = mb_strtolower(str_replace(['-', ' '], '', $wort));
        if ($wort === '') {
            return true;   // nichts zu prüfen
        }
        $euro = (int) floor($betrag);
        $erwartet = str_replace(' ', '', mb_strtolower($this->zahlAlsWort($euro)));

        return $wort === $erwartet;
    }

    // ── Deutsche Zahlwörter bis 999.999 ──
    private function zahlAlsWort(int $n): string
    {
        if ($n === 0) return 'null';

        $einer = ['', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun',
            'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn', 'siebzehn',
            'achtzehn', 'neunzehn'];
        $zehner = ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'];

        $bis999 = function (int $z) use ($einer, $zehner, &$bis999): string {
            $t = '';
            if ($z >= 100) {
                $h = intdiv($z, 100);
                $t .= ($h === 1 ? 'ein' : $einer[$h]) . 'hundert';
                $z %= 100;
            }
            if ($z >= 20) {
                $e = $z % 10;
                $t .= ($e > 0 ? $einer[$e] . 'und' : '') . $zehner[intdiv($z, 10)];
            } elseif ($z > 0) {
                $t .= $einer[$z];
            }
            return $t;
        };

        $wort = '';
        if ($n >= 1000) {
            $tsd = intdiv($n, 1000);
            $wort .= ($tsd === 1 ? 'ein' : $bis999($tsd)) . 'tausend';
            $n %= 1000;
        }
        $wort .= $bis999($n);

        return $wort;
    }
}
