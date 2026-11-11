#!/bin/sh
# Punto de entrada de los contenedores `web` y `worker` (desarrollo).
set -eu

cd /var/www/html

# 1. Dependencias: solo el contenedor `web` instala (COFRIAL_COMPOSER_INSTALL=1);
#    el worker espera a que `web` esté saludable.
if [ "${COFRIAL_COMPOSER_INSTALL:-0}" = "1" ] && [ ! -f vendor/autoload.php ]; then
    echo "cofrial: instalando dependencias de Composer..."
    composer install --no-interaction --no-progress --prefer-dist
fi

# 2. APP_KEY: si el .env no la trae, se genera una vez y se guarda en
#    storage/docker-app.key (ignorado por git) para que web y worker compartan
#    la misma y sobreviva a los reinicios.
if [ -z "${APP_KEY:-}" ]; then
    archivo=storage/docker-app.key
    if [ ! -s "$archivo" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$archivo"
        echo "cofrial: APP_KEY vacía en .env; se generó una para desarrollo en apps/web/$archivo"
    fi
    APP_KEY="$(cat "$archivo")"
    export APP_KEY
fi

# 3. Migraciones (solo `web`, COFRIAL_MIGRAR=1): en local la base queda al día
#    sola, y el worker no arranca hasta que existan las tablas de colas y caché.
#    Migra el usuario maestro (dueño del esquema) y después se reaplican los
#    permisos y vistas de db/ (T-005), igual que en RDS.
if [ "${COFRIAL_MIGRAR:-0}" = "1" ]; then
    php artisan migrate --database=pgsql_admin --force --no-interaction
    sh /var/www/db/aplicar.sh
fi

exec "$@"
