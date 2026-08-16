# DITIB Vereinsportal

Modulares Vereinsverwaltungsportal für DITIB Krefeld (und weitere islamische Gemeinschaften).

## Technologie-Stack

| Komponente | Version |
|-----------|---------|
| Laravel | 12.x |
| Filament Admin | 3.x |
| PHP | 8.3 |
| MySQL | 8.0 |
| Redis | 7.x |
| Docker | 20.10+ |

## Schnellstart (Lokal)

```bash
# 1. Repository klonen
git clone git@github.com:ditibkr/mein-ditib.git
cd mein-ditib

# 2. Environment vorbereiten
cp .env.example .env
# Passwörter in .env anpassen

# 3. Docker starten
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# 4. Abhängigkeiten & Setup
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan filament:shield:generate --all

# 5. Assets bauen
npm install && npm run dev
```

## Admin-Zugang (nach Seeding)

- URL: http://localhost:8000/admin
- E-Mail: admin@ditib-krefeld.de
- Passwort: Admin123! (sofort ändern!)

## Module

### Phase 0 (fertig)
- **M01** — Grundsystem: Docker, Logging, Backup-Vorbereitung
- **M02** — Benutzerverwaltung: Rollen, Rechte, Login-History
- **M03** — Mitgliederverwaltung: CRUD, CSV-Import, Statistiken

### Phase 1 (geplant)
- **M10** — Kommunikation: E-Mail, Newsletter
- **M11** — Dashboard & Statistiken

## API-Endpunkte

| Methode | Endpoint | Beschreibung |
|---------|----------|-------------|
| POST | /api/login | Anmelden (gibt Sanctum-Token zurück) |
| POST | /api/logout | Abmelden |
| GET | /api/user | Eingeloggter Benutzer |
| GET | /api/members | Mitgliederliste (paginiert) |
| POST | /api/members | Mitglied anlegen |
| GET | /api/members/{id} | Mitglied abrufen |
| PUT | /api/members/{id} | Mitglied bearbeiten |
| DELETE | /api/members/{id} | Mitglied löschen |
| GET | /api/members/statistics | Statistiken |

## Rollen

| Rolle | Beschreibung |
|-------|-------------|
| superadmin | Vollzugriff auf alle Funktionen |
| vereinsadmin | Verwaltung aller Vereinsdaten |
| kassenwart | Zugriff auf Finanzen |
| schriftführer | Mitgliederverwaltung, Protokolle |
| imam | Begrenzte Mitgliedersicht |
| mitglied | Selfservice (geplant) |

## Tests ausführen

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --coverage
```

## Deployment (Production)

```bash
# .env.production mit echten Werten füllen
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

## DSGVO

Alle Mitgliederdaten werden entsprechend der DSGVO verarbeitet:
- Audit-Log bei allen Änderungen
- Soft-Delete (kein hartes Löschen)
- DSGVO-Einwilligung mit Zeitstempel
- Passwörter nur gehasht gespeichert

## Lizenz

Proprietär — DITIB Krefeld e.V.
