<?php
/**
 * PayPal-Transaktion (API-Import) lesbar darstellen.
 * Erwartet: $tx (dekodiertes Transaktions-JSON), $spende (Spenden-Datensatz).
 * Wird als eigenständige Seite im Original-Mail-iframe angezeigt.
 */

// ── Feldnamen → deutsche Beschriftung ──
$labels = [
    'transaction_id'              => 'Transaktions-ID',
    'paypal_account_id'           => 'PayPal-Konto-ID',
    'paypal_reference_id'         => 'PayPal-Referenz-ID',
    'paypal_reference_id_type'    => 'Referenz-Typ',
    'transaction_event_code'      => 'Ereigniscode',
    'transaction_initiation_date' => 'Zahlungsdatum',
    'transaction_updated_date'    => 'Zuletzt aktualisiert',
    'transaction_amount'          => 'Betrag',
    'fee_amount'                  => 'PayPal-Gebühr',
    'ending_balance'              => 'Kontostand danach',
    'available_balance'           => 'Verfügbares Guthaben',
    'transaction_status'          => 'Status',
    'transaction_subject'         => 'Betreff',
    'transaction_note'            => 'Nachricht des Spenders',
    'invoice_id'                  => 'Rechnungsnummer',
    'custom_field'                => 'Benutzerdefiniertes Feld',
    'protection_eligibility'      => 'Käuferschutz',
    'instrument_type'             => 'Zahlungsart',
    'instrument_sub_type'         => 'Zahlungsart (Detail)',
    'account_id'                  => 'PayPal-Konto-ID',
    'email_address'               => 'E-Mail-Adresse',
    'phone_number'                => 'Telefon',
    'address_status'              => 'Adresse bestätigt',
    'payer_status'                => 'Konto verifiziert',
    'given_name'                  => 'Vorname',
    'surname'                     => 'Nachname',
    'alternate_full_name'         => 'Vollständiger Name',
    'country_code'                => 'Land',
    'line1'                       => 'Straße',
    'line2'                       => 'Adresszusatz',
    'city'                        => 'Ort',
    'state'                       => 'Bundesland/Region',
    'postal_code'                 => 'PLZ',
    'payer_name'                  => 'Name',
    'address'                     => 'Adresse',
    'name'                        => 'Name',
    'method'                      => 'Versandart',
    'item_name'                   => 'Artikel',
    'item_description'            => 'Beschreibung',
    'item_quantity'               => 'Anzahl',
    'item_amount'                 => 'Einzelpreis',
    'item_unit_price'             => 'Einzelpreis',
    'total_item_amount'           => 'Gesamtbetrag',
    'tax_amount'                  => 'Steuer',
    'invoice_number'              => 'Rechnungsnummer',
];

// ── Wertübersetzungen je Feld ──
$werte = [
    'transaction_status' => [
        'S' => '✅ Abgeschlossen',
        'P' => '⏳ Ausstehend',
        'D' => '❌ Abgelehnt',
        'V' => '↩️ Storniert / Rückerstattet',
        'F' => '❌ Fehlgeschlagen',
    ],
    'payer_status'   => ['Y' => 'Ja (verifiziert)', 'N' => 'Nein (nicht verifiziert)'],
    'address_status' => ['Y' => 'Ja (bestätigt)', 'N' => 'Nein (nicht bestätigt)'],
    'protection_eligibility' => [
        '01' => 'Berechtigt',
        '02' => 'Nicht berechtigt',
        '03' => 'Teilweise berechtigt',
    ],
    'country_code' => [
        'DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz',
        'TR' => 'Türkei', 'NL' => 'Niederlande', 'BE' => 'Belgien',
        'FR' => 'Frankreich', 'GB' => 'Großbritannien', 'US' => 'USA',
        'IT' => 'Italien', 'ES' => 'Spanien', 'PL' => 'Polen',
        'DK' => 'Dänemark', 'SE' => 'Schweden', 'LU' => 'Luxemburg',
    ],
];

// ── Abschnitte → Überschrift ──
$abschnitte = [
    'transaction_info' => '💳 Transaktion',
    'payer_info'       => '👤 Spender',
    'shipping_info'    => '📦 Lieferadresse',
    'cart_info'        => '🛒 Warenkorb',
    'auction_info'     => 'Auktion',
    'incentive_info'   => 'Gutscheine / Rabatte',
    'store_info'       => 'Händler',
];

// Feldname ohne bekannte Übersetzung lesbar machen: unterstriche → Leerzeichen
$label = fn(string $key): string => $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));

// Einzelwert formatieren (Beträge, Datum, Codes, Telefon)
$wert = function ($key, $val) use ($werte, &$wert, $label) {
    // Geldbetrag {currency_code, value}
    if (is_array($val) && isset($val['value'], $val['currency_code'])) {
        return number_format((float) $val['value'], 2, ',', '.') . ' ' . $val['currency_code'];
    }
    // Telefonnummer {country_code, national_number}
    if (is_array($val) && isset($val['national_number'])) {
        return '+' . ($val['country_code'] ?? '') . ' ' . $val['national_number'];
    }
    // Verschachteltes Objekt → Unterzeilen
    if (is_array($val)) {
        $zeilen = [];
        foreach ($val as $k => $v) {
            if ($v === '' || $v === null || $v === []) continue;
            $zeilen[] = htmlspecialchars($label($k)) . ': ' . $wert($k, $v);
        }
        return implode('<br>', $zeilen);
    }
    // ISO-Datum → deutsches Format (lokale Zeit)
    if (str_ends_with($key, '_date') && $val) {
        try {
            return (new DateTime($val))
                ->setTimezone(new DateTimeZone('Europe/Berlin'))
                ->format('d.m.Y \u\m H:i \U\h\r');
        } catch (Exception) {
        }
    }
    // Bekannte Codes übersetzen
    if (isset($werte[$key][$val])) {
        return htmlspecialchars($werte[$key][$val]);
    }
    return htmlspecialchars((string) $val);
};

// Tabelle für einen Abschnitt (Key-Value-Zeilen)
$tabelle = function (array $daten) use ($label, $wert) {
    $html = '<table>';
    foreach ($daten as $k => $v) {
        if ($v === '' || $v === null || $v === []) continue;
        $html .= '<tr><th>' . htmlspecialchars($label($k)) . '</th><td>' . $wert($k, $v) . '</td></tr>';
    }
    return $html . '</table>';
};

// ── Zusammenfassungs-Karte: Betrag + Adresse oben ──
$txInfo   = $tx['transaction_info'] ?? [];
$payer    = $tx['payer_info']       ?? [];
$shipping = $tx['shipping_info']    ?? [];

// Betrag
$betragRaw  = $txInfo['transaction_amount'] ?? [];
$betragText = is_array($betragRaw)
    ? number_format((float)($betragRaw['value'] ?? 0), 2, ',', '.') . ' ' . ($betragRaw['currency_code'] ?? 'EUR')
    : '';
$gebuehrRaw  = $txInfo['fee_amount'] ?? [];
$gebuehrText = is_array($gebuehrRaw) && !empty($gebuehrRaw['value'])
    ? '− ' . number_format(abs((float)$gebuehrRaw['value']), 2, ',', '.') . ' ' . ($gebuehrRaw['currency_code'] ?? 'EUR')
    : '';

// Datum
$datumText = '';
if (!empty($txInfo['transaction_initiation_date'])) {
    try {
        $datumText = (new DateTime($txInfo['transaction_initiation_date']))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))
            ->format('d.m.Y \u\m H:i \U\h\r');
    } catch (Exception) {}
}

// Name
$payerName = $payer['payer_name'] ?? [];
$vorname   = trim($payerName['given_name'] ?? '');
$nachname  = trim($payerName['surname']    ?? '');
if ($vorname === '' && $nachname === '') {
    $voll    = trim($payerName['alternate_full_name'] ?? '');
    $teile   = explode(' ', $voll, 2);
    $vorname = $teile[0] ?? '';
    $nachname = $teile[1] ?? '';
}
$nameText  = trim("$vorname $nachname");
$emailText = trim($payer['email_address'] ?? '');

// Adresse: Lieferadresse bevorzugen, sonst Payer-Adresse
$adresse = $shipping['address'] ?? $payer['address'] ?? [];
$strasseText = trim(($adresse['line1'] ?? '') . ' ' . ($adresse['line2'] ?? ''));
$plzOrtText  = trim(($adresse['postal_code'] ?? '') . ' ' . ($adresse['city'] ?? ''));
$landCode    = strtoupper($adresse['country_code'] ?? $payer['country_code'] ?? '');
$laender     = ['DE'=>'Deutschland','AT'=>'Österreich','CH'=>'Schweiz','TR'=>'Türkei',
                'NL'=>'Niederlande','FR'=>'Frankreich','GB'=>'Großbritannien','BE'=>'Belgien'];
$landText    = $laender[$landCode] ?? ($landCode ?: '');
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>PayPal-Transaktion</title>
<style>
  body  { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 16px; background: #fff; }
  h2    { font-size: 16px; margin: 18px 0 6px; color: #003087; border-bottom: 2px solid #0070ba; padding-bottom: 4px; }
  table { border-collapse: collapse; width: 100%; max-width: 720px; margin-bottom: 8px; }
  th, td { text-align: left; padding: 6px 10px; border: 1px solid #e0e0e0; vertical-align: top; }
  th    { width: 220px; background: #f5f8fa; font-weight: 600; color: #555; }
  tr:nth-child(even) td { background: #fafcfe; }
  .hinweis { color: #888; font-size: 12px; margin-top: 14px; }
  .summary { display:flex; gap:16px; max-width:720px; margin-bottom:18px; flex-wrap:wrap; }
  .summary-box { flex:1; min-width:200px; background:#f0f7ff; border:1px solid #0070ba33;
                 border-radius:6px; padding:12px 16px; }
  .summary-box .label { font-size:11px; color:#0070ba; font-weight:700; text-transform:uppercase;
                        letter-spacing:.5px; margin-bottom:6px; }
  .summary-box .value { font-size:20px; font-weight:700; color:#003087; }
  .summary-box .sub   { font-size:12px; color:#666; margin-top:3px; }
  .summary-box.addr   { background:#f5f8fa; border-color:#ccc; }
  .summary-box.addr .value { font-size:14px; font-weight:600; line-height:1.6; }
</style>
</head>
<body>

<!-- ── Zusammenfassung: Betrag + Adresse oben ── -->
<div class="summary">
  <?php if ($betragText): ?>
  <div class="summary-box">
    <div class="label">Betrag</div>
    <div class="value"><?= htmlspecialchars($betragText) ?></div>
    <?php if ($gebuehrText): ?>
      <div class="sub">Gebühr: <?= htmlspecialchars($gebuehrText) ?></div>
    <?php endif ?>
    <?php if ($datumText): ?>
      <div class="sub"><?= htmlspecialchars($datumText) ?></div>
    <?php endif ?>
  </div>
  <?php endif ?>

  <?php if ($nameText || $emailText || $strasseText): ?>
  <div class="summary-box addr">
    <div class="label">Spender / Adresse</div>
    <div class="value">
      <?php if ($nameText): ?>
        <?= htmlspecialchars($nameText) ?><br>
      <?php endif ?>
      <?php if ($emailText): ?>
        <span style="font-size:12px;font-weight:400;color:#555"><?= htmlspecialchars($emailText) ?></span><br>
      <?php endif ?>
      <?php if ($strasseText): ?>
        <span style="font-size:13px;font-weight:400"><?= htmlspecialchars($strasseText) ?></span><br>
      <?php endif ?>
      <?php if ($plzOrtText): ?>
        <span style="font-size:13px;font-weight:400"><?= htmlspecialchars($plzOrtText) ?></span><br>
      <?php endif ?>
      <?php if ($landText): ?>
        <span style="font-size:13px;font-weight:400"><?= htmlspecialchars($landText) ?></span>
      <?php endif ?>
    </div>
  </div>
  <?php endif ?>
</div>

<!-- ── Vollständige Details ── -->
<?php foreach ($tx as $sektion => $daten): ?>
    <?php if (!is_array($daten) || $daten === []) continue; ?>
    <h2><?= htmlspecialchars($abschnitte[$sektion] ?? $label($sektion)) ?></h2>
    <?php if ($sektion === 'cart_info' && isset($daten['item_details']) && is_array($daten['item_details'])): ?>
        <?php foreach ($daten['item_details'] as $artikel): ?>
            <?= $tabelle((array) $artikel) ?>
        <?php endforeach; ?>
    <?php else: ?>
        <?= $tabelle($daten) ?>
    <?php endif; ?>
<?php endforeach; ?>
<p class="hinweis">Quelle: PayPal-API-Import (Transaction Search) · Spende #<?= (int) ($spende['id'] ?? 0) ?></p>
</body>
</html>
