#!/usr/bin/env bash
# Backup manual do MySQL via container Docker (uso no host, sem mysqldump local).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
    echo "Arquivo .env não encontrado em $ROOT" >&2
    exit 1
fi

# shellcheck disable=SC1091
source <(grep -E '^(DB_DATABASE|DB_PASSWORD)=' .env | sed 's/^/export /')

DB_DATABASE="${DB_DATABASE:-controladoriasb}"
DB_PASSWORD="${DB_PASSWORD:-}"

BACKUP_DIR="${ROOT}/storage/app/backups/database"
mkdir -p "$BACKUP_DIR"

STAMP="$(TZ=America/Sao_Paulo date +%Y-%m-%d_%H%M%S)"
TARGET="${BACKUP_DIR}/${DB_DATABASE}_${STAMP}.sql.gz"

echo "Gerando ${TARGET} ..."

docker compose exec -T -e MYSQL_PWD="${DB_PASSWORD}" mysql mysqldump \
    -uroot \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    "${DB_DATABASE}" 2>/dev/null | gzip -9 > "${TARGET}"

echo "Concluído: $(du -h "${TARGET}" | cut -f1) — ${TARGET}"
