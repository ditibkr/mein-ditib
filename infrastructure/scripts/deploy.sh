#!/usr/bin/env bash
# Deployment-Skript für DITIB Vereinsportal
# Verwendung: ./infrastructure/scripts/deploy.sh [--env production|staging]
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
ENV="${1:-production}"
COMPOSE_FILE="${PROJECT_DIR}/docker-compose.yml"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
error() { echo "[FEHLER] $*" >&2; exit 1; }

[[ -f "${PROJECT_DIR}/.env" ]] || error ".env fehlt! Bitte aus .env.example kopieren und anpassen."

cd "${PROJECT_DIR}"
log "=== DITIB Portal Deployment gestartet (${ENV}) ==="

log "1/6 Neueste Version holen..."
git pull origin main

log "2/6 Docker Images bauen..."
docker compose -f "${COMPOSE_FILE}" build --no-cache app

log "3/6 Container aktualisieren (Zero-Downtime)..."
docker compose -f "${COMPOSE_FILE}" up -d --remove-orphans

log "4/6 Composer-Abhängigkeiten installieren..."
docker compose -f "${COMPOSE_FILE}" exec -T app composer install \
    --no-dev --optimize-autoloader --no-interaction

log "5/6 Laravel Setup..."
docker compose -f "${COMPOSE_FILE}" exec -T app php artisan migrate --force
docker compose -f "${COMPOSE_FILE}" exec -T app php artisan config:cache
docker compose -f "${COMPOSE_FILE}" exec -T app php artisan route:cache
docker compose -f "${COMPOSE_FILE}" exec -T app php artisan view:cache
docker compose -f "${COMPOSE_FILE}" exec -T app php artisan storage:link

log "6/6 Queue-Worker neu starten..."
docker compose -f "${COMPOSE_FILE}" restart queue

log "=== Deployment abgeschlossen ==="
log "Portal erreichbar unter: https://mein.ditib-krefeld.info/admin"
