#!/bin/sh
# =============================================================================
# Aplica roles, permisos y vistas de db/ sobre la base de negocio (T-005,
# vistas de BI en T-020).
#
# Toma todo del entorno (el .env de la raíz) y se conecta con el usuario
# maestro. Idempotente: correrlo después de cada `migrate`.
#
#   set -a; . ./.env; set +a; sh db/aplicar.sh         # desde el equipo
#   docker compose -f infra/docker/compose.dev.yml exec web sh /var/www/db/aplicar.sh
#
# Contra RDS (desde una EC2 de la VPC, con DB_HOST = endpoint y
# DB_SSLMODE=require): mismo comando. Ver db/README.md.
# =============================================================================
set -eu

dir=$(dirname "$0")

for variable in DB_HOST DB_DATABASE DB_ADMIN_USERNAME DB_ADMIN_PASSWORD DB_PASSWORD IOT_DB_PASSWORD BI_DB_PASSWORD GF_DATABASE_PASSWORD; do
    eval "valor=\${$variable:-}"
    if [ -z "$valor" ]; then
        echo "aplicar.sh: falta la variable $variable (revisa el .env)" >&2
        exit 1
    fi
done

export PGHOST="$DB_HOST"
export PGPORT="${DB_PORT:-5432}"
export PGDATABASE="$DB_DATABASE"
export PGUSER="$DB_ADMIN_USERNAME"
export PGPASSWORD="$DB_ADMIN_PASSWORD"
export PGSSLMODE="${DB_SSLMODE:-prefer}"
export PGCONNECT_TIMEOUT="${DB_CONNECT_TIMEOUT:-10}"

psql -X -q \
    -v app_user="${DB_USERNAME:-app_rw}" -v app_pass="$DB_PASSWORD" \
    -v iot_user="${IOT_DB_USERNAME:-iot_writer}" -v iot_pass="$IOT_DB_PASSWORD" \
    -v bi_user="${BI_DB_USERNAME:-bi_reader}" -v bi_pass="$BI_DB_PASSWORD" \
    -v gf_user="${GF_DATABASE_USER:-grafana_rw}" -v gf_pass="$GF_DATABASE_PASSWORD" \
    -v gf_db="${GF_DATABASE_NAME:-grafana}" \
    -f "$dir/roles.sql"

# Las vistas dependen de las tablas: antes de la primera migración no se crean.
if [ "$(psql -X -tAc "select to_regclass('camion') is not null")" = "t" ]; then
    psql -X -q -v iot_user="${IOT_DB_USERNAME:-iot_writer}" -f "$dir/vistas_iot.sql"
    psql -X -q -v bi_user="${BI_DB_USERNAME:-bi_reader}" -f "$dir/vistas_bi.sql"
else
    echo "aplicar.sh: aún no hay tablas; vistas_iot.sql y vistas_bi.sql se aplicarán después de migrar."
fi
