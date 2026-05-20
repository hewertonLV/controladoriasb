#!/bin/sh
set -e

APP_DIR=/var/www/html

fix_permissions() {
    if [ ! -d "${APP_DIR}/storage" ] || [ ! -d "${APP_DIR}/bootstrap/cache" ]; then
        return 0
    fi

    chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
    chmod -R ug+rwx "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
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

case "$1" in
    apache2-foreground)
        fix_permissions
        ;;
    php)
        if [ "${2:-}" = "artisan" ]; then
            wait_for_vendor
            fix_permissions
        fi
        ;;
esac

exec "$@"
