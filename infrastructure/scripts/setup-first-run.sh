#!/usr/bin/env bash
# Erstinstallation DITIB Vereinsportal
# Einmalig ausführen auf einem neuen Server.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
error() { echo "[FEHLER] $*" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || error "Docker ist nicht installiert."
command -v git    >/dev/null 2>&1 || error "Git ist nicht installiert."

cd "${PROJECT_DIR}"
log "=== DITIB Portal Erstinstallation ==="

if [[ ! -f .env ]]; then
    log "1/7 .env aus .env.example erstellen..."
    cp .env.example .env
    log "WICHTIG: Bitte .env jetzt anpassen (DB-Passwort, APP_KEY, MAIL etc.)"
    log "Drücke Enter wenn bereit..."
    read -r
else
    log "1/7 .env bereits vorhanden, überspringe..."
fi

log "2/7 Docker-Netzwerk und Volumes erstellen..."
docker compose up -d db redis
sleep 5

log "3/7 App-Container starten und Composer installieren..."
docker compose up -d app nginx queue scheduler

log "4/7 Composer-Abhängigkeiten installieren..."
docker compose exec -T app composer install --no-dev --optimize-autoloader

log "5/7 APP_KEY generieren und Datenbank migrieren..."
docker compose exec -T app php artisan key:generate --force
docker compose exec -T app php artisan migrate --force

log "6/7 Datenbank befüllen (Rollen + Superadmin)..."
docker compose exec -T app php artisan db:seed --force

log "7/7 Filament Shield Berechtigungen generieren..."
docker compose exec -T app php artisan filament:shield:generate --all --panel=admin

log "=== Erstinstallation abgeschlossen ==="
log ""
log "Admin-Panel: http://localhost:8000/admin"
log "Login:       admin@ditib-krefeld.de"
log "Passwort:    Admin123!  <-- SOFORT ÄNDERN!"
log ""
log "SICHERHEIT: Passwort nach erstem Login ändern!"
