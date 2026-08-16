# 🚀 CLAUDE CLI HANDOFF BRIEF
## DITIB Vereinsportal - Phase 0, 1, 2 (AUTONOM ENTWICKELN)

---

## 📋 PROJEKT-ÜBERSICHT

```
PROJEKT:          DITIB Vereinsportal (Neu, von Grund auf)
ZIEL:             Modulares, mehrsprachiges (DE/TR) Admin-Portal
ARCHITEKTUR:      Multi-Instance, Laravel-Modules, Vue 3 PWA
SCOPE:            Phase 0 + Phase 1 (+ Phase 2 wenn Zeit)

START:            JETZT
AUTONOMIE:        95% (Commits, Push, Tests, alles selbst machen)
REVIEW:           Nach Fertigstellung (ich schaue mir Code an)
```

---

## 🔧 INFRASTRUKTUR (BEREITS VORHANDEN)

```
HOSTER:           IONOS
DOMAIN:           mein.ditib-krefeld.info
OS:               Ubuntu 22.04 LTS
DOCKER:           ✅ Installiert & läuft
NGINX:            ✅ Installiert & läuft
SSH-KEY:          ✅ GitHub-Ready
GIT REPO:         ⏳ NEUEN REPO ERSTELLEN (siehe unten)
```

---

## 📁 GITHUB REPO SETUP (DU MACHST DAS - 5 MIN)

### Schritt 1: Neues Repo erstellen

```
1. github.com → "New"
2. Name: ditib-vereinsportal-neu
   (oder ditib-vereinsportal wenn altes Repo nicht mehr gebraucht)
3. Description: "Modulares Vereinsverwaltungsportal für DITIB - Multi-Instance"
4. Public / Private: DEINE WAHL
5. Initialize: NICHTS auswählen!
6. Create Repository
```

### Schritt 2: Lokal mit GitHub verbinden (DU MACHST DAS)

```bash
# Neuen Ordner erstellen
mkdir -p ~/projects/ditib-vereinsportal-neu
cd ~/projects/ditib-vereinsportal-neu

# Git initialisieren
git init
git config user.name "DITIB Vereinsportal"
git config user.email "info@ditib-krefeld.de"

# README hinzufügen
echo "# DITIB Vereinsportal" > README.md
echo "Modulares Vereinsverwaltungsportal für islamische Gemeinschaften" >> README.md

# Erste Commit
git add README.md
git commit -m "Initial commit: Project structure"

# GitHub verbinden (ERSETZE DEIN-USERNAME!)
git remote add origin git@github.com:DEIN-USERNAME/ditib-vereinsportal-neu.git
git branch -M main
git push -u origin main

# Branches für Entwicklung
git checkout -b develop
git push -u origin develop

git checkout -b feature/phase-0-backend
git push -u origin feature/phase-0-backend

# Zurück zu develop
git checkout develop
```

### Schritt 3: Ordner-Struktur vorbereiten

```bash
# Im Projekt-Ordner:
mkdir -p infrastructure/{docker,nginx,scripts}
mkdir -p deployment/{environments,templates}
mkdir -p documentation/{api,architecture,deployment}
mkdir -p .github/workflows

# Infrastructure-Basis
mkdir -p docker/{app,nginx}
mkdir -p instances/instance-001/{storage,uploads,config,database}

# Commit
git add -A
git commit -m "Add project structure"
git push
```

### Schritt 4: GitHub SSH-Key überprüfen

```bash
# SSH-Test (sollte funktionieren, wenn SSH-Key schon eingerichtet)
ssh -T git@github.com
# Sollte zeigen: "Hi DEIN-USERNAME! You've successfully authenticated..."
```

---

## ✅ PRE-FLIGHT CHECKLIST (VEREINFACHT)

Nur essentiell (alles andere ist vorhanden):

```
DOMAIN & SSL:
  ☐ Domain: mein.ditib-krefeld.info
  ☐ DNS-Records auf IONOS-Server IP zeigen
  ☐ HTTPS funktioniert: https://mein.ditib-krefeld.info
  ☐ Nginx antwortet auf Port 443

NGINX CONFIG:
  ☐ Reverse-Proxy für Port 8000 (Laravel Backend)
  ☐ Config: /etc/nginx/sites-available/mein.ditib-krefeld.info
  ☐ systemctl reload nginx = erfolgreich
  ☐ curl -I https://mein.ditib-krefeld.info → 502 OK (Backend nicht da, ist normal)

DOCKER:
  ☐ docker --version (mindestens 20.10)
  ☐ docker compose version
  ☐ /var/lib/docker/volumes vorhanden

MYSQL:
  ☐ MySQL läuft: systemctl status mysql
  ☐ Benutzer ditib_app erstellt (oder wir machen es in Docker)
  ☐ Datenbank ditib_vereinsportal erstellt

SSH:
  ☐ SSH-Zugriff auf IONOS-Server funktioniert
  ☐ Git SSH-Test erfolgreich: ssh -T git@github.com

GITHUB:
  ☐ Repo "ditib-vereinsportal-neu" erstellt
  ☐ SSH-Key zu GitHub hinzugefügt (falls noch nicht)
  ☐ Initiale Commit & Push erfolgreich
  ☐ Branches (main, develop, feature/...) vorhanden

ENVIRONMENT:
  ☐ .env.example erstellt (Template)
  ☐ .env.production erstellt (mit echten Werten - NICHT zu Git!)
  ☐ APP_KEY generiert (oder Claude macht das)
```

---

## 🎯 WAS CLAUDE CLI MACHT (AUTONOM)

Claude wird den kompletten Code schreiben für:

### PHASE 0: Backend Foundation (3-5 Tage)

```
✅ Laravel 11 Setup
   ├─ Projekt-Struktur
   ├─ Filament 3 Admin Panel
   ├─ Laravel-Modules (nwidart package)
   └─ Docker-Compose Setup

✅ Modul M01: Grundsystem
   ├─ Docker Container Management
   ├─ Laravel Framework Setup
   ├─ Database-Migrationen
   ├─ Queue/Scheduler Setup
   ├─ Vaultwarden Secrets Integration
   ├─ Monitoring & Logging
   └─ Backup-Framework

✅ Modul M02: User-, Rollen-, Rechteverwaltung
   ├─ User Model (mit language_preference)
   ├─ Role Model (vereinsadmin, kassenwart, schriftführer, imam, mitglied, etc.)
   ├─ Permission System (Filament Shield)
   ├─ 2FA Vorbereitung
   ├─ Login-History
   └─ Access Control auf alle Module

✅ Modul M03: Mitgliederverwaltung
   ├─ Member Model (Stammdaten, Kategorien, Gruppen)
   ├─ Mitgliedsnummer-Verwaltung
   ├─ Status-Management (aktiv, ruhend, ausgetreten)
   ├─ DSGVO-Audit-Log
   ├─ CSV-Import Service
   └─ Alle Relationships zu anderen Modulen

✅ Testing
   ├─ Unit Tests (M01, M02, M03)
   ├─ Feature Tests (Auth, Members, Roles)
   ├─ Database Tests (Migrations, Seeders)
   └─ >80% Code Coverage

✅ Documentation
   ├─ README mit Setup-Anleitung
   ├─ API-Dokumentation (M01, M02, M03)
   ├─ Architecture Decision Records (ADRs)
   └─ Database Schema Dokumentation

✅ Docker & Deployment
   ├─ Dockerfile für Laravel App
   ├─ docker-compose.yml (Production)
   ├─ Docker-Compose Override für Development
   ├─ Volume Mappings für Multi-Instance
   ├─ Environment Templates
   └─ Docker Health Checks

✅ CI/CD Vorbereitung
   ├─ GitHub Actions Workflow
   ├─ Automated Tests on Push
   ├─ Build & Push Docker Image
   └─ Deployment Scripts
```

### PHASE 1: Standard-Module (2-3 Tage)

```
✅ Modul M10: Kommunikation (E-Mail + Newsletter)
   ├─ Mail Service Setup
   ├─ Email Templates (DE/TR)
   ├─ Newsletter Service
   ├─ Notification Center
   └─ Tests

✅ Modul M11: Dashboard + Statistiken
   ├─ Dashboard Controller
   ├─ Widget System
   ├─ Statistik-Berechnung (Members, Cash, Donations)
   ├─ Charts Setup (vorbereitet für Vue 3)
   └─ Permission-aware Dashboard

✅ API Endpoints für Frontend
   ├─ GET /api/dashboard/stats
   ├─ GET /api/members (mit Pagination, Filtering)
   ├─ POST /api/members/import
   ├─ GET /api/auth/me
   ├─ Alle M10/M11 Endpoints
   └─ Swagger/OpenAPI Dokumentation

✅ Frontend Vorbereitung
   ├─ Vue 3 Project Setup (mit Vite)
   ├─ Tailwind CSS konfiguriert
   ├─ i18n Setup (DE/TR)
   ├─ Service Worker (PWA basics)
   ├─ Axios Client für API
   ├─ Pinia Store Setup
   └─ Component Library (erste 15-20 Komponenten)

✅ Testing
   ├─ Tests für M10, M11
   ├─ API Tests (alle M10/M11 Endpoints)
   ├─ Integration Tests
   └─ >80% Coverage
```

### PHASE 2: Finance Module (wenn Claude Zeit hat)

```
✅ Modul M07: Kassenbuch (zentral)
   ├─ Booking Model
   ├─ Account Management
   ├─ Double-Entry Accounting
   ├─ Monthly Close
   ├─ GoBD-compliant Logging
   └─ API Endpoints

✅ Modul M04: SEPA-Lastschrift
   ├─ Mandate Management
   ├─ pain.008 XML Generation
   ├─ Pre-notification
   └─ Return Handling

✅ Modul M05: Banking
   ├─ Bank Connection Service
   ├─ CSV/CAMT Import
   ├─ PayPal API Integration
   ├─ Transaction Matching
   └─ Auto-Reconciliation

✅ Modul M06: Spendenverwaltung
   ├─ Donation Tracking
   ├─ Receipt Generation (PDF)
   ├─ Batch Certificates
   └─ Tax-compliant Storage

✅ Modul M08: DATEV-Export
   ├─ EXTF CSV Generation
   ├─ Audit-safe Format
   ├─ Error Validation
   └─ Log Export
```

---

## 🔒 SICHERHEIT & BEST PRACTICES

Claude wird implementieren:

```
✅ Authentifizierung
   ├─ Laravel Sanctum (API Tokens)
   ├─ 2FA Vorbereitung
   └─ Login-Rate Limiting

✅ Autorisierung
   ├─ Filament Shield für Permissions
   ├─ Role-based Access Control (RBAC)
   ├─ Permission-aware Queries
   └─ Audit Logging für alle Änderungen

✅ Data Protection (DSGVO)
   ├─ Encrypted Sensitive Fields
   ├─ Audit Trail für Members
   ├─ Data Export funktioniert
   └─ Deletion Audit Logs

✅ Code Quality
   ├─ Laravel Standards
   ├─ PSR-12 Coding Standard
   ├─ Static Analysis (PHPStan Level 9)
   └─ Linting (Laravel Pint)

✅ Testing
   ├─ Unit Tests
   ├─ Feature Tests
   ├─ Database Tests
   └─ Minimum 80% Coverage
```

---

## 📊 DELIVERABLES NACH FERTIGSTELLUNG

Claude wird folgende Files / Repos haben:

```
GitHub Repo: ditib-vereinsportal-neu/

├── app/                           (Laravel Core)
├── modules/
│   ├── Members/
│   ├── Users/
│   ├── Communications/
│   ├── Dashboard/
│   └── [weitere Module]
├── docker/
│   ├── app/Dockerfile
│   ├── nginx/nginx.conf
│   └── mysql/my.cnf
├── docker-compose.yml             (Production)
├── docker-compose.dev.yml         (Development)
├── infrastructure/                (Deployment Scripts)
├── documentation/
│   ├── API.md
│   ├── ARCHITECTURE.md
│   ├── DEPLOYMENT.md
│   └── DATABASE.md
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── Coverage >80%
├── .github/workflows/             (CI/CD)
├── .env.example
├── README.md
├── phpunit.xml
├── pest.php
└── [alle Standard Laravel/Filament Files]

VUE 3 FRONTEND (separate oder integriert):
├── src/
│   ├── components/               (30+ Komponenten)
│   ├── pages/
│   ├── stores/                   (Pinia)
│   ├── api/
│   ├── i18n/                     (DE/TR)
│   ├── router.js
│   └── App.vue
├── public/
│   ├── manifest.json             (PWA)
│   ├── sw.js                     (Service Worker)
│   └── icons/
├── vite.config.js
├── tailwind.config.js
└── [Vue 3 Project Standards]
```

---

## 🔄 ENTWICKLUNGS-WORKFLOW

### 1️⃣ Claude startet

```bash
# Claude wird diese Befehle selbst ausführen:
git clone git@github.com:DEIN-USERNAME/ditib-vereinsportal-neu.git
cd ditib-vereinsportal-neu
git checkout develop
git checkout -b feature/phase-0-backend
```

### 2️⃣ Claude entwickelt autonom

```
- Schreibt Code für Phase 0, 1, 2
- Macht saubere, beschreibende Commits
- Testet lokal (pest/phpunit)
- Schreibt Dokumentation
- Pusht zu feature/phase-0-backend
```

### 3️⃣ Claude informiert dich

```
Nachricht an dich:
"Phase 0, 1 (und 2 wenn Zeit) sind FERTIG!
Commits: 47
Tests: 156 (100% passing)
Code Coverage: 84%
Branch: feature/phase-0-backend

GitHub: github.com/DEIN-USERNAME/ditib-vereinsportal-neu/tree/feature/phase-0-backend
"
```

### 4️⃣ Du gibst mir den Link

```
Du schreibst mir:
"Hier ist der Code: https://github.com/DEIN-USERNAME/ditib-vereinsportal-neu"

Ich:
- Clone den Repo
- Reviewe alle Commits
- Checke Tests & Coverage
- Gebe Feedback
```

### 5️⃣ Feedback-Zyklus

```
Ich schreibe Feedback:
"Issues:
1. Members.php Zeile 45: Validation Rule fehlt
2. Tests für M07 brauchen Mock für Bank API
3. docker-compose.yml Volume Path falsch

Suggestions:
1. Response-Format für API standardisieren
2. Logger-Setup centralisieren
3. Mehr Comments bei komplexer Logik"

Du gibst Claude das Feedback:
"Behebe diese Issues: [mein Feedback copy-paste]"

Claude:
- Liest Feedback
- Macht Fixes
- Schreibt neue Commits
- Pusht
- Sagt dir Bescheid

Ich schaue erneut → OK ✅
```

### 6️⃣ Go Live Preparation

```
Wenn alles OK:
- Merge feature/phase-0-backend → develop
- Merge develop → main
- Tag: v0.1.0
- Deploy vorbereitet
```

---

## 🚀 CLAUDE CLI START-BEFEHL

Sobald alles oben vorbereitet ist, gibst du Claude CLI diese Anweisung:

```
PROJECT: DITIB Vereinsportal Phase 0, 1, 2

GIT CONFIGURATION:
├─ Repository: git@github.com:DEIN-USERNAME/ditib-vereinsportal-neu.git
├─ Branch: feature/phase-0-backend
├─ Auto-Commit: YES (beschreibende Messages schreiben)
├─ Auto-Push: YES (zu Branch pushen)
└─ SSH-Key: Verfügbar

INFRASTRUCTURE:
├─ Hoster: IONOS
├─ Domain: mein.ditib-krefeld.info (HTTPS läuft)
├─ Server: Ubuntu 22.04 LTS
├─ Docker: ✅ Läuft
├─ Nginx: ✅ Läuft (Reverse Proxy Port 8000)
├─ MySQL: ✅ Läuft (ditib_vereinsportal@localhost)
└─ SSH: ✅ Funktioniert

TECHNOLOGY STACK:
├─ Backend: Laravel 11 + Filament 3 + PHP 8.3
├─ Modules: nwidart/laravel-modules
├─ Frontend: Vue 3 + Vite + Tailwind CSS
├─ Database: MySQL 8
├─ Container: Docker + Docker-Compose
├─ Auth: Laravel Sanctum (JWT Tokens)
├─ Testing: Pest + PHPUnit
└─ DevOps: GitHub Actions (CI/CD)

PHASE 0: BACKEND FOUNDATION (3-5 Tage)
├─ M01: Grundsystem (Docker, Vaultwarden, Monitoring)
├─ M02: User/Roles/Permissions (Filament Shield)
├─ M03: Mitgliederverwaltung (CSV-Import vorbereitet)
├─ API Endpoints für alle 3 Module
├─ Tests >80% Coverage
├─ Database Migrations & Seeders
├─ Docker-Compose (Prod + Dev)
├─ GitHub Actions Workflow
└─ Documentation (API, Architecture, DB Schema)

PHASE 1: STANDARD MODULES (2-3 Tage)
├─ M10: Kommunikation (E-Mail, Newsletter)
├─ M11: Dashboard (Stats, Charts)
├─ API Endpoints (GET /api/dashboard/stats, etc.)
├─ Frontend Setup (Vue 3 + Vite + Tailwind)
├─ i18n (DE/TR) für Frontend
├─ Service Worker (PWA basics)
├─ Component Library (erste 20 Komponenten)
├─ Tests >80% Coverage
└─ Swagger API Dokumentation

PHASE 2: FINANCE MODULES (wenn Zeit: 3-5 Tage)
├─ M07: Kassenbuch (Double-Entry Accounting)
├─ M04: SEPA-Lastschrift
├─ M05: Banking (CSV Import, PayPal API)
├─ M06: Spendenverwaltung
├─ M08: DATEV-Export
├─ Tests für alle Module
├─ API Endpoints
└─ Documentation

REQUIREMENTS:
✅ Alle Commits sind beschreibend
✅ Tests sind grün (100% passing)
✅ Code Coverage >80% (kritische Funktionen >90%)
✅ Code folgt Laravel Standards (PSR-12)
✅ Linting: Laravel Pint clean
✅ Static Analysis: PHPStan Level 9
✅ Docker-Compose läuft lokal
✅ Alle API-Endpoints dokumentiert (Swagger)
✅ Multi-Instance vorbereitet (.env, config/modules.php)
✅ Bilingual Setup (DE/TR) in Datenbank & Frontend

AUTONOMIE:
✅ Mache COMMITS automatisch (nicht fragen)
✅ PUSHE automatisch (nicht fragen)
✅ Schreibe TESTS (nicht fragen)
✅ Schreibe DOKUMENTATION (nicht fragen)
✅ Nutze DOCKER für alles
✅ Wenn Bug/Issue: FIX it (nicht fragen)
✅ Frag nur bei ARCHITEKTUR-Fragen

DELIVERABLE:
✅ Komplettes, funktionierendes GitHub Repo
✅ Alle Tests grün
✅ Docker läuft lokal
✅ API dokumentiert
✅ Frontend-Skeleton ready
✅ Multi-Instance vorbereitet
✅ Deployment-Scripts vorhanden
✅ README mit Setup-Anleitung
✅ Bereit für Code-Review

TIMELINE:
└─ Phase 0 + 1 = 5-8 Tage (du arbeitet parallel, ich reviewe am Ende)
```

---

## ✅ CHECKLISTE VOR DEM START

**Diese Dinge MUSST DU machen (Claude kann das nicht autonom):**

```
GIT SETUP:
  ☐ Neues GitHub Repo erstellt (ditib-vereinsportal-neu)
  ☐ Lokal initialisiert & mit GitHub verbunden
  ☐ README + erste Commit gemacht & gepusht
  ☐ Branches (main, develop, feature/phase-0-backend) erstellt
  ☐ SSH-Test erfolgreich: ssh -T git@github.com

INFRASTRUCTURE VALIDATION:
  ☐ IONOS SSH-Zugriff funktioniert
  ☐ mein.ditib-krefeld.info HTTPS antwortet
  ☐ Nginx läuft: curl -I https://mein.ditib-krefeld.info
  ☐ Docker läuft: docker ps
  ☐ MySQL läuft: systemctl status mysql
  ☐ Nginx Config für Reverse Proxy vorhanden
  ☐ Datenbank ditib_vereinsportal existiert

ENVIRONMENT:
  ☐ .env.example Template erstellt
  ☐ .env.production mit echten Werten vorhanden
  ☐ APP_KEY generiert (oder Claude macht)
  ☐ DB_PASSWORD sicher gespeichert
  ☐ .env in .gitignore

ALLES OK? → Dann sag Claude CLI START! 🚀
```

---

## 📞 WICHTIG: FRAGEN BEVOR CLAUDE STARTET

Damit Claude alles richtig macht, musst du mir folgende Fragen beantworten:

```
1. GitHub Repo URL?
   → github.com/[DEIN-USERNAME]/[REPO-NAME]

2. Wo soll Claude den Code lokal speichern?
   → /root/projects/ditib-vereinsportal-neu/
   → /home/USERNAME/projects/...
   → DEIN PATH?

3. Sollen Phase 2 Module auch gemacht werden (wenn Zeit)?
   → Ja, mach soviel wie möglich
   → Nein, nur Phase 0 + 1

4. Database-Zugang für Claude (lokal)?
   → localhost mit DB_USERNAME=ditib_app, DB_PASSWORD=...
   → Andere Konfiguration?

5. Docker-Compose PORT für Laravel?
   → 8000 (default)
   → Andere?

6. Sollen Vue 3 Frontend im selben Repo sein?
   → Ja, unter /frontend
   → Nein, separates Repo
   → Später entscheiden
```

---

## 🎯 NÄCHSTE SCHRITTE FÜR DICH

1. ✅ **Antworte auf die 6 Fragen oben**

2. ✅ **GitHub Setup** (falls noch nicht done)
   ```bash
   mkdir -p ~/projects/ditib-vereinsportal-neu
   cd ~/projects/ditib-vereinsportal-neu
   git init
   echo "# DITIB Vereinsportal" > README.md
   git add README.md
   git commit -m "Initial commit"
   git remote add origin git@github.com:DEIN-USERNAME/ditib-vereinsportal-neu.git
   git push -u origin main
   ```

3. ✅ **Mache die Checklist** (Infrastructure Validation)

4. ✅ **Sag mir Bescheid: "READY FOR CLAUDE CLI!"**

5. ✅ **Ich schreibe den finalen Brief für Claude CLI**

6. ✅ **Du kopierst den Brief → gibst ihn Claude CLI → Claude startet**

---

**DU BIST CLOSE! 🎉**

Nach den 6 Fragen + GitHub Setup bin ich ready mit dem finalen Claude CLI Brief!

