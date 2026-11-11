-- =============================================================================
-- Cofrial · Usuarios de base de datos con mínimo privilegio (T-005)
--
-- Se ejecuta con el usuario maestro (local: `postgres`; RDS: el usuario
-- maestro de la instancia) conectado a la base de negocio. Es idempotente: se
-- puede correr las veces que haga falta, antes y después de las migraciones.
-- Las contraseñas llegan como variables de psql; nunca se escriben aquí.
--
--   psql -v app_pass=... -v iot_pass=... -v bi_pass=... -v gf_pass=... -f db/roles.sql
--
-- Lo normal es no llamarlo a mano sino con db/aplicar.sh, que toma los valores
-- del .env. Referencias: docs/SECURITY.md §5, docs/DATA_MODEL.md (Roles).
-- =============================================================================

\set ON_ERROR_STOP on
\set QUIET on

-- Nombres de los roles: por defecto los del .env.example.
\if :{?app_user} \else \set app_user app_rw \endif
\if :{?iot_user} \else \set iot_user iot_writer \endif
\if :{?bi_user} \else \set bi_user bi_reader \endif
\if :{?gf_user} \else \set gf_user grafana_rw \endif
\if :{?gf_db} \else \set gf_db grafana \endif

\if :{?app_pass} \else DO $$ BEGIN RAISE EXCEPTION 'roles.sql: falta -v app_pass=...'; END $$; \endif
\if :{?iot_pass} \else DO $$ BEGIN RAISE EXCEPTION 'roles.sql: falta -v iot_pass=...'; END $$; \endif
\if :{?bi_pass} \else DO $$ BEGIN RAISE EXCEPTION 'roles.sql: falta -v bi_pass=...'; END $$; \endif
\if :{?gf_pass} \else DO $$ BEGIN RAISE EXCEPTION 'roles.sql: falta -v gf_pass=...'; END $$; \endif

-- La base de negocio debe pertenecer al usuario maestro: si un rol de la
-- aplicación fuera dueño, podría crear y borrar tablas.
SELECT pg_get_userbyid(datdba) IN (:'app_user', :'iot_user', :'bi_user') AS dueno_invalido
FROM pg_database WHERE datname = current_database() \gset
\if :dueno_invalido
    DO $$ BEGIN RAISE EXCEPTION 'La base % pertenece a un rol de aplicación. En local: docker compose -f infra/docker/compose.dev.yml down -v (base anterior a T-005).', current_database(); END $$;
\endif

-- -----------------------------------------------------------------------------
-- 1. Roles: se crean si faltan y siempre se les fija la contraseña del .env.
-- -----------------------------------------------------------------------------
SELECT format('CREATE ROLE %I LOGIN', rol)
FROM unnest(ARRAY[:'app_user', :'iot_user', :'bi_user', :'gf_user']) AS rol
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = rol) \gexec

ALTER ROLE :"app_user" WITH LOGIN PASSWORD :'app_pass';
ALTER ROLE :"iot_user" WITH LOGIN PASSWORD :'iot_pass';
ALTER ROLE :"bi_user" WITH LOGIN PASSWORD :'bi_pass';
ALTER ROLE :"gf_user" WITH LOGIN PASSWORD :'gf_pass';

-- bi_reader (Grafana) trabaja en transacciones de solo lectura y con un tope
-- de tiempo por consulta: aunque alguien escriba un INSERT en un panel, la base
-- lo rechaza dos veces (sin permiso y transacción de solo lectura), y una
-- consulta pesada no queda corriendo (T-020).
ALTER ROLE :"bi_user" SET default_transaction_read_only = on;
ALTER ROLE :"bi_user" SET statement_timeout = '15s';

-- -----------------------------------------------------------------------------
-- 2. Base interna de Grafana (ADR-0010): de `grafana_rw` y de nadie más.
-- -----------------------------------------------------------------------------
-- En RDS el usuario maestro no es superusuario: para crear una base a nombre
-- de otro rol necesita ser miembro de ese rol.
SELECT format('GRANT %I TO %I', :'gf_user', current_user)
WHERE NOT (SELECT rolsuper FROM pg_roles WHERE rolname = current_user)
  AND NOT pg_has_role(current_user, :'gf_user', 'MEMBER') \gexec

SELECT format('CREATE DATABASE %I OWNER %I', :'gf_db', :'gf_user')
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'gf_db') \gexec

SELECT format('REVOKE ALL ON DATABASE %I FROM PUBLIC', :'gf_db') \gexec

-- -----------------------------------------------------------------------------
-- 3. Base de negocio: solo se conectan los tres usuarios de los componentes.
-- -----------------------------------------------------------------------------
SELECT format('REVOKE ALL ON DATABASE %I FROM PUBLIC', current_database()) \gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO %I, %I, %I', current_database(), :'app_user', :'iot_user', :'bi_user') \gexec

-- Nadie más que el dueño crea objetos en el esquema.
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO :"app_user", :"iot_user", :"bi_user";

-- Se parte de cero en cada ejecución: lo que alguien haya concedido a mano se
-- quita y quedan exactamente los permisos de este archivo. (Revocar a nivel de
-- tabla también quita los permisos por columna.)
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM :"app_user", :"iot_user", :"bi_user";
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM :"app_user", :"iot_user", :"bi_user";

-- -----------------------------------------------------------------------------
-- 4. app_rw: lee y escribe datos; no crea, altera ni borra tablas.
-- -----------------------------------------------------------------------------
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO :"app_user";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO :"app_user";

-- Las tablas que creen las migraciones futuras (con este mismo usuario) quedan cubiertas.
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO :"app_user";

-- Historiales de solo inserción (AGENTS.md): la aplicación no los modifica ni borra.
SELECT format('REVOKE UPDATE, DELETE, TRUNCATE ON %I FROM %I', tabla, :'app_user')
FROM unnest(ARRAY['historial_estado_pedido', 'historial_etapa', 'movimiento_inventario']) AS tabla
WHERE to_regclass(tabla) IS NOT NULL \gexec

-- La telemetría la escribe el simulador; la aplicación la lee y, para la
-- retención (DATA_MODEL.md, punto abierto 5), puede purgarla.
SELECT format('REVOKE INSERT, UPDATE ON telemetria FROM %I', :'app_user')
WHERE to_regclass('telemetria') IS NOT NULL \gexec

-- -----------------------------------------------------------------------------
-- 5. iot_writer: solo inserta lecturas y alertas (docs/API.md §2).
-- -----------------------------------------------------------------------------
-- SELECT sobre la clave para `INSERT ... RETURNING id_telemetria`; nada más.
SELECT format('GRANT INSERT ON telemetria, alerta TO %I', :'iot_user')
WHERE to_regclass('telemetria') IS NOT NULL AND to_regclass('alerta') IS NOT NULL \gexec
SELECT format('GRANT SELECT (id_telemetria) ON telemetria TO %I', :'iot_user')
WHERE to_regclass('telemetria') IS NOT NULL \gexec
SELECT format('GRANT SELECT (id_alerta) ON alerta TO %I', :'iot_user')
WHERE to_regclass('alerta') IS NOT NULL \gexec
SELECT format('GRANT USAGE ON SEQUENCE %s TO %I', pg_get_serial_sequence(tabla, columna), :'iot_user')
FROM (VALUES ('telemetria', 'id_telemetria'), ('alerta', 'id_alerta')) AS s (tabla, columna)
WHERE to_regclass(tabla) IS NOT NULL \gexec
-- El umbral de frío lo lee por la vista iot_umbral_camion (db/vistas_iot.sql).
SELECT format('GRANT SELECT ON iot_umbral_camion TO %I', :'iot_user')
WHERE to_regclass('iot_umbral_camion') IS NOT NULL \gexec

-- -----------------------------------------------------------------------------
-- 6. bi_reader: solo las vistas bi_* (db/vistas_bi.sql, T-020). Ese
--    script vuelve a dar el permiso al recrearlas; aquí se repite para que
--    correr solo roles.sql no se lo quite.
-- -----------------------------------------------------------------------------
SELECT format('GRANT SELECT ON %I TO %I', viewname, :'bi_user')
FROM pg_views
WHERE schemaname = 'public' AND viewname LIKE 'bi\_%' \gexec

\echo 'roles.sql: roles y permisos aplicados en' :DBNAME
