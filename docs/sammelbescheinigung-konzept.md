# Konzept: Sammelbescheinigung (Sammelzuwendungsbestätigung)

Status: **Entwurf / für später** — noch nicht umgesetzt.
Stand: 2026-07-07

## Ziel

Eine rechtlich korrekte Jahres-Sammelbescheinigung pro Spender erstellen, ohne
Zuwendungen doppelt zu erfassen. Insbesondere:

- Bereits **einzeln bescheinigte** Zahlungen (automatisch aus Mail/PayPal-API
  oder manuell) dürfen **nicht** noch einmal in einer Sammelbescheinigung
  auftauchen.
- Auch **Kleinbeträge** unter dem Mindestbetrag, die nie eine Einzelbescheinigung
  bekommen haben, sollen in die Sammelbescheinigung einfließen (genau dafür ist
  sie da).

## Rechtlicher Kern (§ 50 EStDV)

Auf einer Sammelzuwendungsbestätigung steht der Pflichtsatz:

> „Es wird bestätigt, dass über die in der Gesamtsumme enthaltenen Zuwendungen
> keine weiteren Bestätigungen ausgestellt wurden."

Diese Zusicherung muss **technisch beweisbar** sein. Übersetzt in eine Datenregel:

> **Jede Zahlung darf auf genau EINER Bescheinigung erscheinen** — entweder auf
> einer Einzelbescheinigung oder auf (genau) einer Sammelbescheinigung, nie auf
> beiden.

Wenn diese Regel auf **Datenbank-Ebene** erzwungen wird (nicht nur in der
Anzeige-Logik), ist Doppelerfassung strukturell unmöglich.

## Was heute schon existiert

- `spenden`-Tabelle hat bereits die Felder `zeitraum_von`, `zeitraum_bis`,
  `sammel_ids` — das Datenmodell kennt Sammelspenden also im Ansatz.
- Route `POST /api/jahresbescheinigungen` (`public/index.php`, ab ~Z. 971)
  erzeugt Sammelbescheinigungen: gruppiert `spenden` nach Spender, schließt
  `status = 'versendet'`, `status = 'sammelbescheinigt'` und bereits vorhandene
  Sammelzeilen aus, erzeugt **eine neue `spenden`-Zeile** und markiert die
  enthaltenen Zeilen anschließend mit `status = 'sammelbescheinigt'`.
- Das Manuell-Formular (`templates/manuell.php`) hat einen „Sammel"-Modus.

**Der Doppelzählungs-Schutz existiert also bereits** — aber nur für Zahlungen,
die schon als `spenden`-Zeile vorliegen.

## Die Lücke

Der bestehende Mechanismus arbeitet ausschließlich über `spenden`-Zeilen. Die
**PayPal-Kleinbeträge** unter Mindestbetrag wurden nie zu `spenden`-Zeilen — sie
liegen nur in `paypal_transaktionen`. Damit fehlen sie in der Sammelbescheinigung,
obwohl gerade sie dort hingehören.

## Vorschlag: „Bescheinigungs-Register"

Jede Zahlung bekommt eine Verknüpfung zu ihrer Bescheinigung. Zwei Varianten:

- **Variante A (minimal):** Spalte `bescheinigung_id` (nullable) auf
  `paypal_transaktionen`. `NULL` = noch nicht bescheinigt = Kandidat.
- **Variante B (sauber):** eigene Tabelle `bescheinigung_positionen`
  (bescheinigung_id ↔ Zahlungs-Referenz) mit **UNIQUE-Constraint auf die
  Transaktions-ID**. Damit garantiert die DB, dass eine Zahlung höchstens einer
  Bescheinigung zugeordnet ist. Erweiterbar auf Nicht-PayPal-Spenden
  (Überweisungen) mit stabiler Referenz.

Empfehlung: **Variante B** wegen der harten DB-Garantie.

Die Hälfte ist schon da: Eine PayPal-Zahlung „hat eine Einzelbescheinigung",
wenn eine `spenden`-Zeile mit `mail_id = 'paypal-' || tx_id` existiert (der
Report macht diesen JOIN bereits). Neu ist nur die Markierung für die
Kleinbeträge ohne `spenden`-Zeile.

## Punkt 3: die „neue Zeile"

Die Sammelbescheinigung ist **kein neuer Datentyp**, sondern **ein einziger neuer
Datensatz in der `spenden`-Tabelle**. Statt „eine Zahlung = eine Zeile" gilt
„eine Bescheinigung über viele Zahlungen = eine Zeile":

| Feld            | Einzelspende      | Sammelspende (neue Zeile)              |
|-----------------|-------------------|----------------------------------------|
| `betrag`        | ein Zahlungsbetrag| **Summe** aller aufgenommenen Zahlungen |
| Datum/Zeitraum  | ein `datum`       | `zeitraum_von` / `zeitraum_bis`         |
| `art`           | „Geldzuwendung"   | „Sammelbescheinigung … <Jahr>"          |
| `sammel_ids`    | leer              | Liste der enthaltenen Positionen        |
| `mail_id`       | `paypal-<txId>`   | eindeutig, z. B. `sammel-<spender>-<jahr>` |

**Warum eine Zeile in derselben Tabelle:** Damit die Sammelbescheinigung durch
den **bestehenden Workflow** läuft — PDF-Erzeugung, Versand-Freigabe,
Spendenliste, Status, 10-Jahre-Aufbewahrung, Audit. Kein Parallelsystem.

## Ablauf beim Erstellen

1. **Kandidaten sammeln:** alle Zahlungen des Spenders im Zeitraum, die *keine*
   Bescheinigung haben (kein `spenden`-Match **und** `bescheinigung_id IS NULL`).
2. **Vorschau zeigen** (zwei Blöcke):
   - ✅ *wird aufgenommen* (grün, aufsummiert)
   - ⛔ *ausgeschlossen, weil bereits bescheinigt* (grau, mit Referenz
     „Einzelbescheinigung #123 vom …" bzw. „Sammelbescheinigung #45")
3. **Erzeugen als Transaktion (alles-oder-nichts):** eine neue `spenden`-Zeile
   (siehe oben) anlegen **und im selben Vorgang** bei jeder aufgenommenen Zahlung
   die Verknüpfung setzen. Schlägt ein Schritt fehl, wird nichts geschrieben.

Ab da ist jede aufgenommene Zahlung gesperrt — ein zweiter Lauf findet sie nicht
mehr.

## Storno-Pfad (wichtig)

Wird eine Bescheinigung (einzeln oder Sammel) gelöscht/widerrufen, müssen die
zugehörigen Zahlungen wieder auf `NULL` gesetzt werden, sonst sind sie dauerhaft
„verbrannt". Korrektur = **Storno + neu**, nie stilles Editieren (Audit-Log,
10 Jahre).

## Braucht es ein neues Template?

- **PDF-Vorlage (`pdf_templates/`): ja, eine eigene ist rechtlich richtig.**
  Die aktuelle `vorlage.pdf` ist auf *eine* Zuwendung mit *einem* `<DATUM>`
  ausgelegt. Eine Sammelbescheinigung muss:
  - einen **Zeitraum** statt eines Einzeldatums zeigen,
  - den **Pflichtsatz** tragen (siehe oben),
  - idealerweise die **Einzelzuwendungen auflisten** (Datum + Betrag), ggf. auf
    einer Anlage-Seite.

  → neue Vorlage `vorlage_sammel.pdf` + Verzweigung im `Generator`, die anhand der
  `art` / eines Typs diese Vorlage wählt und Zeitraum / Erklärung / Liste stempelt.
  (Heute nutzt `jahresbescheinigungen` denselben Generator + dieselbe Einzel-
  Vorlage — rechtlich unvollständig, weil Zeitraum und Pflichtsatz fehlen.)

- **View-Template (`templates/`): nein, keine neue nötig.** Die Auslöse-/
  Vorschau-Oberfläche passt auf die bestehende `/paypal-summen`-Seite (dort gibt
  es schon „✏️ Bescheinigung" pro Spender und die aufklappbare Einzelansicht).

## Weitere Punkte / Edge Cases

- **Spender-Identität:** Gruppierung nach E-Mail/Name ist für die *Suche* okay,
  aber eine rechtsverbindliche Bescheinigung braucht **Name + Anschrift korrekt**.
  PayPal-Zahlungen haben oft keine Adresse. Deshalb Sammelbescheinigung um einen
  *bestätigten Spender-Datensatz* herum bauen (aus Einzelspende oder
  Mitgliederstamm), Zahlungen daran hängen, **mit menschlicher Bestätigung**.
- **Betrag:** immer **Brutto** (das hat der Spender gegeben); PayPal-Gebühr ist
  Vereinsaufwand.
- **Zeitraum:** Kalenderjahr ist der Standard.
- **Einfrieren nach Versand:** sobald PDF erzeugt und versendet, ist die
  Positionsliste unveränderlich.
- **Mindest-Positionsanzahl:** ggf. nur Sammelbescheinigung ab ≥ 2 Zahlungen
  (bestehende Logik überspringt Einzel-Spender).

## Empfehlung in einem Satz

Ein **Positions-Register mit DB-Uniqueness pro Zahlung** plus eine **Vorschau,
die Aufgenommenes und Ausgeschlossenes nebeneinander zeigt** — der Ausschluss
ergibt sich dann von selbst aus den Daten, und die bestehende
`jahresbescheinigungen`-Logik wird um die PayPal-Kleinbeträge erweitert.
