<?php
namespace App\Report;

use App\DB\Database;

/**
 * Auswertung der PayPal-Transaktionen für das Dashboard.
 *
 * Liefert je Zeitraum:
 *  - buckets: die Balken des Diagramms (Tag / Woche / Monat / Jahr)
 *  - kpi:     Summen des laufenden Zeitraums + Vergleich zum Vorzeitraum
 *
 * Vergleichslogik: Der laufende Zeitraum wird immer gegen das GLEICH LANGE
 * Fenster der Vorperiode gerechnet (1.–11. Juli vs. 1.–11. Juni). Sonst sähe
 * ein angebrochener Monat immer künstlich schlecht aus.
 */
class Statistik
{
    private const MONATE = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];

    /**
     * @param string $gran  woche | monat | jahr | frei
     * @param string $von   nur bei gran=frei (Y-m-d)
     * @param string $bis   nur bei gran=frei (Y-m-d)
     */
    public function auswerten(string $gran, string $von = '', string $bis = ''): array
    {
        $heute = date('Y-m-d');

        if ($gran === 'frei') {
            $daten = $this->freierZeitraum($von ?: $heute, $bis ?: $heute);
        } else {
            $daten = [
                'gran'    => $gran,
                'buckets' => $this->buckets($gran, $heute),
                'kpi'     => $this->kpi($gran, $heute),
            ];
        }

        // Top-Spender immer für den ausgewerteten Zeitraum
        [$tVon, $tBis]  = $daten['kpi']['zeitraum']['akt'];
        $daten['top']   = $this->topSpender($tVon, $tBis);

        return $daten;
    }

    /**
     * Top 10 Spender im Zeitraum – einmal nach Anzahl, einmal nach Summe.
     *
     * Gruppiert wird über die E-Mail, weil nur sie eine Person eindeutig
     * identifiziert (Namen sind uneinheitlich geschrieben). Zahlungen OHNE
     * E-Mail lassen sich niemandem zuordnen und würden sonst zu einem einzigen
     * Phantom-Spender verschmelzen – sie bleiben draußen und werden separat
     * ausgewiesen.
     */
    private function topSpender(string $von, string $bis): array
    {
        $liste = function (string $sortierung) use ($von, $bis) {
            $st = Database::get()->prepare(
                "SELECT
                    trim(COALESCE(vorname,'') || ' ' || COALESCE(nachname,'')) AS name,
                    lower(trim(email)) AS mail,
                    COUNT(*)           AS anzahl,
                    ROUND(SUM(brutto), 2) AS summe
                 FROM paypal_transaktionen
                 WHERE status = 'S'
                   AND date(datum) BETWEEN :von AND :bis
                   AND trim(COALESCE(email,'')) <> ''
                 GROUP BY lower(trim(email))
                 ORDER BY $sortierung DESC, summe DESC
                 LIMIT 10"
            );
            $st->execute([':von' => $von, ':bis' => $bis]);

            return array_map(fn ($r) => [
                'name'   => $r['name'] !== '' ? $r['name'] : $r['mail'],
                'mail'   => $r['mail'],
                'anzahl' => (int) $r['anzahl'],
                'summe'  => (float) $r['summe'],
            ], $st->fetchAll());
        };

        // Nicht zuordenbar: Zahlungen ohne E-Mail
        $st = Database::get()->prepare(
            "SELECT COUNT(*) n, COALESCE(SUM(brutto),0) s
             FROM paypal_transaktionen
             WHERE status = 'S' AND date(datum) BETWEEN ? AND ?
               AND trim(COALESCE(email,'')) = ''"
        );
        $st->execute([$von, $bis]);
        $rest = $st->fetch();

        return [
            'nach_anzahl' => $liste('anzahl'),
            'nach_summe'  => $liste('summe'),
            'ohne_zuordnung' => [
                'anzahl' => (int) $rest['n'],
                'summe'  => round((float) $rest['s'], 2),
            ],
        ];
    }

    // ── Balken der letzten N Perioden ──
    private function buckets(string $gran, string $heute): array
    {
        $anz   = $gran === 'jahr' ? 3 : 12;
        $reihe = [];

        for ($i = $anz - 1; $i >= 0; $i--) {
            if ($gran === 'woche') {
                $start = date('Y-m-d', strtotime("$heute -$i weeks monday this week"));
                $ende  = date('Y-m-d', strtotime("$start +6 days"));
                $label = 'KW ' . date('W', strtotime($start));
            } elseif ($gran === 'monat') {
                $start = date('Y-m-01', strtotime("$heute -$i months"));
                $ende  = date('Y-m-t', strtotime($start));
                $label = self::MONATE[(int) date('n', strtotime($start)) - 1];
            } else {
                $jahr  = (int) date('Y', strtotime($heute)) - $i;
                $start = "$jahr-01-01";
                $ende  = "$jahr-12-31";
                $label = (string) $jahr;
            }
            $reihe[] = ['label' => $label, 'start' => $start] + $this->summe($start, $ende);
        }

        return $reihe;
    }

    // ── Laufende Periode vs. gleiches Fenster der Vorperiode ──
    private function kpi(string $gran, string $heute): array
    {
        if ($gran === 'woche') {
            $aktStart = date('Y-m-d', strtotime("$heute monday this week"));
            $vorStart = date('Y-m-d', strtotime("$aktStart -1 week"));
        } elseif ($gran === 'monat') {
            $aktStart = date('Y-m-01', strtotime($heute));
            $vorStart = date('Y-m-01', strtotime("$aktStart -1 month"));
        } else {
            $aktStart = date('Y-01-01', strtotime($heute));
            $vorStart = date('Y-01-01', strtotime("$aktStart -1 year"));
        }

        $tage    = (int) ((strtotime($heute) - strtotime($aktStart)) / 86400);
        $vorEnde = date('Y-m-d', strtotime("$vorStart +$tage days"));

        return $this->vergleich($aktStart, $heute, $vorStart, $vorEnde);
    }

    // ── Frei gewählter Zeitraum (auch ein einzelner Tag) ──
    private function freierZeitraum(string $von, string $bis): array
    {
        if (strtotime($von) > strtotime($bis)) {
            [$von, $bis] = [$bis, $von];
        }

        $tage = (int) ((strtotime($bis) - strtotime($von)) / 86400) + 1;

        // Balken-Granularität passend zur Spannweite wählen
        if ($tage <= 31) {
            $einheit = 'tag';
        } elseif ($tage <= 182) {
            $einheit = 'woche';
        } elseif ($tage <= 1100) {
            $einheit = 'monat';
        } else {
            $einheit = 'jahr';
        }

        $buckets = [];
        $cursor  = $von;
        while (strtotime($cursor) <= strtotime($bis)) {
            if ($einheit === 'tag') {
                $start = $cursor;
                $ende  = $cursor;
                $label = date('d.m.', strtotime($start));
                $next  = date('Y-m-d', strtotime("$cursor +1 day"));
            } elseif ($einheit === 'woche') {
                $start = date('Y-m-d', strtotime("$cursor monday this week"));
                $ende  = date('Y-m-d', strtotime("$start +6 days"));
                $label = 'KW ' . date('W', strtotime($start));
                $next  = date('Y-m-d', strtotime("$start +7 days"));
            } elseif ($einheit === 'monat') {
                $start = date('Y-m-01', strtotime($cursor));
                $ende  = date('Y-m-t', strtotime($start));
                $label = self::MONATE[(int) date('n', strtotime($start)) - 1] . ' ' . date('y', strtotime($start));
                $next  = date('Y-m-d', strtotime("$start +1 month"));
            } else {
                $start = date('Y-01-01', strtotime($cursor));
                $ende  = date('Y-12-31', strtotime($start));
                $label = date('Y', strtotime($start));
                $next  = date('Y-m-d', strtotime("$start +1 year"));
            }

            // Balken auf den gewählten Zeitraum beschneiden
            $bStart = max($start, $von);
            $bEnde  = min($ende, $bis);

            $buckets[] = ['label' => $label, 'start' => $bStart] + $this->summe($bStart, $bEnde);
            $cursor = $next;
        }

        // Vergleich: gleich langes Fenster direkt davor
        $vorBis  = date('Y-m-d', strtotime("$von -1 day"));
        $vorVon  = date('Y-m-d', strtotime("$vorBis -" . ($tage - 1) . " days"));

        return [
            'gran'    => 'frei',
            'einheit' => $einheit,
            'buckets' => $buckets,
            'kpi'     => $this->vergleich($von, $bis, $vorVon, $vorBis),
        ];
    }

    /**
     * Summen eines Zeitraums – nur echte Spenden.
     *
     * Buchungen OHNE Zahler-E-Mail sind keine Spenden, sondern Kontodeckungen für
     * eigene Ausgaben (PayPal-Event T0300: Facebook-Werbung, Metro, eBay ...) und
     * Rückstellungs-Aufhebungen (T1105). Am 11.07.2026 über die PayPal-API geprüft:
     * alle 78 solcher Buchungen, zusammen 27.855,33 €, ohne Zahler und ohne Gebühr.
     * Sie gehören nicht in den Spendeneingang.
     */
    private function summe(string $von, string $bis): array
    {
        $st = Database::get()->prepare(
            "SELECT COALESCE(SUM(brutto),0) b, COALESCE(SUM(gebuehr),0) g, COUNT(*) n
             FROM paypal_transaktionen
             WHERE status = 'S' AND date(datum) BETWEEN ? AND ?
               AND trim(COALESCE(email,'')) <> ''"
        );
        $st->execute([$von, $bis]);
        $r = $st->fetch();

        return [
            'brutto'  => round((float) $r['b'], 2),
            'gebuehr' => round((float) $r['g'], 2),
            'anzahl'  => (int) $r['n'],
        ];
    }

    // Ältester Datensatz – davor gibt es keine Vergleichsbasis
    private function datenBeginn(): ?string
    {
        $r = Database::get()->query("SELECT MIN(date(datum)) d FROM paypal_transaktionen")->fetch();
        return $r['d'] ?: null;
    }

    private function vergleich(string $aktVon, string $aktBis, string $vorVon, string $vorBis): array
    {
        $akt = $this->summe($aktVon, $aktBis);
        $vor = $this->summe($vorVon, $vorBis);

        // Reicht das Vergleichsfenster vor den ersten Datensatz, ist das Delta wertlos:
        // es vergleicht gegen einen Zeitraum, für den wir gar keine Daten haben.
        $beginn      = $this->datenBeginn();
        $unvollstaendig = $beginn !== null && strtotime($vorVon) < strtotime($beginn);

        // Ohne Vergleichswert gibt es kein Delta – dann lieber nichts anzeigen als 0 %
        $delta = fn (float $a, float $b) => $b > 0 ? round((($a - $b) / $b) * 100, 1) : null;

        return [
            'aktuell'       => $akt,
            'vorzeitraum'   => $vor,
            'delta_brutto'  => $delta($akt['brutto'],  $vor['brutto']),
            'delta_gebuehr' => $delta($akt['gebuehr'], $vor['gebuehr']),
            'delta_anzahl'  => $delta((float) $akt['anzahl'], (float) $vor['anzahl']),
            'quote'         => $akt['brutto'] > 0 ? round(100 * $akt['gebuehr'] / $akt['brutto'], 2) : 0.0,
            'quote_vor'     => $vor['brutto'] > 0 ? round(100 * $vor['gebuehr'] / $vor['brutto'], 2) : 0.0,
            'zeitraum'      => ['akt' => [$aktVon, $aktBis], 'vor' => [$vorVon, $vorBis]],
            'vergleich_unvollstaendig' => $unvollstaendig,
            'daten_beginn'  => $beginn,
        ];
    }
}
