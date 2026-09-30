<?php
namespace App\Pdf;

use App\DB\Database;
use App\Imap\AnhangIndex;

/**
 * Import bestehender (manuell erstellter) Spendenbescheinigungen ab 2024.
 *
 * Workflow – nichts wird beim Einlesen produktiv gesetzt:
 *
 *   Upload  →  eingang   (ausgelesen, noch nichts in `spenden`)
 *           →  geprueft  (Mensch hat die Daten kontrolliert/korrigiert)
 *           →  Freigabe  → Eintrag in `spenden` mit dem ORIGINAL-PDF
 *           →  verworfen (aussortiert)
 *
 * Beim Übernehmen wird KEIN neues PDF erzeugt. Der Eintrag bekommt das
 * Original-PDF und den Status 'erledigt' – die Bescheinigung existiert ja
 * bereits und liegt beim Spender. Ein neu erzeugtes PDF würde einen zweiten,
 * abweichenden Beleg für denselben Vorgang schaffen.
 */
class Importer
{
    private string $lager;

    public function __construct(private ?array $cfg = null)
    {
        $this->cfg   = $cfg ?? require __DIR__ . '/../../config/settings.php';
        // Bewusst NICHT unter public/ – die PDFs enthalten Namen und Anschriften
        $this->lager = rtrim($this->cfg['output_dir'], '/') . '/import';
        if (!is_dir($this->lager)) {
            mkdir($this->lager, 0775, true);
        }
    }

    /**
     * Massenimport: alle PDFs aus dem Import-Verzeichnis einlesen.
     *
     * Der Browser-Upload ist für Mengen untauglich (post_max_size 8 MB,
     * max_file_uploads 20 – bei ~1,2 MB pro PDF also ~6 Dateien pro Durchgang).
     * Deshalb: Dateien per SCP nach import_eingang/ kopieren und hier einlesen.
     *
     * Bereits eingelesene Dateien werden über den Hash übersprungen, der Lauf ist
     * also beliebig wiederholbar. Die Dateien bleiben liegen.
     *
     * @param int $limit Wie viele Dateien pro Durchgang (gegen Timeouts)
     */
    public function verzeichnisEinlesen(int $limit = 100): array
    {
        $dir = $this->cfg['import_dir'] ?? '/var/www/html/import_eingang';
        if (!is_dir($dir)) {
            return ['ok' => false, 'error' => "Verzeichnis $dir existiert nicht"];
        }

        $dateien = glob(rtrim($dir, '/') . '/*.[pP][dD][fF]') ?: [];
        sort($dateien);

        $neu = $uebersprungen = $fehler = 0;
        $rest = 0;

        foreach ($dateien as $datei) {
            if ($neu + $fehler >= $limit) {
                $rest++;
                continue;
            }
            $r = $this->aufnehmen($datei, basename($datei), true);
            if ($r['ok']) {
                $neu++;
            } elseif (str_contains($r['grund'] ?? '', 'bereits eingelesen')) {
                $uebersprungen++;
            } else {
                $fehler++;
            }
        }

        Database::logAdd('OK', "Verzeichnis-Import: $neu neu, $uebersprungen bereits bekannt, $fehler Probleme, $rest offen");

        return [
            'ok' => true,
            'gefunden' => count($dateien),
            'neu' => $neu,
            'uebersprungen' => $uebersprungen,
            'fehler' => $fehler,
            'rest' => $rest,
        ];
    }

    /**
     * Eine Datei aufnehmen und auslesen.
     * Legt einen Eintrag im Status 'eingang' an – noch nichts Produktives.
     *
     * @param bool $ausVerzeichnis true = Datei liegt schon auf dem Server (kein Upload)
     */
    public function aufnehmen(string $tmpDatei, string $originalName, bool $ausVerzeichnis = false): array
    {
        $hash = hash_file('sha256', $tmpDatei);

        // Dieselbe Datei nicht zweimal einlesen
        $vorhanden = Database::get()->prepare("SELECT id FROM import_pdf WHERE hash = ?");
        $vorhanden->execute([$hash]);
        if ($id = $vorhanden->fetchColumn()) {
            return ['ok' => false, 'grund' => 'Datei bereits eingelesen (#' . $id . ')'];
        }

        // Original unverändert ablegen
        $ziel = $this->lager . '/' . $this->sicherName($originalName, $hash);
        if (!copy($tmpDatei, $ziel)) {
            return ['ok' => false, 'grund' => 'Datei konnte nicht gespeichert werden'];
        }

        $d = (new Leser($this->cfg))->auslesen($ziel);

        // Der INHALT ist die Wahrheit. Der Dateiname ist nur die Gegenprobe:
        // Weicht er ab, wird das als Warnung vermerkt statt still übergangen.
        $warnungen = $d['warnungen'];
        $ausName   = $this->dateinameLesen($originalName);
        foreach ($this->dateinameVergleichen($ausName, $d) as $w) {
            $warnungen[] = $w;
        }

        // Zuwendungsart und Kalenderjahr getrennt führen (Geldzuwendung / Mitgliedsbeitrag)
        $zuwendungsart = $this->zuwendungsartBestimmen($d['art']);
        $jahr = $d['jahr'] !== '' ? $d['jahr'] : ($ausName['jahr'] ?? '');
        if ($zuwendungsart === '') {
            $warnungen[] = 'Zuwendungsart unklar – bitte Geldzuwendung oder Mitgliedsbeitrag wählen';
        }

        // Abgleich mit den gesendeten Mails: gleiche Datei = gleiche Bytes = harter Treffer
        $mail = AnhangIndex::suchen($hash);

        $st = Database::get()->prepare(
            "INSERT INTO import_pdf
                (dateiname, datei_name_roh, pdf_pfad, hash, vorname, nachname, strasse, plz, ort, email,
                 betrag, betrag_wort, datum, art, zuwendungsart, jahr, zahlungsweg,
                 status, fehler, warnungen, duplikat_id, mail_gefunden, mail_datum)
             VALUES
                (:dateiname, :roh, :pdf, :hash, :vorname, :nachname, :strasse, :plz, :ort, :email,
                 :betrag, :wort, :datum, :art, :zart, :jahr, :weg,
                 :status, :fehler, :warnungen, :dup, :mailgef, :maildatum)"
        );

        $duplikat = $d['ok'] ? $this->duplikatSuchen($d) : null;

        $st->execute([
            ':dateiname' => $originalName,
            ':roh'       => trim(($ausName['vorname'] ?? '') . ' ' . ($ausName['nachname'] ?? '')),
            ':pdf'       => $ziel,
            ':hash'      => $hash,
            ':vorname'   => $d['vorname'],
            ':nachname'  => $d['nachname'],
            ':strasse'   => $d['strasse'],
            ':plz'       => $d['plz'],
            ':ort'       => $d['ort'],
            // E-Mail kommt NUR aus der gesendeten Mail – im PDF steht keine
            ':email'     => $mail['email'] ?? '',
            ':betrag'    => $d['betrag'],
            ':wort'      => $d['betrag_wort'],
            ':datum'     => $d['datum'],
            ':art'       => $d['art'],
            ':zart'      => $zuwendungsart,
            ':jahr'      => $jahr,
            // Zahlungsweg steht nicht im PDF – muss der Mensch setzen (bar, Überweisung, ...)
            ':weg'       => $mail ? 'PayPal' : '',
            ':status'    => $d['fehler'] ? 'fehler' : 'eingang',
            ':fehler'    => $d['fehler'],
            ':warnungen' => implode(' · ', $warnungen),
            ':dup'       => $duplikat,
            ':mailgef'   => $mail ? 1 : 0,
            ':maildatum' => $mail['mail_datum'] ?? '',
        ]);

        return [
            'ok'       => $d['fehler'] === '',
            'id'       => (int) Database::get()->lastInsertId(),
            'grund'    => $d['fehler'],
            'duplikat' => $duplikat,
            'mail'     => $mail['email'] ?? '',
        ];
    }

    /**
     * Name und Kalenderjahr aus dem DATEINAMEN raten.
     *
     * Es gibt keine garantierte Namenskonvention, deshalb bewusst tolerant:
     * Jahreszahl (2024–2029) suchen, Füllwörter wegwerfen, der Rest sind Namen.
     * Das Ergebnis wird NIE direkt übernommen – es dient nur der Gegenprobe
     * gegen den PDF-Inhalt.
     */
    public function dateinameLesen(string $dateiname): array
    {
        $basis = pathinfo($dateiname, PATHINFO_FILENAME);
        $basis = str_replace(['_', '-', '.', '+'], ' ', $basis);

        $jahr = '';
        if (preg_match('/\b(20[2-9]\d)\b/', $basis, $m)) {
            $jahr = $m[1];
        }

        $muell = [
            'spendenbescheinigung', 'zuwendungsbestaetigung', 'zuwendungsbestätigung',
            'bescheinigung', 'geldzuwendung', 'zuwendung', 'mitgliedsbeitrag',
            'spende', 'beitrag', 'kopie', 'scan', 'pdf', 'ditib', 'final',
        ];

        $woerter = [];
        foreach (preg_split('/\s+/u', $basis, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
            if (preg_match('/^\d+$/', $w)) {
                continue;   // Zahlen (Jahr, laufende Nummer, Datum)
            }
            if (in_array(mb_strtolower($w), $muell, true)) {
                continue;
            }
            if (mb_strlen($w) < 2) {
                continue;
            }
            $woerter[] = $w;
        }

        // Konvention unbekannt → letztes Wort als Nachname annehmen, Rest Vorname
        $nachname = count($woerter) ? array_pop($woerter) : '';
        $vorname  = implode(' ', $woerter);

        return ['vorname' => $vorname, 'nachname' => $nachname, 'jahr' => $jahr];
    }

    /** Gegenprobe Dateiname ↔ PDF-Inhalt */
    private function dateinameVergleichen(array $ausName, array $inhalt): array
    {
        $warnungen = [];
        $norm = fn (string $s) => preg_replace('/[^a-z]/', '', mb_strtolower($s));

        if ($ausName['nachname'] !== '' && $inhalt['nachname'] !== ''
            && $norm($ausName['nachname']) !== $norm($inhalt['nachname'])
            && !str_contains($norm($ausName['vorname'] . $ausName['nachname']), $norm($inhalt['nachname']))) {
            $warnungen[] = sprintf(
                'Dateiname nennt "%s", im PDF steht "%s"',
                trim($ausName['vorname'] . ' ' . $ausName['nachname']),
                trim($inhalt['vorname'] . ' ' . $inhalt['nachname'])
            );
        }

        if ($ausName['jahr'] !== '' && $inhalt['jahr'] !== '' && $ausName['jahr'] !== $inhalt['jahr']) {
            $warnungen[] = sprintf(
                'Jahr im Dateinamen (%s) weicht vom PDF (%s) ab',
                $ausName['jahr'],
                $inhalt['jahr']
            );
        }

        return $warnungen;
    }

    /** Geldzuwendung oder Mitgliedsbeitrag? Steht in der Zeile "Art der Zuwendung:" */
    private function zuwendungsartBestimmen(string $art): string
    {
        $a = mb_strtolower($art);
        if (str_contains($a, 'mitglied')) {
            return 'Mitgliedsbeitrag';
        }
        if (str_contains($a, 'geld') || str_contains($a, 'zuwendung') || str_contains($a, 'spende')) {
            return 'Geldzuwendung';
        }
        return '';
    }

    /**
     * Nachträglicher Mail-Abgleich für alle Staging-Einträge ohne Treffer.
     * (Für Dateien, die vor dem Aufbau des Anhang-Index eingelesen wurden.)
     */
    public function mailAbgleich(): array
    {
        $offen = Database::get()->query(
            "SELECT id, hash FROM import_pdf WHERE mail_gefunden = 0 AND status != 'uebernommen'"
        )->fetchAll();

        $up = Database::get()->prepare(
            "UPDATE import_pdf
             SET email = CASE WHEN email = '' THEN :email ELSE email END,
                 mail_gefunden = 1,
                 mail_datum = :datum,
                 zahlungsweg = CASE WHEN zahlungsweg = '' THEN 'PayPal' ELSE zahlungsweg END
             WHERE id = :id"
        );

        $treffer = 0;
        foreach ($offen as $e) {
            $mail = AnhangIndex::suchen($e['hash']);
            if (!$mail) {
                continue;   // nie per Mail verschickt → bleibt ein Eintrag ohne E-Mail
            }
            $up->execute([':email' => $mail['email'], ':datum' => $mail['mail_datum'], ':id' => $e['id']]);
            $treffer++;
        }

        Database::logAdd('OK', "Mail-Abgleich: $treffer von " . count($offen) . " Dateien einer gesendeten Mail zugeordnet");

        return ['geprueft' => count($offen), 'treffer' => $treffer, 'ohne_mail' => count($offen) - $treffer];
    }

    /**
     * Duplikatsuche: Das PDF enthält keine E-Mail und keine Transaktions-ID.
     * Bleibt nur Name + Betrag + Datum (± 5 Tage, weil das Ausstellungsdatum der
     * Bescheinigung vom Zahlungsdatum abweichen kann).
     */
    private function duplikatSuchen(array $d): ?int
    {
        if ($d['datum'] === '' || $d['betrag'] <= 0) {
            return null;
        }
        $ts = strtotime(str_replace('.', '-', $this->iso($d['datum'])));
        if (!$ts) {
            return null;
        }

        $st = Database::get()->prepare(
            "SELECT id, datum FROM spenden
             WHERE lower_utf8(nachname) = lower_utf8(:nachname)
               AND ABS(betrag - :betrag) < 0.01"
        );
        $st->execute([':nachname' => $d['nachname'], ':betrag' => $d['betrag']]);

        foreach ($st->fetchAll() as $s) {
            $sTs = strtotime($this->iso($s['datum']));
            if ($sTs && abs($sTs - $ts) <= 5 * 86400) {
                return (int) $s['id'];
            }
        }
        return null;
    }

    /** TT.MM.JJJJ → JJJJ-MM-TT */
    private function iso(string $datum): string
    {
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', trim($datum), $m)) {
            return "$m[3]-$m[2]-$m[1]";
        }
        return $datum;
    }

    /**
     * Freigabe: erzeugt den Eintrag in `spenden` – mit dem ORIGINAL-PDF.
     * Kein neues PDF, kein Versand.
     */
    public function uebernehmen(int $importId): array
    {
        $st = Database::get()->prepare("SELECT * FROM import_pdf WHERE id = ?");
        $st->execute([$importId]);
        $i = $st->fetch();

        if (!$i) {
            return ['ok' => false, 'error' => 'Import nicht gefunden'];
        }
        if ($i['spende_id']) {
            return ['ok' => false, 'error' => 'Bereits übernommen (Spende #' . $i['spende_id'] . ')'];
        }
        if ($i['nachname'] === '' || $i['betrag'] <= 0 || $i['datum'] === '') {
            return ['ok' => false, 'error' => 'Name, Betrag und Datum müssen gefüllt sein'];
        }
        if ($i['zahlungsweg'] === '') {
            return ['ok' => false, 'error' => 'Bitte den Zahlungsweg setzen (Bar, Überweisung, PayPal ...)'];
        }
        if ($i['zuwendungsart'] === '' || $i['jahr'] === '') {
            return ['ok' => false, 'error' => 'Bitte Zuwendungsart (Geldzuwendung/Mitgliedsbeitrag) und Kalenderjahr setzen'];
        }

        // art in der Schreibweise, die im Portal schon üblich ist:
        // "Geldzuwendung für 2026" / "Mitgliedsbeitrag für 2025"
        $art = $i['zuwendungsart'] . ' für ' . $i['jahr'];

        $sid = Database::spendeErstellen([
            'vorname'     => $i['vorname'],
            'nachname'    => $i['nachname'],
            'strasse'     => $i['strasse'],
            'plz'         => $i['plz'],
            'ort'         => $i['ort'],
            'email'       => $i['email'],           // darf leer sein (bar / Überweisung)
            'betrag'      => (float) $i['betrag'],
            'datum'       => $i['datum'],
            'art'         => $art,
            'zahlungsweg' => $i['zahlungsweg'],
            'quelle'      => 'pdf_import',
            'pdf_pfad'    => $i['pdf_pfad'],        // das ORIGINAL, nicht neu erzeugt
            'status'      => 'erledigt',            // Bescheinigung liegt bereits beim Spender
        ]);

        if (!$sid) {
            return ['ok' => false, 'error' => 'Eintrag konnte nicht angelegt werden'];
        }

        Database::get()->prepare(
            "UPDATE import_pdf SET status = 'uebernommen', spende_id = ? WHERE id = ?"
        )->execute([$sid, $importId]);

        Database::kommentarAdd(
            $sid,
            'Aus bestehender PDF importiert (' . $i['dateiname'] . ') – Original-PDF übernommen, nicht neu erzeugt',
            'System',
            'system'
        );
        Database::logAdd('OK', "PDF-Import #$importId übernommen als Spende #$sid");

        return ['ok' => true, 'spende_id' => $sid];
    }

    private function sicherName(string $name, string $hash): string
    {
        $basis = pathinfo($name, PATHINFO_FILENAME);
        $basis = preg_replace('/[^A-Za-z0-9_-]/', '_', $basis);
        return substr($basis, 0, 60) . '_' . substr($hash, 0, 8) . '.pdf';
    }
}
