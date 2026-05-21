#!/bin/sh
set -e

APP_DIR=/var/www/html

fix_permissions() {
    if [ ! -d "${APP_DIR}/storage" ] || [ ! -d "${APP_DIR}/bootstrap/cache" ]; then
        return 0
    fi

    chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
    chmod -R ug+rwx "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true

    if [ -d "${APP_DIR}/storage/app/private" ]; then
        find "${APP_DIR}/storage/app/private" -type d -exec chmod 775 {} \; 2>/dev/null || true
        find "${APP_DIR}/storage/app/private" -type f -exec chmod 664 {} \; 2>/dev/null || true
        chmod g+s "${APP_DIR}/storage/app/private" 2>/dev/null || true
    fi
}

wait_for_vendor() {
    i=0
    max=90

    while [ ! -f "${APP_DIR}/vendor/autoload.php" ]; do
        i=$((i + 1))
        if [ "${i}" -ge "${max}" ]; then
            echo "ERRO: ${APP_DIR}/vendor/autoload.php ausente após ${max} tentativas."
            echo "Execute: docker compose exec controladoriasb composer install"
            exit 1
        fi
        echo "Aguardando vendor/autoload.php... (${i}/${max})"
        sleep 2
    done
}

ensure_storage_link() {
    if [ ! -e "${APP_DIR}/public/storage" ] && [ -f "${APP_DIR}/artisan" ]; then
        php "${APP_DIR}/artisan" storage:link --no-interaction 2>/dev/null || true
    fi
}

case "$1" in
    apache2-foreground)
        fix_permissions
        ensure_storage_link
        ;;
    php)
        if [ "${2:-}" = "artisan" ]; then
            wait_for_vendor
            fix_permissions
        fi
        ;;
esac

exec "$@"
