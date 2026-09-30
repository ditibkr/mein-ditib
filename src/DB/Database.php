<?php
namespace App\DB;

class Database
{
    private static ?\PDO $pdo = null;

    public static function get(): \PDO
    {
        if (self::$pdo === null) {
            $cfg = require __DIR__ . '/../../config/settings.php';
            $path = $cfg['db_path'];
            $dir = dirname($path);
            if (!is_dir($dir))
                mkdir($dir, 0775, true);

            self::$pdo = new \PDO("sqlite:$path");
            self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA journal_mode=WAL');
            // SQLite LIKE kennt keine Unicode-Groß-/Kleinschreibung (ä≠Ä etc.)
            // Eigene Funktion für Umlaut-sichere Suche registrieren
            self::$pdo->sqliteCreateFunction('lower_utf8', fn($s) => mb_strtolower((string)$s, 'UTF-8'), 1);
            self::init();
        }
        return self::$pdo;
    }

    private static function init(): void
    {
        self::$pdo->exec("
        CREATE TABLE IF NOT EXISTS spenden (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            vorname       TEXT NOT NULL,
            nachname      TEXT NOT NULL,
            strasse       TEXT DEFAULT '',
            plz           TEXT DEFAULT '',
            ort           TEXT DEFAULT '',
            email         TEXT DEFAULT '',
            betrag        REAL NOT NULL,
            datum         TEXT NOT NULL,
            art           TEXT NOT NULL DEFAULT 'Geldzuwendung',
            zahlungsweg   TEXT DEFAULT 'Überweisung',
            quelle        TEXT DEFAULT 'mail',
            pdf_pfad      TEXT DEFAULT '',
            status        TEXT DEFAULT 'neu',
            kommentar     TEXT DEFAULT '',
            mitgliedsnr   TEXT DEFAULT '',
            zeitraum_von  TEXT DEFAULT '',
            zeitraum_bis  TEXT DEFAULT '',
            sammel_ids    TEXT DEFAULT '',
            mail_id       TEXT UNIQUE DEFAULT '',
            mail_body     TEXT DEFAULT '',
            land          TEXT DEFAULT '',
            auslaendisch  INTEGER DEFAULT 0,
            erstellt_am   TEXT DEFAULT (datetime('now','localtime')),
            versendet_am  TEXT DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS kommentare (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            spende_id   INTEGER NOT NULL,
            autor       TEXT DEFAULT 'Admin',
            typ         TEXT DEFAULT 'user',
            text        TEXT NOT NULL,
            erstellt_am TEXT DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (spende_id) REFERENCES spenden(id)
        );
        CREATE TABLE IF NOT EXISTS mail_ids (
            mail_id        TEXT PRIMARY KEY,
            verarbeitet_am TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS logs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            level       TEXT NOT NULL,
            nachricht   TEXT NOT NULL,
            erstellt_am TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS paypal_transaktionen (
            tx_id       TEXT PRIMARY KEY,
            datum       TEXT NOT NULL,
            vorname     TEXT DEFAULT '',
            nachname    TEXT DEFAULT '',
            email       TEXT DEFAULT '',
            betreff     TEXT DEFAULT '',
            brutto      REAL NOT NULL,
            gebuehr     REAL DEFAULT 0,
            netto       REAL NOT NULL,
            waehrung    TEXT DEFAULT 'EUR',
            status      TEXT DEFAULT '',
            geladen_am  TEXT DEFAULT (datetime('now','localtime'))
        );
        -- Staging für den Import alter, manuell erstellter Bescheinigungen.
        -- Nichts hiervon ist produktiv: erst die Freigabe erzeugt einen Eintrag
        -- in `spenden` – mit dem ORIGINAL-PDF, ohne Neuerzeugung.
        CREATE TABLE IF NOT EXISTS import_pdf (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            dateiname    TEXT NOT NULL,
            pdf_pfad     TEXT NOT NULL,
            hash         TEXT UNIQUE,
            vorname      TEXT DEFAULT '',
            nachname     TEXT DEFAULT '',
            strasse      TEXT DEFAULT '',
            plz          TEXT DEFAULT '',
            ort          TEXT DEFAULT '',
            email        TEXT DEFAULT '',
            betrag       REAL DEFAULT 0,
            betrag_wort  TEXT DEFAULT '',
            datum        TEXT DEFAULT '',
            art          TEXT DEFAULT '',
            zahlungsweg  TEXT DEFAULT '',
            status       TEXT DEFAULT 'eingang',
            fehler       TEXT DEFAULT '',
            warnungen    TEXT DEFAULT '',
            duplikat_id  INTEGER DEFAULT NULL,
            spende_id    INTEGER DEFAULT NULL,
            erstellt_am  TEXT DEFAULT (datetime('now','localtime'))
        );
        -- Index der PDF-Anhänge aus 'Gesendete Objekte'.
        -- Über den SHA-256 des Anhangs lässt sich eine Datei eindeutig einer
        -- gesendeten Mail zuordnen – und damit der E-Mail-Adresse des Spenders.
        CREATE TABLE IF NOT EXISTS mail_anhaenge (
            hash        TEXT PRIMARY KEY,
            dateiname   TEXT DEFAULT '',
            email       TEXT DEFAULT '',
            mail_datum  TEXT DEFAULT '',
            betreff     TEXT DEFAULT '',
            indiziert_am TEXT DEFAULT (datetime('now','localtime'))
        );
        ");
        // Migration: Staging um Zuwendungsart, Jahr und Mail-Abgleich erweitern
        foreach ([
            "ALTER TABLE import_pdf ADD COLUMN zuwendungsart TEXT DEFAULT ''",
            "ALTER TABLE import_pdf ADD COLUMN jahr TEXT DEFAULT ''",
            "ALTER TABLE import_pdf ADD COLUMN datei_name_roh TEXT DEFAULT ''",
            "ALTER TABLE import_pdf ADD COLUMN mail_gefunden INTEGER DEFAULT 0",
            "ALTER TABLE import_pdf ADD COLUMN mail_datum TEXT DEFAULT ''",
        ] as $sql) {
            try { self::$pdo->exec($sql); } catch (\Exception) {}
        }
        // Migration: neue Spalten für bestehende DBs
        try { self::$pdo->exec("ALTER TABLE spenden ADD COLUMN mail_body TEXT DEFAULT ''"); } catch (\Exception) {}
        try { self::$pdo->exec("ALTER TABLE spenden ADD COLUMN land TEXT DEFAULT ''"); } catch (\Exception) {}
        try { self::$pdo->exec("ALTER TABLE spenden ADD COLUMN auslaendisch INTEGER DEFAULT 0"); } catch (\Exception) {}

        // ── Sitzungsprotokolle ──
        // Teilnehmer und Tagesordnung liegen als JSON im Protokoll selbst –
        // ein Protokoll ist ein abgeschlossenes Dokument, keine relationalen Live-Daten.
        // Die Vorlagen (übliche Teilnehmer, wiederkehrende TOPs) sind eigene Tabellen.
        self::$pdo->exec("
        CREATE TABLE IF NOT EXISTS protokolle (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            datum        TEXT NOT NULL,
            beginn       TEXT DEFAULT '',
            ende         TEXT DEFAULT '',
            status       TEXT DEFAULT 'offen',
            teilnehmer   TEXT DEFAULT '[]',
            tops         TEXT DEFAULT '[]',
            erstellt_am  TEXT DEFAULT (datetime('now','localtime')),
            geaendert_am TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS protokoll_personen (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            name     TEXT NOT NULL,
            funktion TEXT DEFAULT '',
            standard INTEGER DEFAULT 0,
            sortier  INTEGER DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS protokoll_bausteine (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            icon    TEXT DEFAULT '📄',
            titel   TEXT NOT NULL,
            subs    TEXT DEFAULT '[]',
            sortier INTEGER DEFAULT 0
        );
        ");
        self::protokollVorlagenSeed();
    }

    // Erstbefüllung der Protokoll-Vorlagen (nur wenn beide Tabellen leer sind)
    private static function protokollVorlagenSeed(): void
    {
        if ((int) self::$pdo->query("SELECT COUNT(*) FROM protokoll_personen")->fetchColumn() === 0) {
            $st = self::$pdo->prepare("INSERT INTO protokoll_personen (name, funktion, standard, sortier) VALUES (?,?,?,?)");
            foreach ([
                ['Murat Özsoy', 'Vorsitzender', 1],
                ['Veysel Arsoy', 'stellv. Vorsitzender', 1],
                ['Ramazan Kaya', 'Sekretär', 1],
                ['Nesime Eroglu', 'stellv. Vorsitzende Frauenverband', 1],
                ['Nazra Yesilyurt', 'Vorsitzende Jugendverband', 1],
                ['Nevzat Kus', 'Beisitzer', 1],
                ['Nihat Bilgic', 'Architekt', 0],
                ['Mehmet Selim', 'Imam', 0],
            ] as $i => $p) {
                $st->execute([$p[0], $p[1], $p[2], $i]);
            }
        }
        if ((int) self::$pdo->query("SELECT COUNT(*) FROM protokoll_bausteine")->fetchColumn() === 0) {
            $st = self::$pdo->prepare("INSERT INTO protokoll_bausteine (icon, titel, subs, sortier) VALUES (?,?,?,?)");
            foreach ([
                ['👋', 'Eröffnung und Begrüßung', ['Der Vorsitzende eröffnet die Sitzung, begrüßt alle Anwesenden und dankt für die Teilnahme.']],
                ['🎪', 'Veranstaltung „…“', []],
                ['👨‍👩‍👧', 'Statusbericht vom Elternverband', []],
                ['🧕', 'Statusbericht vom Frauenverband', []],
                ['🧑‍🤝‍🧑', 'Statusbericht vom Jugendverband', []],
                ['🕌', 'Statusbericht vom Moscheebau', []],
                ['💶', 'Finanzbericht', []],
                ['🗳️', 'Wahlen', []],
                ['🤝', 'Höflichkeitsbesuch', []],
                ['💬', 'Anliegen der Vorstandsmitglieder', []],
                ['📌', 'Verschiedenes / Sonstiges', []],
            ] as $i => $b) {
                $st->execute([$b[0], $b[1], json_encode($b[2], JSON_UNESCAPED_UNICODE), $i]);
            }
        }
    }

    // ── Protokolle ──

    public static function protokollAlle(): array
    {
        return self::get()->query("SELECT * FROM protokolle ORDER BY datum DESC, id DESC")->fetchAll();
    }

    public static function protokollById(int $id): ?array
    {
        $st = self::get()->prepare("SELECT * FROM protokolle WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    // Neues Protokoll: Standard-Teilnehmer aus den Vorlagen sind vorab angehakt
    public static function protokollErstellen(): int
    {
        $db = self::get();
        $teilnehmer = array_map(
            fn ($p) => ['name' => $p['name'], 'funktion' => $p['funktion'], 'anwesend' => (bool) $p['standard']],
            self::protokollPersonen()
        );
        $db->prepare("INSERT INTO protokolle (datum, beginn, teilnehmer, tops) VALUES (?,?,?,?)")
            ->execute([
                date('Y-m-d'),
                date('H:i'),
                json_encode($teilnehmer, JSON_UNESCAPED_UNICODE),
                json_encode([], JSON_UNESCAPED_UNICODE),
            ]);
        return (int) $db->lastInsertId();
    }

    public static function protokollUpdate(int $id, array $d): void
    {
        $set = [];
        $par = [':id' => $id];
        foreach (['datum', 'beginn', 'ende', 'status', 'teilnehmer', 'tops'] as $f) {
            if (array_key_exists($f, $d)) {
                $set[] = "$f = :$f";
                $par[":$f"] = $d[$f];
            }
        }
        if (!$set) return;
        $set[] = "geaendert_am = datetime('now','localtime')";
        self::get()->prepare("UPDATE protokolle SET " . implode(', ', $set) . " WHERE id = :id")->execute($par);
    }

    public static function protokollPersonen(): array
    {
        return self::get()->query("SELECT * FROM protokoll_personen ORDER BY sortier, id")->fetchAll();
    }

    public static function protokollBausteine(): array
    {
        return self::get()->query("SELECT * FROM protokoll_bausteine ORDER BY sortier, id")->fetchAll();
    }

    // Vorlagen komplett ersetzen (die Listen sind klein, das hält die API simpel)
    public static function protokollPersonenSpeichern(array $personen): void
    {
        $db = self::get();
        $db->beginTransaction();
        $db->exec("DELETE FROM protokoll_personen");
        $st = $db->prepare("INSERT INTO protokoll_personen (name, funktion, standard, sortier) VALUES (?,?,?,?)");
        $i = 0;
        foreach ($personen as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '') continue;
            $st->execute([$name, trim((string) ($p['funktion'] ?? '')), !empty($p['standard']) ? 1 : 0, $i++]);
        }
        $db->commit();
    }

    public static function protokollBausteineSpeichern(array $bausteine): void
    {
        $db = self::get();
        $db->beginTransaction();
        $db->exec("DELETE FROM protokoll_bausteine");
        $st = $db->prepare("INSERT INTO protokoll_bausteine (icon, titel, subs, sortier) VALUES (?,?,?,?)");
        $i = 0;
        foreach ($bausteine as $b) {
            $titel = trim((string) ($b['titel'] ?? ''));
            if ($titel === '') continue;
            $subs = array_values(array_filter(array_map('trim', (array) ($b['subs'] ?? [])), fn ($s) => $s !== ''));
            $st->execute([
                trim((string) ($b['icon'] ?? '')) ?: '📄',
                $titel,
                json_encode($subs, JSON_UNESCAPED_UNICODE),
                $i++,
            ]);
        }
        $db->commit();
    }

    // ── Spenden ──

    public static function spendeErstellen(array $d): int
    {
        $db = self::get();
        $sql = "INSERT OR IGNORE INTO spenden
            (vorname,nachname,strasse,plz,ort,email,betrag,datum,art,zahlungsweg,
             quelle,pdf_pfad,status,kommentar,mitgliedsnr,zeitraum_von,zeitraum_bis,
             sammel_ids,mail_id,mail_body,land,auslaendisch,erstellt_von)
            VALUES
            (:vorname,:nachname,:strasse,:plz,:ort,:email,:betrag,:datum,:art,:zahlungsweg,
             :quelle,:pdf_pfad,:status,:kommentar,:mitgliedsnr,:zeitraum_von,:zeitraum_bis,
             :sammel_ids,:mail_id,:mail_body,:land,:auslaendisch,:erstellt_von)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':vorname' => $d['vorname'] ?? '',
            ':nachname' => $d['nachname'] ?? '',
            ':strasse' => $d['strasse'] ?? '',
            ':plz' => $d['plz'] ?? '',
            ':ort' => $d['ort'] ?? '',
            ':email' => $d['email'] ?? '',
            ':betrag' => $d['betrag'] ?? 0,
            ':datum' => $d['datum'] ?? date('d.m.Y'),
            ':art' => $d['art'] ?? 'Geldzuwendung',
            ':zahlungsweg' => $d['zahlungsweg'] ?? 'Überweisung',
            ':quelle' => $d['quelle'] ?? 'manuell',
            ':pdf_pfad' => $d['pdf_pfad'] ?? '',
            ':status' => $d['status'] ?? 'neu',
            ':kommentar' => $d['kommentar'] ?? '',
            ':mitgliedsnr' => $d['mitgliedsnr'] ?? '',
            ':zeitraum_von' => $d['zeitraum_von'] ?? '',
            ':zeitraum_bis' => $d['zeitraum_bis'] ?? '',
            ':sammel_ids' => $d['sammel_ids'] ?? '',
            ':mail_id'     => ($d['mail_id'] ?? '') ?: null,
            ':mail_body'   => $d['mail_body'] ?? '',
            ':land'        => $d['land'] ?? '',
            ':auslaendisch'=> $d['auslaendisch'] ?? 0,
            ':erstellt_von'=> $d['erstellt_von'] ?? '',
        ]);
        return (int) $db->lastInsertId();
    }

    public static function spendeUpdate(int $id, array $felder): void
    {
        $db = self::get();
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($felder)));
        $felder['id'] = $id;
        $db->prepare("UPDATE spenden SET $sets WHERE id = :id")->execute($felder);
    }

    public static function spendeById(int $id): ?array
    {
        $db = self::get();
        $r = $db->prepare("SELECT * FROM spenden WHERE id = ?");
        $r->execute([$id]);
        return $r->fetch() ?: null;
    }

    public static function spendeAlle(string $status = 'alle', string $suche = '', string $sort = 'erstellt_am DESC', string $datumVon = '', string $datumBis = ''): array
    {
        $db = self::get();
        $where = ['1=1'];
        $params = [];
        if ($status && $status !== 'alle') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($suche) {
            $where[] = "(lower_utf8(vorname || ' ' || nachname) LIKE lower_utf8(:s) OR lower_utf8(email) LIKE lower_utf8(:s) OR CAST(betrag AS TEXT) LIKE :s)";
            $params[':s'] = "%$suche%";
        }
        if ($datumVon) {
            // Datum in DB ist TT.MM.JJJJ – umwandeln für Vergleich
            $where[] = "substr(datum,7,4)||substr(datum,4,2)||substr(datum,1,2) >= :datum_von";
            $params[':datum_von'] = str_replace('-', '', $datumVon);
        }
        if ($datumBis) {
            $where[] = "substr(datum,7,4)||substr(datum,4,2)||substr(datum,1,2) <= :datum_bis";
            $params[':datum_bis'] = str_replace('-', '', $datumBis);
        }
        $allowed_sorts = ['erstellt_am DESC', 'erstellt_am ASC', 'nachname ASC', 'nachname DESC', 'betrag ASC', 'betrag DESC'];
        if (!in_array($sort, $allowed_sorts))
            $sort = 'erstellt_am DESC';
        $sql = "SELECT *,
                    (SELECT COUNT(*) FROM spenden s2
                     WHERE s2.id != spenden.id
                       AND CASE WHEN spenden.email != ''
                                THEN lower_utf8(s2.email) = lower_utf8(spenden.email)
                                ELSE lower_utf8(s2.vorname || ' ' || s2.nachname) = lower_utf8(spenden.vorname || ' ' || spenden.nachname)
                           END
                    ) AS wiederholer
                FROM spenden WHERE " . implode(' AND ', $where) . " ORDER BY $sort";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function spendeZaehler(): array
    {
        $db = self::get();
        $rows = $db->query("SELECT status, COUNT(*) n FROM spenden GROUP BY status")->fetchAll();
        $r = ['neu' => 0, 'freigegeben' => 0, 'versendet' => 0, 'erledigt' => 0, 'fehler' => 0, 'nicht_erforderlich' => 0, 'sammelbescheinigt' => 0, 'gesamt' => 0];
        foreach ($rows as $row) {
            $r[$row['status']] = (int) $row['n'];
            $r['gesamt'] += (int) $row['n'];
        }
        return $r;
    }

    // ── Kommentare ──

    public static function kommentarAdd(int $spende_id, string $text, string $autor = '', string $typ = 'user'): void
    {
        self::get()->prepare(
            "INSERT INTO kommentare (spende_id,autor,typ,text) VALUES (?,?,?,?)"
        )->execute([$spende_id, $autor ?: 'System', $typ, $text]);
    }

    public static function kommentareBySpende(int $spende_id): array
    {
        $stmt = self::get()->prepare("SELECT * FROM kommentare WHERE spende_id = ? ORDER BY erstellt_am");
        $stmt->execute([$spende_id]);
        return $stmt->fetchAll();
    }

    // ── Mail-IDs (Duplikat-Schutz) ──

    public static function mailIdBekannt(string $mid): bool
    {
        $stmt = self::get()->prepare("SELECT 1 FROM mail_ids WHERE mail_id = ?");
        $stmt->execute([$mid]);
        return (bool) $stmt->fetch();
    }

    public static function mailIdSpeichern(string $mid): void
    {
        self::get()->prepare("INSERT OR IGNORE INTO mail_ids (mail_id) VALUES (?)")->execute([$mid]);
    }

    // ── Löschen ──

    public static function spendeLoeschen(int $id): void
    {
        $db = self::get();
        // PDF-Pfad vorher auslesen um Datei zu löschen
        $stmt = $db->prepare("SELECT pdf_pfad FROM spenden WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && $row['pdf_pfad'] && file_exists($row['pdf_pfad'])) {
            @unlink($row['pdf_pfad']);
        }
        // Kommentare löschen
        $db->prepare("DELETE FROM kommentare WHERE spende_id = ?")->execute([$id]);
        // Spende löschen
        $db->prepare("DELETE FROM spenden WHERE id = ?")->execute([$id]);
    }

    public static function allesZuruecksetzen(): void
    {
        $db = self::get();
        // Alle PDFs löschen
        $pdfs = $db->query("SELECT pdf_pfad FROM spenden WHERE pdf_pfad != ''")->fetchAll();
        foreach ($pdfs as $row) {
            if ($row['pdf_pfad'] && file_exists($row['pdf_pfad'])) {
                @unlink($row['pdf_pfad']);
            }
        }
        // Tabellen leeren
        $db->exec("DELETE FROM spenden");
        $db->exec("DELETE FROM kommentare");
        $db->exec("DELETE FROM mail_ids");
        $db->exec("DELETE FROM logs");
        $db->exec("DELETE FROM sqlite_sequence WHERE name IN ('spenden','kommentare','logs')");
    }

    // ── Logs ──

    public static function logAdd(string $level, string $msg, string $user = ''): void
    {
        self::get()->prepare("INSERT INTO logs (level,nachricht,user) VALUES (?,?,?)")->execute([$level, $msg, $user]);
    }

    public static function logsAlle(int $limit = 200): array
    {
        return self::get()
            ->query("SELECT * FROM logs ORDER BY erstellt_am DESC LIMIT $limit")
            ->fetchAll();
    }
    public static function logsAufraumen(int $tage = 90): void
    {
        self::get()->prepare(
            "DELETE FROM logs WHERE erstellt_am < datetime('now', '-' || ? || ' days')"
        )->execute([$tage]);
    }

    // ── PayPal-Transaktionen (lokaler Spiegel für die Suche) ──

    public static function paypalTxSpeichern(array $t): void
    {
        self::get()->prepare(
            "INSERT OR REPLACE INTO paypal_transaktionen
             (tx_id,datum,vorname,nachname,email,betreff,brutto,gebuehr,netto,waehrung,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $t['tx_id'], $t['datum'], $t['vorname'], $t['nachname'], $t['email'],
            $t['betreff'], $t['brutto'], $t['gebuehr'], $t['netto'], $t['waehrung'], $t['status'],
        ]);
    }

    public static function paypalTxSuchen(string $suche = '', string $von = '', string $bis = ''): array
    {
        $sql    = "SELECT t.*, s.id AS spende_id
                   FROM paypal_transaktionen t
                   LEFT JOIN spenden s ON s.mail_id = 'paypal-' || t.tx_id
                   WHERE 1=1";
        $params = [];
        if ($suche !== '') {
            $sql .= " AND (lower_utf8(t.vorname || ' ' || t.nachname) LIKE lower_utf8(?)
                      OR lower_utf8(t.email) LIKE lower_utf8(?) OR t.tx_id LIKE ?)";
            $like = "%$suche%";
            array_push($params, $like, $like, $like);
        }
        if ($von !== '') {
            $sql .= " AND t.datum >= ?";
            $params[] = $von . ' 00:00:00';
        }
        if ($bis !== '') {
            $sql .= " AND t.datum <= ?";
            $params[] = $bis . ' 23:59:59';
        }
        $sql .= " ORDER BY t.datum DESC LIMIT 1000";
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Summen pro Spender (Report) – gruppiert direkt in SQL, damit die
    // Summen über den GESAMTEN Zeitraum korrekt sind (nicht nur über die
    // ersten 1000 Zeilen). Gruppierung nach E-Mail, sonst nach Name.
    public static function paypalSummenSuchen(string $suche = '', string $von = '', string $bis = ''): array
    {
        $sql    = "SELECT MAX(t.vorname)  AS vorname,
                          MAX(t.nachname) AS nachname,
                          MAX(t.email)    AS email,
                          COUNT(*)        AS anzahl,
                          SUM(t.brutto)   AS brutto,
                          SUM(t.gebuehr)  AS gebuehr,
                          SUM(t.netto)    AS netto,
                          MIN(t.datum)    AS erste,
                          MAX(t.datum)    AS letzte
                   FROM paypal_transaktionen t
                   WHERE NOT (t.email = '' AND t.gebuehr = 0 AND t.vorname = 'Anonym')
                     AND t.email NOT LIKE '%@fb.com'
                     AND t.email NOT LIKE '%@ebay.com'";
        $params = [];
        if ($suche !== '') {
            $sql .= " AND (lower_utf8(t.vorname || ' ' || t.nachname) LIKE lower_utf8(?)
                      OR lower_utf8(t.email) LIKE lower_utf8(?) OR t.tx_id LIKE ?)";
            $like = "%$suche%";
            array_push($params, $like, $like, $like);
        }
        if ($von !== '') {
            $sql .= " AND t.datum >= ?";
            $params[] = $von . ' 00:00:00';
        }
        if ($bis !== '') {
            $sql .= " AND t.datum <= ?";
            $params[] = $bis . ' 23:59:59';
        }
        $sql .= " GROUP BY CASE WHEN t.email != '' THEN lower_utf8(t.email)
                               ELSE lower_utf8(t.vorname || ' ' || t.nachname) END
                  ORDER BY brutto DESC LIMIT 5000";
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Einzeltransaktionen EINES Spenders (für die aufklappbare Detailansicht
    // im Summen-Report). Gruppierung wie in paypalSummenSuchen: nach E-Mail,
    // sonst nach Name.
    public static function paypalTxFuerSpender(string $email, string $vorname, string $nachname, string $von = '', string $bis = ''): array
    {
        $sql    = "SELECT t.*, s.id AS spende_id
                   FROM paypal_transaktionen t
                   LEFT JOIN spenden s ON s.mail_id = 'paypal-' || t.tx_id
                   WHERE ";
        $params = [];
        if ($email !== '') {
            $sql .= "lower_utf8(t.email) = lower_utf8(?)";
            $params[] = $email;
        } else {
            $sql .= "t.email = '' AND lower_utf8(t.vorname || ' ' || t.nachname) = lower_utf8(?)";
            $params[] = trim($vorname . ' ' . $nachname);
        }
        if ($von !== '') {
            $sql .= " AND t.datum >= ?";
            $params[] = $von . ' 00:00:00';
        }
        if ($bis !== '') {
            $sql .= " AND t.datum <= ?";
            $params[] = $bis . ' 23:59:59';
        }
        $sql .= " ORDER BY t.datum DESC LIMIT 500";
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Ältere Transaktionen entfernen – behalten wird ab 1.1. vor zwei Jahren
    // (= 3-Jahres-Horizont der PayPal Transaction Search API).
    // Kein Datenverlust: die Daten liegen bei PayPal und sind jederzeit neu ladbar.
    public static function paypalTxAufraumen(): int
    {
        $grenze = (date('Y') - 2) . '-01-01 00:00:00';
        $stmt = self::get()->prepare("DELETE FROM paypal_transaktionen WHERE datum < ?");
        $stmt->execute([$grenze]);
        return $stmt->rowCount();
    }

    public static function paypalTxInfo(): array
    {
        return self::get()->query(
            "SELECT COUNT(*) AS anzahl, MIN(datum) AS aeltester, MAX(datum) AS neuester
             FROM paypal_transaktionen"
        )->fetch() ?: ['anzahl' => 0, 'aeltester' => null, 'neuester' => null];
    }

    public static function paypalTxById(string $txId): ?array
    {
        $stmt = self::get()->prepare(
            "SELECT t.*, s.id AS spende_id, s.mail_body
             FROM paypal_transaktionen t
             LEFT JOIN spenden s ON s.mail_id = 'paypal-' || t.tx_id
             WHERE t.tx_id = ?"
        );
        $stmt->execute([$txId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}