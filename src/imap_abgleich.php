<?php
/**
 * IMAP-Abgleich: Alle IMAP-Einträge mit PayPal-API-Daten vergleichen und korrigieren.
 *
 * Korrekturen:
 *   1. land/strasse/plz/ort: aus mail_body HTML neu parsen (identische Logik wie MailReader)
 *   2. vorname/nachname/email: gegen paypal_transaktionen validieren (via TX-ID aus mail_body)
 *   3. betrag: Abweichung zum API-Brutto markieren (keine Auto-Korrektur)
 *
 * Aufruf: php /var/www/html/src/imap_abgleich.php [--dry-run]
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

$db = new PDO('sqlite:' . ROOT . '/data/spenden.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── HTML → Text (identisch zu MailReader::getBody) ──
function htmlZuText(string $html): string
{
    $h = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
    $h = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $h);
    $h = preg_replace('/<br\s*\/?>/i', "\n", $h);
    $h = preg_replace('/<\/p>/i', "\n", $h);
    $h = preg_replace('/<\/div>/i', "\n", $h);
    $h = preg_replace('/<\/tr>/i', "\n", $h);
    $h = preg_replace('/<\/td>/i', " ", $h);
    $h = strip_tags($h);
    $h = html_entity_decode($h, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_filter(array_map('trim', explode("\n", $h)), fn($l) => $l !== '');
    return implode("\n", array_values($lines));
}

// ── Adresse aus Text parsen (identisch zu MailReader::parse, nur Adressteil) ──
function adresseParsen(string $text): array
{
    $result = ['strasse' => '', 'plz' => '', 'ort' => '', 'land' => ''];

    if (preg_match('/Lieferadresse\s*\n\s*[^\n]+\n((?:[^\n]+\n)*?)(\d{5})\s+([^\n]+)\n?([^\n]*)/u', $text, $m)) {
        $rawLines = array_values(array_filter(
            array_map('trim', explode("\n", trim($m[1]))),
            fn($l) => $l !== ''
        ));
        $rawLines = array_values(array_filter(
            $rawLines,
            fn($l) => !preg_match('/\b(GmbH|AG|KG|OHG|e\.V\.|Inc\.|Ltd\.)\b/i', $l)
        ));
        if (count($rawLines) >= 2 && preg_match('/^\d+\s*[a-zA-Z]?\s*$/', $rawLines[1] ?? '')) {
            $result['strasse'] = ($rawLines[0] ?? '') . ' ' . trim($rawLines[1]);
        } else {
            $result['strasse'] = $rawLines[0] ?? '';
        }
        $result['plz'] = trim($m[2]);
        $result['ort'] = trim(preg_replace('/\s*(Deutschland|Germany|DE)\s*/i', '', $m[3]));
        $land = trim($m[4] ?? '');
        $result['land'] = (preg_match('/^(Deutschland|Germany|DE)$/i', $land) || $land === '')
            ? 'Deutschland' : $land;
    } elseif (preg_match('/(\d{5})\s+([A-ZÄÖÜ][a-zäöüß\s\-]+?)(?:\n|,|$)/u', $text, $m)) {
        // Fallback: PLZ + Ort irgendwo
        $result['plz'] = $m[1];
        $result['ort'] = trim($m[2]);
        $result['land'] = 'Deutschland';
    }

    return $result;
}

// ── PayPal TX-ID aus mail_body extrahieren ──
function txIdExtrahieren(string $html): ?string
{
    // PayPal TX-IDs: genau 17 Zeichen, alphanumerisch, typisches Muster
    preg_match_all('/\b([0-9A-Z]{17})\b/', $html, $m);
    foreach (array_unique($m[1] ?? []) as $kandidat) {
        // Heuristik: TX-IDs enden auf X oder enthalten Ziffern+Buchstaben gemischt
        if (preg_match('/^[0-9A-Z]{17}$/', $kandidat) && preg_match('/[0-9]/', $kandidat) && preg_match('/[A-Z]/', $kandidat)) {
            return $kandidat;
        }
    }
    return null;
}

// ── Alle IMAP-Spenden laden ──
$spenden = $db->query(
    "SELECT id, vorname, nachname, email, betrag, strasse, plz, ort, land, pdf_pfad, mail_id, mail_body
     FROM spenden WHERE quelle='mail' ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

// ── paypal_transaktionen für schnellen Lookup in Speicher laden ──
$apiTx = [];
foreach ($db->query("SELECT tx_id, vorname, nachname, email, brutto FROM paypal_transaktionen") as $row) {
    $apiTx[$row['tx_id']] = $row;
}

// ── Ergebnis-Statistik ──
$stats = [
    'gesamt'       => count($spenden),
    'land_fix'     => 0,
    'strasse_fix'  => 0,
    'plz_ort_fix'  => 0,
    'name_fix'     => 0,
    'email_fix'    => 0,
    'betrag_warn'  => 0,
    'kein_tx'      => 0,
    'unveraendert' => 0,
];

$bericht = [];

foreach ($spenden as $spende) {
    $id   = $spende['id'];
    $html = $spende['mail_body'] ?? '';
    $updates  = [];
    $hinweise = [];

    // PDF vorhanden → Eintrag und PDF nicht anfassen
    if ($spende['pdf_pfad'] !== '' && $spende['pdf_pfad'] !== null) {
        $stats['unveraendert']++;
        continue;
    }

    if ($html === '') {
        $bericht[] = "ID $id: Kein mail_body gespeichert – übersprungen";
        $stats['kein_tx']++;
        continue;
    }

    $text = htmlZuText($html);

    // ── 1. Adresse neu parsen ──
    $adresse = adresseParsen($text);

    // land: leer → setze 'Deutschland'
    if (($spende['land'] === '' || $spende['land'] === null) && $adresse['land'] !== '') {
        $updates['land'] = $adresse['land'];
        $hinweise[] = "land: '' → '{$adresse['land']}'";
        $stats['land_fix']++;
    }

    // strasse: leer → aus Neu-Parse
    if (($spende['strasse'] === '' || $spende['strasse'] === null) && $adresse['strasse'] !== '') {
        $updates['strasse'] = $adresse['strasse'];
        $hinweise[] = "strasse: '' → '{$adresse['strasse']}'";
        $stats['strasse_fix']++;
    }

    // plz/ort: leer → aus Neu-Parse
    if (($spende['plz'] === '' || $spende['plz'] === null) && $adresse['plz'] !== '') {
        $updates['plz'] = $adresse['plz'];
        $hinweise[] = "plz: '' → '{$adresse['plz']}'";
        $stats['plz_ort_fix']++;
    }
    if (($spende['ort'] === '' || $spende['ort'] === null) && $adresse['ort'] !== '') {
        $updates['ort'] = $adresse['ort'];
        $hinweise[] = "ort: '' → '{$adresse['ort']}'";
    }

    // ── 2. TX-ID extrahieren → API-Daten abgleichen ──
    $txId = txIdExtrahieren($html);
    if ($txId !== null && isset($apiTx[$txId])) {
        $api = $apiTx[$txId];

        // E-Mail: normalisieren und vergleichen
        $dbEmail  = strtolower(trim($spende['email']));
        $apiEmail = strtolower(trim($api['email']));
        if ($dbEmail !== $apiEmail && $apiEmail !== '') {
            $updates['email'] = $api['email'];
            $hinweise[] = "email: '{$spende['email']}' → '{$api['email']}' (API)";
            $stats['email_fix']++;
        } elseif ($spende['email'] !== $api['email'] && $apiEmail !== '') {
            // Nur Gross-/Kleinschreibung
            $updates['email'] = strtolower($api['email']);
            $hinweise[] = "email Schreibweise: '{$spende['email']}' → '" . strtolower($api['email']) . "'";
            $stats['email_fix']++;
        }

        // Name: prüfen ob deutlich abweichend
        // Sicherheit: API-Daten können vertauscht oder mit Sonderzeichen verschmutzt sein.
        // Nur korrigieren wenn: API-Wert nicht leer, kein Komma/Sonderzeichen enthält
        // und der volle Name (Vor+Nach) gleich bleibt (nur Aufteilung ändert sich).
        $dbVor  = mb_strtolower(trim($spende['vorname']));
        $apiVor = mb_strtolower(trim($api['vorname']));
        $dbNach  = mb_strtolower(trim($spende['nachname']));
        $apiNach = mb_strtolower(trim($api['nachname']));

        // Vollständiger Name (normalisiert) muss übereinstimmen
        $dbVollname  = preg_replace('/\s+/', ' ', trim("$dbVor $dbNach"));
        $apiVollname = preg_replace('/\s+/', ' ', trim("$apiVor $apiNach"));

        $apiVorRaw  = trim($api['vorname']);
        $apiNachRaw = trim($api['nachname']);

        // Überspringe: API-Name leer, enthält Komma/Sonderzeichen, oder Vollname weicht ab
        $apiNameSicher = $apiVorRaw !== ''
            && $apiNachRaw !== ''
            && !str_contains($apiVorRaw, ',')
            && !str_contains($apiNachRaw, ',')
            && $dbVollname === $apiVollname;  // Nur Splitting-Korrektur, kein anderer Name

        if ($apiNameSicher) {
            if ($dbVor !== $apiVor) {
                $neuerVorname = preg_replace('/\s+/', ' ', mb_convert_case($apiVorRaw, MB_CASE_TITLE, 'UTF-8'));
                $updates['vorname'] = $neuerVorname;
                $hinweise[] = "vorname: '{$spende['vorname']}' → '$neuerVorname' (API-Split-Korrektur)";
                $stats['name_fix']++;
            }
            if ($dbNach !== $apiNach) {
                $neuerNachname = preg_replace('/\s+/', ' ', mb_convert_case($apiNachRaw, MB_CASE_TITLE, 'UTF-8'));
                $updates['nachname'] = $neuerNachname;
                $hinweise[] = "nachname: '{$spende['nachname']}' → '$neuerNachname' (API-Split-Korrektur)";
                $stats['name_fix']++;
            }
        } elseif ($dbVor !== $apiVor || $dbNach !== $apiNach) {
            // Nicht automatisch korrigieren – manuell prüfen
            $hinweise[] = "Name-Abweichung (nicht korrigiert): DB='{$spende['vorname']} {$spende['nachname']}' vs API='$apiVorRaw $apiNachRaw'";
        }

        // Betrag: nur als Warnung (keine automatische Korrektur)
        $diff = abs($spende['betrag'] - $api['brutto']);
        if ($diff > 0.01) {
            $hinweise[] = "ACHTUNG Betrag: DB={$spende['betrag']} € vs API-Brutto={$api['brutto']} € (Diff=$diff) → NICHT automatisch korrigiert";
            $stats['betrag_warn']++;
        }
    } else {
        if ($txId === null) {
            $hinweise[] = "Keine TX-ID gefunden – nur Adress-Korrektur aus mail_body";
        } else {
            $hinweise[] = "TX-ID $txId nicht in paypal_transaktionen";
            $stats['kein_tx']++;
        }
    }

    // ── 3. Updates anwenden ──
    if (!empty($updates)) {
        $setParts = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($updates)));
        $updates['id'] = $id;

        if (!$dryRun) {
            $stmt = $db->prepare("UPDATE spenden SET $setParts WHERE id = :id");
            $stmt->execute($updates);
        }

        $bericht[] = "ID $id ({$spende['vorname']} {$spende['nachname']})" . ($dryRun ? ' [DRY-RUN]' : '') . ":";
        foreach ($hinweise as $h) {
            $bericht[] = "  - $h";
        }
    } else {
        $stats['unveraendert']++;
        if (!empty($hinweise)) {
            $bericht[] = "ID $id ({$spende['vorname']} {$spende['nachname']}) [keine Änderung]:";
            foreach ($hinweise as $h) {
                $bericht[] = "  ~ $h";
            }
        }
    }
}

// ── Ausgabe ──
echo ($dryRun ? "=== DRY-RUN (keine Änderungen) ===" : "=== IMAP-ABGLEICH DURCHGEFÜHRT ===") . PHP_EOL;
echo PHP_EOL;
foreach ($bericht as $zeile) {
    echo $zeile . PHP_EOL;
}
echo PHP_EOL;
echo "=== STATISTIK ===" . PHP_EOL;
echo "Gesamt IMAP-Einträge: {$stats['gesamt']}" . PHP_EOL;
echo "land korrigiert:      {$stats['land_fix']}" . PHP_EOL;
echo "strasse ergänzt:      {$stats['strasse_fix']}" . PHP_EOL;
echo "plz/ort ergänzt:      {$stats['plz_ort_fix']}" . PHP_EOL;
echo "Name korrigiert:      {$stats['name_fix']}" . PHP_EOL;
echo "E-Mail korrigiert:    {$stats['email_fix']}" . PHP_EOL;
echo "Betrag-Warnung:       {$stats['betrag_warn']}" . PHP_EOL;
echo "Ohne TX-ID/API-Match: {$stats['kein_tx']}" . PHP_EOL;
echo "Unverändert:          {$stats['unveraendert']}" . PHP_EOL;
