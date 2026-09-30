<?php

namespace App\PayPal;

use App\DB\Database;

/**
 * PayPal REST API Client
 * - OAuth2 Client-Credentials-Flow (Token ~9h gültig, wird pro Lauf neu geholt)
 * - Transaction Search API: /v1/reporting/transactions (max. 31 Tage pro Abfrage)
 *
 * Voraussetzung: App im PayPal Developer Dashboard mit Feature "Transaction Search".
 * Doku: https://developer.paypal.com/docs/api/transaction-search/v1/
 */
class ApiClient
{
    private array $cfg;
    private string $baseUrl;
    private ?string $token = null;

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? require ROOT . '/config/settings.php';
        $this->baseUrl = ($this->cfg['paypal_mode'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    // ══════════════════════════════════════════════════════
    //  OAuth2-Token holen
    // ══════════════════════════════════════════════════════
    public function tokenHolen(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $id     = trim($this->cfg['paypal_client_id'] ?? '');
        $secret = trim($this->cfg['paypal_secret'] ?? '');
        if ($id === '' || $secret === '') {
            throw new \RuntimeException('PayPal: client_id/secret fehlen in settings.php');
        }

        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $id . ':' . $secret,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $antwort = curl_exec($ch);
        $status  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler  = curl_error($ch);
        curl_close($ch);

        if ($antwort === false) {
            throw new \RuntimeException("PayPal: Verbindungsfehler beim Token-Holen: $fehler");
        }
        $daten = json_decode($antwort, true);
        if ($status !== 200 || empty($daten['access_token'])) {
            $detail = $daten['error_description'] ?? $daten['error'] ?? substr($antwort, 0, 200);
            throw new \RuntimeException("PayPal: Token fehlgeschlagen (HTTP $status): $detail");
        }

        $this->token = $daten['access_token'];
        return $this->token;
    }

    // ══════════════════════════════════════════════════════
    //  Transaktionen für Zeitraum abrufen (inkl. Pagination)
    //  Achtung: PayPal erlaubt max. 31 Tage pro Abfrage,
    //  Daten erscheinen mit ~3h Verzögerung.
    // ══════════════════════════════════════════════════════
    public function transaktionenAbrufen(\DateTimeInterface $von, \DateTimeInterface $bis): array
    {
        $token = $this->tokenHolen();
        $utc   = new \DateTimeZone('UTC');
        $start = \DateTime::createFromInterface($von)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
        $ende  = \DateTime::createFromInterface($bis)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');

        $alle  = [];
        $seite = 1;
        do {
            $url = $this->baseUrl . '/v1/reporting/transactions?' . http_build_query([
                'start_date' => $start,
                'end_date'   => $ende,
                'fields'     => 'transaction_info,payer_info,shipping_info',
                'page_size'  => 100,
                'page'       => $seite,
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT        => 60,
            ]);
            $antwort = curl_exec($ch);
            $status  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            $daten = json_decode((string) $antwort, true);
            if ($status !== 200 || !is_array($daten)) {
                $detail = $daten['message'] ?? substr((string) $antwort, 0, 200);
                throw new \RuntimeException("PayPal: Transaktionsabruf fehlgeschlagen (HTTP $status): $detail");
            }

            $alle = array_merge($alle, $daten['transaction_details'] ?? []);
            $gesamtSeiten = (int) ($daten['total_pages'] ?? 1);
            $seite++;
        } while ($seite <= $gesamtSeiten);

        return $alle;
    }

    // ══════════════════════════════════════════════════════
    //  Neue eingehende Zahlungen als Spenden-Datensätze
    //  (gleiches Format wie MailReader::parse) zurückgeben.
    //  Bereits importierte Transaktionen werden übersprungen
    //  (Dedup über spenden.mail_id = 'paypal-<TransaktionsID>').
    // ══════════════════════════════════════════════════════
    public function spendenAbrufen(int $tage = 3): array
    {
        $tage = min(max($tage, 1), 31);
        $bis  = new \DateTime('now');
        $von  = (new \DateTime('now'))->modify("-$tage days")->setTime(0, 0, 0);

        // Untergrenze: nie vor paypal_seit_datum importieren – Spenden davor
        // wurden bereits per Mail-Parsing erfasst (andere mail_id → Dedup greift nicht)
        $seit = trim($this->cfg['paypal_seit_datum'] ?? '');
        if ($seit !== '') {
            try {
                $seitDatum = (new \DateTime($seit))->setTime(0, 0, 0);
                if ($seitDatum > $von) {
                    $von = $seitDatum;
                }
            } catch (\Exception) {
            }
        }
        if ($von >= $bis) {
            return [];
        }

        $minBetrag = (float) ($this->cfg['mindestbetrag'] ?? 0);
        $ergebnis  = [];

        foreach ($this->transaktionenAbrufen($von, $bis) as $tx) {
            $info = $tx['transaction_info'] ?? [];
            $txId = $info['transaction_id'] ?? '';
            if ($txId === '') {
                continue;
            }

            // Nur erfolgreiche, eingehende Zahlungen (positive Beträge)
            $betrag   = (float) ($info['transaction_amount']['value'] ?? 0);
            $waehrung = $info['transaction_amount']['currency_code'] ?? 'EUR';
            if (($info['transaction_status'] ?? '') !== 'S' || $betrag <= 0) {
                continue;
            }
            if ($waehrung !== 'EUR') {
                Database::logAdd('WARN', "PayPal-API: $txId übersprungen – Währung $waehrung");
                continue;
            }
            if ($betrag < $minBetrag) {
                Database::logAdd('INFO', "PayPal-API: $txId übersprungen – Kleinbetrag $betrag €");
                continue;
            }
            if ($this->bereitsImportiert('paypal-' . $txId)) {
                continue;
            }

            $ergebnis[] = $this->mappen($tx, $txId, $betrag);
        }

        return $ergebnis;
    }

    // ══════════════════════════════════════════════════════
    //  Zeitraum in die lokale Tabelle paypal_transaktionen
    //  spiegeln (für die Transaktions-Suche im Portal).
    //  Beliebig lange Zeiträume – wird intern in 31-Tage-
    //  Blöcke zerlegt. Speichert ALLE eingehenden Zahlungen,
    //  auch unter dem Mindestbetrag.
    // ══════════════════════════════════════════════════════
    public function synchronisieren(\DateTimeInterface $von, \DateTimeInterface $bis): array
    {
        $start      = \DateTime::createFromInterface($von);
        $endeGesamt = \DateTime::createFromInterface($bis);
        $gespeichert = 0;
        $gesamt      = 0;

        while ($start < $endeGesamt) {
            $blockEnde = (clone $start)->modify('+31 days');
            if ($blockEnde > $endeGesamt) {
                $blockEnde = clone $endeGesamt;
            }

            foreach ($this->transaktionenAbrufen($start, $blockEnde) as $tx) {
                $gesamt++;
                $info = $tx['transaction_info'] ?? [];
                $txId = $info['transaction_id'] ?? '';
                $brutto = (float) ($info['transaction_amount']['value'] ?? 0);
                // Nur eingehende Zahlungen – Ausgaben/Umbuchungen überspringen
                if ($txId === '' || $brutto <= 0) {
                    continue;
                }

                $payer   = $tx['payer_info'] ?? [];
                $gebuehr = abs((float) ($info['fee_amount']['value'] ?? 0));

                $datum = '';
                if (!empty($info['transaction_initiation_date'])) {
                    try {
                        $datum = (new \DateTime($info['transaction_initiation_date']))
                            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                            ->format('Y-m-d H:i:s');
                    } catch (\Exception) {
                    }
                }

                $txVorname  = trim($payer['payer_name']['given_name'] ?? '');
                $txNachname = trim($payer['payer_name']['surname'] ?? '');
                if ($txVorname === '' && $txNachname === '') {
                    $teile      = explode(' ', trim($payer['payer_name']['alternate_full_name'] ?? ''), 2);
                    $txVorname  = $teile[0] ?? '';
                    $txNachname = $teile[1] ?? '';
                }

                Database::paypalTxSpeichern([
                    'tx_id'    => $txId,
                    'datum'    => $datum,
                    'vorname'  => $txVorname,
                    'nachname' => $txNachname,
                    'email'    => trim($payer['email_address'] ?? ''),
                    'betreff'  => trim($info['transaction_subject'] ?? $info['transaction_note'] ?? ''),
                    'brutto'   => $brutto,
                    'gebuehr'  => $gebuehr,
                    'netto'    => round($brutto - $gebuehr, 2),
                    'waehrung' => $info['transaction_amount']['currency_code'] ?? 'EUR',
                    'status'   => $info['transaction_status'] ?? '',
                ]);
                $gespeichert++;
            }

            $start = $blockEnde;
        }

        return ['gespeichert' => $gespeichert, 'gesamt' => $gesamt];
    }

    // ── Prüfen, ob die Transaktion schon als Spende existiert ──
    private function bereitsImportiert(string $mailId): bool
    {
        $stmt = Database::get()->prepare("SELECT 1 FROM spenden WHERE mail_id = ?");
        $stmt->execute([$mailId]);
        return (bool) $stmt->fetchColumn();
    }

    // ── Straße in deutsche Schreibweise bringen ──
    // PayPal liefert die Adresse teils im US-Format ("45 Gotenstrasse") oder
    // verteilt auf line1/line2 in umgekehrter Reihenfolge. Deutsche Adressen
    // beginnen nie mit der Hausnummer → steht sie vorn, wird getauscht.
    public static function strasseNormalisieren(string $strasse): string
    {
        $strasse = trim(preg_replace('/\s+/u', ' ', $strasse));
        if ($strasse === '') {
            return '';
        }

        // Hausnummer vorn: "45 Gotenstrasse", "45a Gotenstr.", "45-47, Gotenstrasse"
        $muster = '/^(\d{1,4}\s*[a-zA-Z]?(?:\s*[-\/]\s*\d{1,4}\s*[a-zA-Z]?)?)[,\s]+(\D.*)$/u';
        if (preg_match($muster, $strasse, $m)) {
            return trim($m[2]) . ' ' . trim(str_replace(' ', '', $m[1]));
        }

        return $strasse;
    }

    // ── API-Transaktion → Spenden-Datensatz (Format wie MailReader) ──
    private function mappen(array $tx, string $txId, float $betrag): array
    {
        $info     = $tx['transaction_info'] ?? [];
        $payer    = $tx['payer_info'] ?? [];
        // Adresse: Lieferadresse bevorzugen, sonst PayPal-Kontoadresse des Spenders
        $adresse  = $tx['shipping_info']['address'] ?? $payer['address'] ?? [];

        // Name: bevorzugt strukturiert, sonst Vollname aufteilen
        $vorname  = trim($payer['payer_name']['given_name'] ?? '');
        $nachname = trim($payer['payer_name']['surname'] ?? '');
        if ($vorname === '' && $nachname === '') {
            $teile    = explode(' ', trim($payer['payer_name']['alternate_full_name'] ?? ''), 2);
            $vorname  = $teile[0] ?? '';
            $nachname = $teile[1] ?? '';
        }
        $vorname  = mb_convert_case(mb_strtolower($vorname), MB_CASE_TITLE, 'UTF-8');
        $nachname = mb_convert_case(mb_strtolower($nachname), MB_CASE_TITLE, 'UTF-8');

        // Datum: ISO 8601 → dd.mm.yyyy (lokale Zeit)
        $datum = date('d.m.Y');
        if (!empty($info['transaction_initiation_date'])) {
            try {
                $datum = (new \DateTime($info['transaction_initiation_date']))
                    ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                    ->format('d.m.Y');
            } catch (\Exception) {
            }
        }

        $strasse = self::strasseNormalisieren(
            trim(($adresse['line1'] ?? '') . ' ' . ($adresse['line2'] ?? ''))
        );
        $plz     = trim($adresse['postal_code'] ?? '');
        $ort     = trim($adresse['city'] ?? '');
        $landCode = strtoupper($adresse['country_code'] ?? $payer['country_code'] ?? '');
        $land     = ($landCode === 'DE' || $landCode === '') ? 'Deutschland' : $landCode;
        // Immer als Inland behandeln – auch ausländische Spender erhalten eine Bescheinigung
        $auslaendisch = 0;

        $jahr = substr($datum, -4);

        return [
            'vorname'      => $vorname,
            'nachname'     => $nachname,
            'email'        => trim($payer['email_address'] ?? ''),
            'strasse'      => $strasse,
            'plz'          => $plz,
            'ort'          => $ort,
            'land'         => $land,
            'auslaendisch' => $auslaendisch,
            'betrag'       => $betrag,
            'datum'        => $datum,
            'art'          => "Geldzuwendung für $jahr",
            'zahlungsweg'  => 'PayPal',
            'quelle'       => 'paypal_api',
            'status'       => 'neu',
            'mail_id'      => 'paypal-' . $txId,
            'mail_body'    => json_encode($tx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ];
    }
}
