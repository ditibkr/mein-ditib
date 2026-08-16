#!/usr/bin/env bash
# Health-Check für DITIB Vereinsportal
# Prüft alle Dienste und gibt Status aus.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

log()   { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
ok()    { echo "  [OK]     $*"; }
warn()  { echo "  [WARN]   $*"; }
fail()  { echo "  [FEHLER] $*"; FAILED=1; }

FAILED=0
cd "${PROJECT_DIR}"

log "=== DITIB Portal Health-Check ==="

# Docker-Dienste
log "Container-Status:"
for svc in app nginx db redis queue; do
    STATUS=$(docker compose ps --format "{{.Status}}" "${svc}" 2>/dev/null || echo "nicht gefunden")
    if echo "${STATUS}" | grep -qi "running\|up"; then
        ok "${svc}: läuft"
    else
        fail "${svc}: ${STATUS}"
    fi
done

# HTTP-Endpunkt
log "HTTP-Check:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/ 2>/dev/null || echo "000")
if [[ "${HTTP_CODE}" =~ ^(200|302|301)$ ]]; then
    ok "HTTP ${HTTP_CODE} - Nginx antwortet"
else
    fail "HTTP ${HTTP_CODE} - Portal nicht erreichbar"
fi

# Datenbank
log "Datenbankverbindung:"
if docker compose exec -T app php artisan db:show --count 2>/dev/null | grep -q "Tables"; then
    ok "MySQL-Verbindung OK"
else
    DB_TEST=$(docker compose exec -T app php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>&1 | grep -c "OK" || true)
    if [[ "${DB_TEST}" -gt 0 ]]; then
        ok "MySQL-Verbindung OK"
    else
        fail "MySQL-Verbindung fehlgeschlagen"
    fi
fi

# Redis
log "Redis:"
REDIS_TEST=$(docker compose exec -T app php artisan tinker --execute="echo Cache::store('redis')->put('health','ok',10) ? 'OK' : 'FAIL';" 2>&1 | grep -c "OK" || true)
if [[ "${REDIS_TEST}" -gt 0 ]]; then
    ok "Redis-Verbindung OK"
else
    warn "Redis-Verbindung konnte nicht geprüft werden"
fi

# Queue
log "Queue-Worker:"
QUEUE_STATUS=$(docker compose ps --format "{{.Status}}" queue 2>/dev/null || echo "")
if echo "${QUEUE_STATUS}" | grep -qi "running\|up"; then
    ok "Queue-Worker aktiv"
else
    fail "Queue-Worker nicht aktiv"
fi

log "================================"
if [[ "${FAILED}" -eq 0 ]]; then
    log "Alle Checks bestanden."
    exit 0
else
    log "Es gibt Probleme! Bitte Logs prüfen:"
    log "  docker compose logs --tail=50 app"
    exit 1
fi
