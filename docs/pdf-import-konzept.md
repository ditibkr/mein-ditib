# Konzept: Import bestehender Spendenbescheinigungen (PDF)

Stand: 11.07.2026 · Status: **Konzept, noch nicht umgesetzt**

## Ausgangslage

Das Spendenportal ist jünger als die Spendenhistorie. Die älteren Bescheinigungen
wurden manuell erstellt, liegen als PDF auf einem USB-Stick und sind **nicht für
jeden Vorgang** vorhanden. Sie sollen ins Portal übernommen werden: PDF hochladen →
auslesen → Eintrag anlegen.

Referenz-Formular: `pdf_templates/Geldzuwendung_template_alt.pdf`

---

## Machbarkeit – geprüft, nicht vermutet

Alle folgenden Punkte wurden am 11.07.2026 an der echten Vorlage getestet.

### 1. Der Schreibschutz ist kein Hindernis (und braucht kein Passwort)

```
qpdf --show-encryption Geldzuwendung_template_alt.pdf
  R = 6, AESv3
  User password =            ← LEER
  modify anything: not allowed
```

Die PDFs haben **nur ein Owner-Passwort** (Schreibschutz), aber ein leeres
User-Passwort. Zum **Öffnen und Auslesen wird kein Passwort benötigt** – der Schutz
verbietet nur das Ändern. `qpdf --decrypt` entschlüsselt die Datei ohne jede
Passworteingabe.

> **Damit ist die Annahme „wir brauchen das Passwort in den Settings, um zu lesen"
> hinfällig.** Für den Import wird es nicht gebraucht.

### 2. Der Text ist echter Text, kein Scan

111 Text-Operatoren, 45 eingebettete Schriften (Calibri), 19 `ToUnicode`-Tabellen.
Die Schriften nutzen `Identity-H`-Kodierung – der Text steht also als Glyph-IDs in
der Datei und ist nur mit einem Parser lesbar, der `ToUnicode` auswertet.

### 3. Die Extraktion funktioniert vollständig

Pipeline: `qpdf --decrypt` → `smalot/pdfparser`. Ergebnis an der Vorlage:

```
Gülsen Baris
Thornstr. 58
52531 Übach-Palenberg
Betrag der Zuwendung:  107,39,-
Betrag in Buchstaben:  Einhundertsieben
Art der Zuwendung:     Geldzuwendung für 2026
Datum:                 24.02.2026
```

Alles, was ein Datenbank-Eintrag braucht, ist auslesbar.

### 4. Feldzuordnung über Koordinaten statt über Zeilenreihenfolge

Der Parser liefert den Text **nicht in Lesereihenfolge** (der Betrag erscheint vor
dem Namen). Reine Zeilen-Regex wäre also fragil. `getDataTm()` liefert aber zu jedem
Textblock X/Y-Koordinaten (146 Blöcke auf der Vorlage). Bei einem festen Formular ist
das der robuste Weg:

**Empfehlung:** Felder über **Ankertexte** finden („Betrag der Zuwendung:",
„Name und Anschrift des Zuwendenden:") und den Wert relativ dazu lesen – nicht über
fest verdrahtete Y-Werte. Ankertexte überleben kleine Layout-Verschiebungen zwischen
Formular-Versionen, absolute Koordinaten nicht.

---

## Was im PDF NICHT steht

Das ist die eigentliche Lücke:

- **keine E-Mail-Adresse**
- keine Transaktions-ID / kein Bezug zur PayPal-Zahlung
- keine eindeutige Belegnummer

Ein reiner PDF-Import erzeugt also Einträge **ohne E-Mail**. Zuordnung und
Duplikaterkennung sind nur über die Kombination **Name + Betrag + Datum** möglich.

### Deshalb: der zweite Importweg über den Gesendete-Ordner

Im IMAP-Ordner „Gesendete Objekte" liegen **4.118 Mails, zurück bis 2018**. Ab
mindestens 2021 finden sich dort Antworten („AW: Mitteilung über erhaltene Zahlung")
**mit PDF-Anhängen** – die alten Bescheinigungen wurden also per Mail verschickt.

Dort bekommt man PDF **und** Empfänger-E-Mail **und** Versanddatum aus einem Objekt.
Das ist qualitativ deutlich besser als der USB-Stick.

**Empfehlung: beide Wege bauen, mit demselben Parser.**

| Weg | Quelle | liefert | Rolle |
|---|---|---|---|
| A | Upload (USB-Stick) | PDF | Rückfallebene für nie gemailte Bescheinigungen |
| B | IMAP „Gesendete Objekte" | PDF + E-Mail + Datum | bevorzugt, weil vollständiger |

---

## Ablauf (beide Wege)

1. **Einlesen** – Upload bzw. IMAP-Abruf, Original unverändert ablegen
2. **Entschlüsseln** – `qpdf --decrypt` in eine *temporäre* Kopie
3. **Extrahieren** – Text + Koordinaten, Felder über Ankertexte
4. **Plausibilisieren** – siehe unten
5. **Vorschau** – Tabelle mit Ampel: ✅ sicher · ⚠ unsicher · ⛔ Duplikat
6. **Bestätigen** – erst dann werden Einträge geschrieben

**Nichts wird ohne Bestätigung in die Datenbank geschrieben.** Der Import ist ein
Vorschlag, keine Automatik.

### Eingebaute Prüfsumme nutzen

Das Formular enthält den Betrag **zweimal**: als Ziffern (`107,39,-`) und in
Buchstaben (`Einhundertsieben`). Das ist eine geschenkte Gegenprobe: Stimmen beide
nicht überein, ist die Zeile automatisch „unsicher" und muss manuell geprüft werden.

---

## Risiken

### 1. Mehrere Formular-Versionen über die Jahre — **das Hauptrisiko**
Im Ordner `pdf_templates/` liegen bereits `vorlage.pdf`, `vorlage_alt.pdf`,
`vorlage110626.pdf`, `Geldzuwendung_template_alt.pdf`. Über mehrere Jahre manueller
Erstellung sind Layout-Abweichungen fast sicher.
**Folge:** Felder werden falsch oder gar nicht erkannt.
**Gegenmaßnahme:** Ankertext-basierte Erkennung; erkennt der Parser einen Anker
nicht, wird die Datei als „nicht lesbar" markiert statt geraten. Vor der Umsetzung
brauche ich **3–5 echte Beispiel-PDFs aus verschiedenen Jahren**.

### 2. Stille Fehlzuordnung
Ein falsch gelesener Betrag oder Name erzeugt einen falschen Eintrag – und der ist
steuerlich relevant.
**Gegenmaßnahme:** Betrag-in-Buchstaben als Gegenprobe, Pflicht-Vorschau,
Ampel-Kennzeichnung, kein stiller Auto-Import.

### 3. Doppelerfassung mit den PayPal-Daten
Dieselbe Spende kann als PayPal-Transaktion **und** als importiertes PDF existieren →
das Dashboard zählt sie doppelt.
**Gegenmaßnahme:** Abgleich Name + Betrag + Datum (± wenige Tage) gegen `spenden`
*und* `paypal_transaktionen`; Treffer werden als Duplikat vorgeschlagen, niemals
automatisch zusammengeführt. Zusätzlich `quelle = 'pdf_import'` setzen, damit der
Ursprung jederzeit erkennbar ist. Und: ein klarer **Stichtag**, ab dem das Portal
zuständig ist – davor PDF-Import, danach PayPal.

### 4. Der Import darf keine neue Bescheinigung erzeugen
Sonst existieren zwei Bescheinigungen für denselben Vorgang – eine beim Spender, eine
neu erzeugte. Das ist derselbe Fall wie der Archivschutz bei versendeten
Bescheinigungen.
**Gegenmaßnahme:** Importierte Fälle bekommen das **Original-PDF** als `pdf_pfad` und
Status `erledigt`. Keine Neuerzeugung, kein Versand.

### 5. Entschlüsselte Kopien dürfen nicht ins Archiv
`qpdf --decrypt` entfernt den Schreibschutz. Diese Kopie ist ein Arbeitsmittel und
muss nach dem Auslesen gelöscht werden. Ins Archiv kommt **ausschließlich das
unveränderte Original**.

### 6. Scans ohne Textebene
Falls einzelne alte Bescheinigungen eingescannt wurden (Papier mit Unterschrift),
enthalten sie keinen Text – die Extraktion liefert 0 Zeichen.
**Gegenmaßnahme:** erkennen und aussortieren („nicht lesbar, bitte manuell
erfassen"). OCR (Tesseract) wäre möglich, hat aber eine deutlich höhere Fehlerquote –
bei steuerlich relevanten Beträgen würde ich davon abraten, jedenfalls nicht ohne
Sichtprüfung jeder Zeile.

### 7. Datenschutz
Die PDFs enthalten Namen und Anschriften. Der Upload-Ordner darf **nicht** unter
`public/` liegen und nicht über das Web erreichbar sein.

### 8. Menge und Laufzeit
Bei mehreren hundert oder tausend PDFs läuft ein Web-Request in den Timeout.
**Gegenmaßnahme:** Verarbeitung in Blöcken bzw. als Hintergrund-Job.

### 9. Die Dashboard-Auswertung kennt nur PayPal
Die Diagramme basieren zu 100 % auf `paypal_transaktionen`. **Importierte Altfälle
würden dort nicht auftauchen.** Sollen sie in die Auswertung einfließen, braucht das
Dashboard eine zweite Datenquelle. Das ist eine Entscheidung, keine Kleinigkeit.

---

## Das Passwort gehört trotzdem in die Settings – aber woanders

Zum *Lesen* wird kein Passwort gebraucht. Es gibt aber eine echte Fundstelle:

**`src/Pdf/Generator.php` hat das Owner-Passwort der NEUEN PDFs fest im Code:**

```php
$ownerPw = 'DITIB_OWNER_' . $jahr;
```

Das widerspricht der Projektregel „Secrets nie im Code" und gehört nach
`config/settings.php` (`pdf_owner_passwort`), mit Feld in den Einstellungen.

Zusätzlich sinnvoll: ein optionales Feld `pdf_import_passwort` als **Rückfallebene**,
falls doch einzelne alte PDFs ein echtes Öffnen-Passwort haben. Beim Import wird
zuerst ohne Passwort versucht; scheitert das, wird dieses Passwort benutzt.

---

## Offene Fragen (blockieren die Umsetzung)

1. **Wie viele PDFs, welcher Zeitraum?** Und bitte 3–5 echte Beispiele aus
   verschiedenen Jahren – daran zeigt sich, wie viele Formular-Versionen es gibt.
2. **Sollen die Altfälle in die Dashboard-Auswertung einfließen?** (siehe Risiko 9)
3. **Ab welchem Stichtag ist das Portal zuständig?** (siehe Risiko 3)
4. Bevorzugter Weg: erst IMAP (vollständiger) oder erst USB-Upload?

## Technische Abhängigkeit

Neu nötig: `smalot/pdfparser` (Composer). `qpdf` ist im Container bereits vorhanden
und wird schon von der PDF-Erzeugung genutzt.
