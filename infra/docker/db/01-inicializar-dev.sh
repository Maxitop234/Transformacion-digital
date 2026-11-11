#!/bin/sh
# Inicialización de la base LOCAL (solo corre la primera vez, con el volumen vacío).
# - `app_rw` queda como dueño de la base de negocio para que Laravel pueda migrar.
#   Es un atajo de desarrollo: T-005 (db/roles.sql) define los permisos reales.
# - PostGIS se habilita aquí porque crear la extensión exige superusuario.
# - Grafana guarda su estado interno en una base aparte (ADR-0010).
set -eu

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -v app_user="$DB_USERNAME" -v app_pass="$DB_PASSWORD" \
    -v gf_user="$GF_DATABASE_USER" -v gf_pass="$GF_DATABASE_PASSWORD" \
    -v db_name="$POSTGRES_DB" -v gf_db="$GF_DATABASE_NAME" <<'SQL'
CREATE ROLE :"app_user" LOGIN PASSWORD :'app_pass';
ALTER DATABASE :"db_name" OWNER TO :"app_user";
CREATE EXTENSION IF NOT EXISTS postgis;

CREATE ROLE :"gf_user" LOGIN PASSWORD :'gf_pass';
CREATE DATABASE :"gf_db" OWNER :"gf_user";
SQL
