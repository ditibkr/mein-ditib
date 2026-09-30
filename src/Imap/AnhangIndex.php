<?php
namespace App\Imap;

use App\DB\Database;

/**
 * Indiziert die PDF-Anhänge aus "Gesendete Objekte".
 *
 * Zweck: Eine PDF-Datei vom USB-Stick eindeutig einer gesendeten Mail zuordnen –
 * und damit an die E-Mail-Adresse des Spenders kommen.
 *
 * Der Abgleich läuft über den SHA-256 des Anhangs. Wurde genau diese Datei damals
 * verschickt, sind die Bytes identisch – das ist ein harter Treffer, kein Raten
 * über Namensähnlichkeit.
 *
 * Der Index wird in `mail_anhaenge` zwischengespeichert, damit der IMAP-Ordner
 * nicht bei jedem Import neu durchsucht werden muss.
 */
class AnhangIndex
{
    private array $cfg;

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? require __DIR__ . '/../../config/settings.php';
    }

    /**
     * Baut den Index auf (bzw. aktualisiert ihn).
     *
     * @param string $seit IMAP-Datum, z.B. "1-Jan-2024"
     * @return array{mails: int, anhaenge: int, neu: int, fehler: int}
     */
    public function aufbauen(string $seit = '1-Jan-2024'): array
    {
        $host = '{' . $this->cfg['imap_host'] . ':' . $this->cfg['imap_port'] . '/imap/ssl/novalidate-cert}';
        $ordner = $this->cfg['imap_sent_folder'] ?? 'Gesendete Objekte';

        $imap = @imap_open($host . $ordner, $this->cfg['imap_user'], $this->cfg['imap_password'], 0, 1);
        if (!$imap) {
            Database::logAdd('ERROR', 'Anhang-Index: IMAP-Verbindung fehlgeschlagen – ' . imap_last_error());
            return ['mails' => 0, 'anhaenge' => 0, 'neu' => 0, 'fehler' => 1];
        }

        $mails = @imap_search($imap, 'SINCE ' . $seit) ?: [];
        $anhaenge = $neu = $fehler = 0;

        $ins = Database::get()->prepare(
            "INSERT OR IGNORE INTO mail_anhaenge (hash, dateiname, email, mail_datum, betreff)
             VALUES (:hash, :datei, :email, :datum, :betreff)"
        );

        foreach ($mails as $nr) {
            try {
                $kopf = @imap_headerinfo($imap, $nr);
                if (!$kopf) {
                    continue;
                }
                $email = '';
                if (!empty($kopf->to[0]->mailbox) && !empty($kopf->to[0]->host)) {
                    $email = strtolower($kopf->to[0]->mailbox . '@' . $kopf->to[0]->host);
                }
                $datum   = isset($kopf->date) ? date('Y-m-d', strtotime($kopf->date)) : '';
                $betreff = isset($kopf->subject) ? @imap_utf8($kopf->subject) : '';

                foreach ($this->pdfAnhaenge($imap, $nr) as $anhang) {
                    $anhaenge++;
                    $ins->execute([
                        ':hash'    => hash('sha256', $anhang['inhalt']),
                        ':datei'   => $anhang['name'],
                        ':email'   => $email,
                        ':datum'   => $datum,
                        ':betreff' => mb_substr($betreff, 0, 120),
                    ]);
                    if ($ins->rowCount() > 0) {
                        $neu++;
                    }
                }
            } catch (\Throwable $e) {
                $fehler++;
            }
        }

        imap_close($imap);

        Database::logAdd('OK', sprintf(
            'Anhang-Index: %d Mails durchsucht, %d PDF-Anhänge, %d neu im Index, %d Fehler',
            count($mails), $anhaenge, $neu, $fehler
        ));

        return ['mails' => count($mails), 'anhaenge' => $anhaenge, 'neu' => $neu, 'fehler' => $fehler];
    }

    /** Alle PDF-Anhänge einer Mail als ['name' => ..., 'inhalt' => ...] */
    private function pdfAnhaenge($imap, int $nr): array
    {
        $struktur = @imap_fetchstructure($imap, $nr);
        if (!$struktur || empty($struktur->parts)) {
            return [];
        }

        $treffer = [];
        foreach ($struktur->parts as $i => $teil) {
            $name = '';
            foreach (array_merge((array) ($teil->dparameters ?? []), (array) ($teil->parameters ?? [])) as $p) {
                if (is_object($p) && in_array(strtolower($p->attribute ?? ''), ['filename', 'name'], true)) {
                    $name = $p->value;
                }
            }
            if ($name === '' || !preg_match('/\.pdf$/i', $name)) {
                continue;
            }

            $roh = @imap_fetchbody($imap, $nr, (string) ($i + 1));
            if ($roh === false) {
                continue;
            }
            $inhalt = match ((int) ($teil->encoding ?? 0)) {
                3       => base64_decode($roh),
                4       => quoted_printable_decode($roh),
                default => $roh,
            };
            if ($inhalt === '' || $inhalt === false) {
                continue;
            }

            // Der Mitgliedsantrag hängt an jeder Mail und ist keine Bescheinigung
            $beilage = basename($this->cfg['beilage_pfad'] ?? '');
            if ($beilage !== '' && $name === $beilage) {
                continue;
            }

            $treffer[] = ['name' => $name, 'inhalt' => $inhalt];
        }

        return $treffer;
    }

    /** Zu einem Datei-Hash die gesendete Mail finden */
    public static function suchen(string $hash): ?array
    {
        $st = Database::get()->prepare("SELECT * FROM mail_anhaenge WHERE hash = ?");
        $st->execute([$hash]);
        return $st->fetch() ?: null;
    }

    public static function anzahl(): int
    {
        return (int) Database::get()->query("SELECT COUNT(*) FROM mail_anhaenge")->fetchColumn();
    }
}
