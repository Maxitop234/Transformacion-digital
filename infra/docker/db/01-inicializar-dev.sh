#!/bin/sh
# Inicialización de la base LOCAL (solo corre la primera vez, con el volumen vacío).
# - La base de negocio `cofrial` pertenece al usuario maestro (`postgres`), que
#   corre las migraciones; los usuarios y permisos salen de db/roles.sql, igual
#   que en RDS (T-005). Las tablas y la vista IoT las completa el contenedor
#   `web` al migrar (db/aplicar.sh).
# - PostGIS se habilita aquí porque crear la extensión exige superusuario.
# - `cofrial_test` es la base de los tests (apps/web/phpunit.xml). Ahí `app_rw`
#   sí es dueño, porque los tests recrean el esquema; no es la base de negocio.
# - Grafana guarda su estado interno en su propia base (ADR-0010).
set -eu

psql -X -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -v app_user="$DB_USERNAME" -v app_pass="$DB_PASSWORD" \
    -v iot_user="$IOT_DB_USERNAME" -v iot_pass="$IOT_DB_PASSWORD" \
    -v bi_user="$BI_DB_USERNAME" -v bi_pass="$BI_DB_PASSWORD" \
    -v gf_user="$GF_DATABASE_USER" -v gf_pass="$GF_DATABASE_PASSWORD" \
    -v gf_db="$GF_DATABASE_NAME" \
    -f /cofrial-db/roles.sql

psql -X -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -v app_user="$DB_USERNAME" <<'SQL'
CREATE EXTENSION IF NOT EXISTS postgis;

CREATE DATABASE cofrial_test OWNER :"app_user";
\connect cofrial_test
CREATE EXTENSION IF NOT EXISTS postgis;
SQL
