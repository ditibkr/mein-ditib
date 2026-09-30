# Migrationsplan: Spendenportal PHP → Vereinsportal (Laravel)

**Erstellt:** 2026-07-03  
**Ziel:** Vollständige Ablösung des eigenständigen `spendenportal_php` durch das Vereinsportal (Laravel 11 + Filament 3). Die Mitgliederdaten ermöglichen dabei eine bessere Zuordnung von Spenden und eine automatische Erstellung von Mitgliedsbescheinigungen.

---

## Ausgangslage

### Spendenportal PHP (wird abgelöst)
- Eigenständiges PHP-Projekt, SQLite-Datenbank
- IMAP-basierter PayPal-Mail-Parser (kein API-Zugriff)
- PDF-Generierung per TCPDF/FPDI (Vorlage-Overlay)
- PHPMailer für Versand, eigene IMAP-Duplikatprüfung
- Manueller Freigabe-Workflow (neu → freigegeben → versendet → erledigt)
- Sprachen: Deutsch + Türkisch

### Vereinsportal (Ziel-System)
- Laravel 11 + Filament 3, MySQL 8
- Bereits vorhanden: `Donor`, `Donation`, `DonationReceipt`, `DonationProject`
- Bereits vorhanden: `DonationService` (Quittungen, Sammelbescheinigungen)
- Bereits vorhanden: `PayPalService` (direkte API-Anbindung)
- Bereits vorhanden: `PaperlessService` + `ArchiveDokumentInPaperless`-Job
- Bereits vorhanden: `Mitglied`-Modell mit Adresse, E-Mail, SEPA
- Bereits vorhanden: `BankMatchingService` (Buchungen → Mitglied)
- Bereits vorhanden: `WhatsAppService`, Newsletter, Kassenbuch
- Bereits vorhanden: `SpendenquittungMail`, `GenerateYearlyReceipts`-Job

---

## Feature-Gap-Analyse

| Feature | Spendenportal | Vereinsportal | Aktion |
|---|---|---|---|
| Spenderdaten speichern | SQLite `spenden` | `donors` + `donations` | Migration |
| PDF-Quittung (Vorlage) | TCPDF/FPDI | DomPDF/Blade | Blade-Template erstellen |
| Freigabe-Workflow | 5 Status | nur 2 Booleans | Status-Feld + Actions |
| PayPal via IMAP | ja | nein (nur API) | IMAP-Job portieren |
| Mitglied-Verknüpfung | manuell/Nr | `member_id` in Donor | BankMatchingService nutzen |
| Mitgliedsbescheinigung | ja (art-Feld) | nein | Neuer Service + Template |
| Sprachen TR/DE | ja | nur DE | Mail-Mehrsprachigkeit |
| Duplikat-Prüfung | IMAP-Header X-Spende-ID | transaktions_id | X-Spende-ID in EmailLog |
| Paperless-Archiv | nein | ja | Automatisch beim Versand |
| Kassenbuch-Buchung | nein | ja | Auto-Buchung bei Spende |
| Sammelquittung | ja | ja (GenerateYearlyReceipts) | Workflow anpassen |
| DSGVO-Auskunft | nein | teilweise | DsgvoAuskunftService erweitern |
| Kommentare/Verlauf | ja | AuditLog | AuditLog-Erweiterung |

---

## Phase 1 — Vorbereitung (keine Code-Änderungen)

### 1.1 Datenbankanalyse
Mapping der SQLite-Felder auf MySQL-Zielstruktur:

| SQLite `spenden` | MySQL Ziel | Tabelle |
|---|---|---|
| `vorname`, `nachname` | `first_name`, `last_name` | `donors` |
| `strasse`, `plz`, `ort` | `street`, `zip`, `city` | `donors` |
| `email` | `email` | `donors` |
| `betrag` | `betrag` | `donations` |
| `datum` | `datum` | `donations` |
| `art` | `typ` + `verwendungszweck` | `donations` |
| `zahlungsweg` | `zahlungsweg` | `donations` |
| `quelle` | `zahlungsweg` (paypal/manuell) | `donations` |
| `status` | neues `status`-Feld | `donations` |
| `pdf_pfad` | `pdf_pfad` | `donation_receipts` |
| `versendet_am` | `versendet_am` | `donation_receipts` |
| `mail_id` | `transaktions_id` in `bank_transactions` | Brücke |
| `mitgliedsnr` | `member_id` via Lookup | `donors` |
| `sammel_ids` | `typ = 'sammel'` + `donation_id = null` | `donation_receipts` |
| `auslaendisch` | `anonym`-ähnliches Flag oder neues Feld | `donors` |
| `kommentare` | `audit_logs` oder neue `donation_kommentare` | neu |

### 1.2 PDF-Vorlage sichern
Die Datei `pdf_templates/vorlage.pdf` muss in das Vereinsportal übernommen werden (`storage/app/private/pdf_templates/`).

### 1.3 Bestehende PDFs migrieren
Alle vorhandenen PDFs aus `output/` in Laravel-Storage übertragen:
```
spendenportal_php/output/*.pdf → vereinsportal/storage/app/private/quittungen/
```

---

## Phase 2 — Datenmigration

### 2.1 Migrations-Artisan-Command erstellen
`php artisan make:command ImportSpendenportalDaten`

Ablauf des Commands:
1. SQLite-Datei öffnen (`spenden.sqlite`)
2. Pro Zeile in `spenden`:
   - Spender anlegen oder per E-Mail/Mitgliedsnr. suchen (`findeOderErstelleSpender()`)
   - Mitglied-Verknüpfung: `mitgliedsnr` → `Mitglied::where('mitglieds_nr', ...)` → `donor->member_id`
   - `Donation` erstellen mit Statusmapping (siehe unten)
   - `DonationReceipt` erstellen wenn `pdf_pfad` vorhanden
3. `kommentare` → `AuditLog` mit `model_type = Donation`
4. `mail_ids` → Tabelle `donation_mail_ids` (neue Hilfstabelle für Duplikatschutz)

**Status-Mapping:**
```
neu              → status = 'neu'
freigegeben      → status = 'freigegeben'
versendet        → status = 'versendet', quittung_versendet = true
erledigt         → status = 'erledigt'
nicht_erforderlich → status = 'nicht_erforderlich'
sammelbescheinigt  → status = 'sammelbescheinigt'
fehler           → status = 'fehler'
```

### 2.2 Donations-Tabelle erweitern
Neue Migration: `donations`-Tabelle um folgende Felder ergänzen:
```php
$table->string('status')->default('neu');             // Workflow-Status
$table->string('quelle')->default('manuell');         // paypal / manuell / imap
$table->string('mail_id')->nullable()->unique();      // IMAP Message-ID
$table->text('mail_body')->nullable();                // Original-Mailinhalt
$table->string('sprache')->default('tr');             // Sprache der Quittungsmail
$table->string('mitgliedsnr')->nullable();            // Fallback falls kein member_id
$table->boolean('auslaendisch')->default(false);      // Kein Bescheinigungsrecht
$table->string('land')->nullable();
$table->timestamp('versendet_am')->nullable();
```

### 2.3 DonationReceipt-Tabelle ergänzen
```php
$table->string('sprache')->default('tr');             // DE / TR
$table->string('paperless_id')->nullable();           // Paperless-Dokument-ID
```

---

## Phase 3 — Feature-Implementierung

### 3.1 Freigabe-Workflow in Filament

**Datei:** `DonationResource.php`

Filament-Actions hinzufügen:
- `FreigebenAction` — Status neu → freigegeben (nur kassenwart/vereinsadmin)
- `MailVersendenAction` — Öffnet Modal mit Sprachauswahl (TR/DE), ruft `DonationService::sendReceipt()` auf
- `PostversandAction` — Status → versendet ohne Mail
- `ErledigtAction` — Status → erledigt (Abschluss)
- `NichtErforderlichAction` — für ausländische Spender oder Kleinbeträge
- `ReactiverenAction` — zurück auf neu, PDF neu generieren

Status-Badge als Filament `BadgeColumn` mit Farbzuordnung.

**Berechtigung:** Filament Shield Policies für `kassenwart`-Rolle.

### 3.2 PDF-Generierung mit FPDI-Vorlage

**Neue Klasse:** `app/Services/SpendenquittungPdfService.php`

Portierung der Logik aus `spendenportal_php/src/Pdf/Generator.php`:
- FPDI + TCPDF via `setasign/fpdi-tcpdf` (bereits in Spendenportal vorhanden)
- Vorlage: `storage/app/private/pdf_templates/vorlage.pdf`
- Felder: Spendername, Adresse, Betrag (Zahl + Wort), Datum, Art
- Dateiname: `{datum}_{vorname}_{nachname}_{donation_id}.pdf` — ID macht Duplikate unmöglich
- Output: `storage/app/private/quittungen/`
- Danach: `ArchiveDokumentInPaperless`-Job dispatchen mit Korrespondent = Spendername

`DonationService::generateReceipt()` auf neuen Service umleiten.

### 3.3 PayPal IMAP-Integration (Portierung)

**Neuer Artisan-Command:** `php artisan make:command PaypalImapImport`  
**Alternativ als Job:** `app/Jobs/ImportPayPalImapMails.php`

Portierung von `spendenportal_php/src/Imap/MailReader.php`:
- Verwendet `webklex/php-imap` (bereits im Spendenportal als Abhängigkeit)
- Schritt 1: INBOX → `spenden-eingang` verschieben (Betreff-Filter)
- Schritt 2: `spenden-eingang` verarbeiten → `BankTransaction` erstellen
- `BankMatchingService::matchTransaction()` läuft automatisch (Mitglied-Zuordnung)
- Duplikatschutz: `transaktions_id = mail_message_id` in `bank_transactions`
- Parallel zur bestehenden `PayPalService::import()` (API) nutzbar

**Konfiguration** in `.env`:
```
PAYPAL_IMAP_HOST=imap.ionos.de
PAYPAL_IMAP_USER=info@...
PAYPAL_IMAP_PASSWORD=...
PAYPAL_IMAP_FOLDER=INBOX
PAYPAL_IMAP_EINGANG=spenden-eingang
PAYPAL_IMAP_ARCHIV=spenden-archiv
PAYPAL_IMAP_KLEINBETRAG=spenden-kleinbetrag
PAYPAL_IMAP_FILTER="Zahlungseingang"
PAYPAL_MINDESTBETRAG=50
```

**Scheduler-Eintrag** in `routes/console.php`:
```php
Schedule::command('paypal:imap-import')->hourly();
```

### 3.4 Mitgliedsbescheinigung (neue Funktion)

**Neuer Service:** `app/Services/MitgliedsbescheinigungService.php`

Funktionen:
- `erstellen(Mitglied $mitglied, int $jahr, string $art = 'Mitgliedsbeitrag'): string` — PDF erstellen
- `versenden(Mitglied $mitglied, DonationReceipt $bescheinigung, string $sprache = 'tr'): void`
- `jahresbescheinigungErstellen(int $jahr): int` — für alle Mitglieder mit Zahlungseingang

Datenquellen aus Mitglied (kein manuelle Eingabe nötig):
- Name, Adresse → direkt aus `Mitglied`-Modell
- Betrag → aus Kassenbuch-Buchungen (Beiträge des Jahres) oder aus `SepaTransaction`
- Datum → Buchungsdatum oder 31.12. des Jahres

**Filament-Integration:**
- Action im `MitgliedResource` → "Mitgliedsbescheinigung erstellen"
- Eigene Filament-Page: `MitgliedsbescheinigungenPage` mit Jahresauswahl + Massenerstellen

**PDF-Template:** Separates Blade-Template `resources/views/pdf/mitgliedsbescheinigung.blade.php`  
(analog zu `spendenquittung-amtlich.blade.php` aber für Mitgliedsbeitrag)

### 3.5 Spender-Mitglied-Verknüpfung verbessern

**Erweiterung `BankMatchingService`:**
- Bei PayPal-IMAP-Import: E-Mail-Abgleich mit `mitglieder.email`
- Fallback: Name-Ähnlichkeitsabgleich (Vorname + Nachname)
- Mitgliedsnr.-Abgleich (aus `mitgliedsnr`-Feld falls vorhanden)
- Bei Treffer: `donor->member_id` setzen + Kassenbuch-Buchung zuordnen

**Filament-Action in DonationResource:**
- "Mit Mitglied verknüpfen" — Dropdown-Suche auf Mitglied, setzt `donor->member_id`
- Bei Verknüpfung: Adressdaten optional vom Mitglied übernehmen

### 3.6 Mehrsprachige Quittungs-Mails (TR + DE)

**Erweiterung `SpendenquittungMail.php`:**
```php
public function __construct(
    public DonationReceipt $quittung,
    public string $sprache = 'tr'  // neu
) {}
```

Blade-Templates:
- `resources/views/mail/spendenquittung-tr.blade.php` — türkischer Text
- `resources/views/mail/spendenquittung-de.blade.php` — deutscher Text

Betreff aus `Setting`-Modell:
- `mail_betreff_spende_tr` / `mail_betreff_spende_de`
- `mail_text_spende_tr` / `mail_text_spende_de`

### 3.7 Paperless-Archivierung für Quittungen

Erweiterung `ArchiveDokumentInPaperless`-Job:
- Typ `quittung` → Paperless-Dokumenttyp "Spendenquittung" (auto-anlegen)
- Typ `mitgliedsbescheinigung` → "Mitgliedsbescheinigung"
- Korrespondent = Spendername oder Mitgliedsname (auto-anlegen via `PaperlessService::korrespondentAnlegen()`)
- `paperless_id` in `donation_receipts` speichern

Vorschau-Link direkt in Filament-Detailansicht: `PaperlessService::getPreviewUrl($id)`.

### 3.8 Kassenbuch-Integration

Bei `DonationService::sendReceipt()` oder bei Status → versendet:
- Automatisch `KassenbuchBuchung` erstellen (Einnahme)
- Kategorie: "Spenden" (feste `KassenbuchKategorie`)
- Buchungstext: "Spende von {Spendername} – Quittung #{receipt_id}"
- `mitglied_id` wird gesetzt falls Donor ein Mitglied ist

Optional: Einstellung "Spenden automatisch ins Kassenbuch buchen" (per `Setting`-Modell).

### 3.9 DSGVO-Auskunft erweitern

**Erweiterung `DsgvoAuskunftService.php`:**
```php
// Spendendaten hinzufügen
$spendedaten = Donation::whereHas('donor', fn($q) => $q->where('member_id', $mitglied->id))
    ->with('receipts')
    ->get();
```

Enthält: Datum, Betrag, Quittungs-Status, ob Paperless-Archiv.

---

## Phase 4 — Dashboard & Statistiken

### 4.1 Spenden-Widget im Vereinsportal-Dashboard
Erweiterung `RecentDonationsWidget` (bereits vorhanden):
- Offene Freigaben (Status = freigegeben) als Aufmerksamkeits-Badge
- Monatssumme Spenden
- Vergleich Vorjahr

### 4.2 Statistiken-Seite
Erweiterung `StatistikenPage.php`:
- Spenden pro Monat/Jahr (Chart)
- Top-Spender (anonymisierbar)
- Mitglieder- vs. Extern-Spender-Anteil
- Ausstehende Quittungen (nicht versendet)
- Paperless-Archivierungsquote

---

## Phase 5 — Übergang & Abschaltung

### 5.1 Parallelbetrieb
- Spendenportal PHP läuft weiter bis alle Daten migriert und geprüft
- Neuer IMAP-Job im Vereinsportal aktiv
- Beide Systeme überwachen ob gleiche Mails ankommen (per Log-Vergleich)
- Spendenportal-PHP: cron_job deaktivieren (nicht löschen)

### 5.2 Cutover-Checkliste
- [ ] Alle bestehenden PDFs in Storage migriert und erreichbar
- [ ] Alle `spenden`-Einträge in MySQL importiert, Counts stimmen überein
- [ ] Mail-IDs migriert (kein Doppelimport)
- [ ] Mitglied-Verknüpfungen geprüft (manuelle Nacharbeit für unbekannte Spender)
- [ ] IMAP-Job im Vereinsportal läuft stabil (1 Woche Parallelbetrieb)
- [ ] Paperless: Quittungen korrekt archiviert
- [ ] Kassenbuch: Buchungen korrekt erstellt
- [ ] Mindestens eine Jahresbescheinigung testweise erstellt
- [ ] Mitgliedsbescheinigung testweise erstellt und versendet

### 5.3 Abschaltung Spendenportal PHP
Nach erfolgreichem Cutover:
1. `imap_copy_stunden`-Einstellung auf 0 setzen (keine neuen Mails mehr holen)
2. Cron-Eintrag entfernen
3. Docker-Container `spendenportal_php` stoppen (nicht löschen, 30 Tage Wartezeit)
4. SQLite-Datei archivieren (in Paperless oder als Backup)
5. Nach 30 Tagen: Container und Daten endgültig löschen

---

## Technische Abhängigkeiten

### Neue Composer-Pakete
```bash
composer require setasign/fpdi-tcpdf    # PDF-Vorlage-Overlay (wie im Spendenportal)
composer require webklex/php-imap       # IMAP-Mail-Reader (portiert aus Spendenportal)
```
*(falls noch nicht vorhanden)*

### Neue Umgebungsvariablen (.env.example)
```
# PayPal IMAP-Zugang
PAYPAL_IMAP_HOST=
PAYPAL_IMAP_PORT=993
PAYPAL_IMAP_USER=
PAYPAL_IMAP_PASSWORD=
PAYPAL_IMAP_FOLDER=INBOX
PAYPAL_IMAP_EINGANG=spenden-eingang
PAYPAL_IMAP_ARCHIV=spenden-archiv
PAYPAL_IMAP_KLEINBETRAG=spenden-kleinbetrag
PAYPAL_IMAP_FILTER="Zahlungseingang"
PAYPAL_IMAP_SEIT_DATUM=          # leer = letzte 24h
PAYPAL_MINDESTBETRAG=50

# PDF-Vorlage
SPENDENQUITTUNG_VORLAGE_PFAD=pdf_templates/vorlage.pdf
MITGLIEDSBESCHEINIGUNG_VORLAGE_PFAD=pdf_templates/mitglied_vorlage.pdf
```

---

## Reihenfolge der Umsetzung (empfohlen)

1. **Migrations-Tabellen** (neue Felder in `donations`, `donation_receipts`)
2. **Datenmigration** (Import-Command, SQLite → MySQL)
3. **SpendenquittungPdfService** (FPDI-Vorlage in Laravel)
4. **Freigabe-Workflow** (Status + Filament-Actions)
5. **Mehrsprachige Mails** (TR/DE)
6. **IMAP-Job** (PayPal-Mail-Import portieren)
7. **Paperless-Archivierung** (Auto-Archiv beim Versand)
8. **Kassenbuch-Integration** (Auto-Buchung)
9. **Mitgliedsbescheinigung** (neuer Service + Filament-Action)
10. **DSGVO-Erweiterung**
11. **Statistiken** (Dashboard-Widgets)
12. **Parallelbetrieb + Cutover**

---

## Hinweise

- **Keine Breaking Changes** im Vereinsportal solange Parallelbetrieb läuft
- **Bestehende `DonationService`-API** bleibt kompatibel — neue Methoden werden ergänzt, keine werden geändert
- **Mitgliedsbescheinigung** ist rechtlich ähnlich wie Spendenquittung — dasselbe PDF-Template kann mit anderem `art`-Feld wiederverwendet werden
- **Paperless-Typen** "Spendenquittung" und "Mitgliedsbescheinigung" müssen beim ersten Lauf in Paperless angelegt werden (einmaliger Setup via `PaperlessSetup`-Command)
- **Ausländische Spender** (kein Bescheinigungsrecht): `donor.auslaendisch = true` → kein PDF erzeugen, Status = `nicht_erforderlich`
