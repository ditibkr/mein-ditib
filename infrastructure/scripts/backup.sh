#!/usr/bin/env bash
# Datenbank-Backup für DITIB Vereinsportal
# Empfohlen: täglich via Cron ausführen
# Cron: 0 2 * * * /pfad/zu/backup.sh >> /var/log/ditib-backup.log 2>&1
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
BACKUP_DIR="${PROJECT_DIR}/storage/backups"
TIMESTAMP="$(date '+%Y%m%d_%H%M%S')"
BACKUP_FILE="${BACKUP_DIR}/db_${TIMESTAMP}.sql.gz"
RETENTION_DAYS=30

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

mkdir -p "${BACKUP_DIR}"
cd "${PROJECT_DIR}"

# Datenbankzugangsdaten aus .env lesen
DB_DATABASE=$(grep '^DB_DATABASE=' .env | cut -d= -f2)
DB_USERNAME=$(grep '^DB_USERNAME=' .env | cut -d= -f2)
DB_PASSWORD=$(grep '^DB_PASSWORD=' .env | cut -d= -f2)

log "Backup wird erstellt: ${BACKUP_FILE}"

docker compose exec -T db mysqldump \
    -u "${DB_USERNAME}" \
    -p"${DB_PASSWORD}" \
    --single-transaction \
    --routines \
    --triggers \
    "${DB_DATABASE}" | gzip > "${BACKUP_FILE}"

BACKUP_SIZE=$(du -sh "${BACKUP_FILE}" | cut -f1)
log "Backup erstellt: ${BACKUP_FILE} (${BACKUP_SIZE})"

log "Alte Backups bereinigen (älter als ${RETENTION_DAYS} Tage)..."
find "${BACKUP_DIR}" -name "db_*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete

BACKUP_COUNT=$(find "${BACKUP_DIR}" -name "db_*.sql.gz" | wc -l)
log "Vorhandene Backups: ${BACKUP_COUNT}"
