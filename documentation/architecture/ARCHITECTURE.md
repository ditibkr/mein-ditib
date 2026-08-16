# Architektur — DITIB Vereinsportal

## Übersicht

```
┌─────────────────────────────────────────────────────┐
│                   Cloudflare Zero Trust              │
│                  mein.ditib-krefeld.info              │
└─────────────────────┬───────────────────────────────┘
                      │ HTTPS
┌─────────────────────▼───────────────────────────────┐
│                 Nginx (Port 80)                      │
│         Reverse Proxy + Static Files                 │
└──────┬──────────────────────────────────────────────┘
       │
┌──────▼──────────────────────────────────────────────┐
│                PHP-FPM 8.3 (Port 9000)               │
│                Laravel 12 Application                │
│                                                      │
│  /admin    → Filament Admin Panel                    │
│  /api/*    → Laravel Sanctum API                    │
│  /         → Vue 3 SPA (Frontend)                    │
└──────┬──────────────┬───────────────────────────────┘
       │              │
┌──────▼──────┐ ┌─────▼──────────────────────────────┐
│  MySQL 8.0  │ │  Redis 7                            │
│  (Daten)    │ │  (Cache, Sessions, Queues)          │
└─────────────┘ └────────────────────────────────────┘
```

## Module

### M01 — Grundsystem
- Docker-Container-Management
- Environment-Konfiguration (.env)
- Logging (Laravel Log → Stack)
- Queue-System (Redis → Jobs)
- Scheduler (Laravel Cron in Docker)

### M02 — Benutzerverwaltung
- User-Model mit Rollen (Spatie Permission)
- Filament Shield für RBAC im Admin
- Login-History-Tracking
- API Auth via Laravel Sanctum

### M03 — Mitgliederverwaltung
- Member-Model mit Soft-Delete + Audit-Log
- MemberGroup (Pivot-Tabelle)
- MemberService (Business-Logik)
- MemberNumberService (Auto-Nummerierung)
- CSV-Import via UploadedFile
- Filament CRUD (vollständig)
- REST-API für Mitglieder

### M10 — Kommunikation
- EmailTemplate (bilingual DE/TR)
- Newsletter mit Zweisprachigkeit
- NewsletterSend (Versand-Tracking)
- SendNewsletterJob (Queue-basiert)
- Filament Admin für Newsletter

### M11 — Dashboard
- DashboardService mit Redis-Cache
- Wachstumsdaten (12 Monate)
- Filament Widgets (Stats, Charts)
- API Endpoint: GET /api/dashboard/stats

## Frontend (Vue 3 SPA)

```
src/
├── main.ts          # App-Bootstrap
├── App.vue          # Root-Komponente
├── router/          # Vue Router (History Mode)
├── stores/          # Pinia (auth, members)
├── api/             # Axios-Client + Endpunkte
├── i18n/            # DE + TR Übersetzungen
├── components/
│   ├── layout/      # AppLayout (Sidebar + Header)
│   ├── dashboard/   # StatCard, GrowthChart, CategoryChart
│   └── members/     # StatusBadge
└── pages/           # Login, Dashboard, Members, Detail, Form
```

## Sicherheit

- **Auth:** Laravel Sanctum Tokens
- **RBAC:** Spatie Permission + Filament Shield
- **DSGVO:** Soft-Delete, Audit-Log (Spatie ActivityLog)
- **Rate Limiting:** Login-Endpunkt (5/min)
- **CSRF:** Sanctum Cookie-Auth
- **HTTPS:** Erzwungen in Production
- **Sessions:** Redis (verschlüsselt in Production)

## Multi-Instance

Vorbereitet für mehrere Vereine:
- `.env` enthält `INSTANCE_ID` und `INSTANCE_NAME`
- Storage-Volumes: `instances/{instance-id}/storage`
- Datenbanken können pro Instanz getrennt werden

## Datenbank-Schema (Kern)

```
users                → login_histories
users                → roles (via Spatie)
members              ←→ member_groups (M2M)
newsletters          → newsletter_sends
email_templates      (standalone)
activity_log         (Spatie ActivityLog)
```
