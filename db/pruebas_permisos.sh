#!/bin/sh
# =============================================================================
# Prueba reproducible de los permisos de cada usuario de base (T-005).
#
# Se conecta como cada rol con su contraseña real y comprueba lo que puede y lo
# que no puede hacer. Todo lo que escribe va dentro de transacciones que se
# revierten, así que no deja datos. Requiere haber corrido migraciones y
# db/aplicar.sh. Termina con código distinto de 0 si algo falla.
#
#   docker compose -f infra/docker/compose.dev.yml exec web sh /var/www/db/pruebas_permisos.sh
#   set -a; . ./.env; set +a; sh db/pruebas_permisos.sh
# =============================================================================
set -u

export PGHOST="$DB_HOST" PGPORT="${DB_PORT:-5432}" PGSSLMODE="${DB_SSLMODE:-prefer}" PGCONNECT_TIMEOUT=10
BASE="$DB_DATABASE"
APP="${DB_USERNAME:-app_rw}"
IOT="${IOT_DB_USERNAME:-iot_writer}"
BI="${BI_DB_USERNAME:-bi_reader}"
GF="${GF_DATABASE_USER:-grafana_rw}"
GF_BASE="${GF_DATABASE_NAME:-grafana}"

fallas=0
total=0

clave() {
    case "$1" in
        "$APP") echo "$DB_PASSWORD" ;;
        "$IOT") echo "$IOT_DB_PASSWORD" ;;
        "$BI") echo "$BI_DB_PASSWORD" ;;
        "$GF") echo "$GF_DATABASE_PASSWORD" ;;
        *) echo "$DB_ADMIN_PASSWORD" ;;
    esac
}

# bi_reader trabaja por defecto en transacciones de solo lectura (roles.sql), y
# ese error (25006) llega antes que el de permisos. Para probar los permisos
# por sí solos se desactiva en la sesión; `por_defecto` prueba la configuración
# real del rol.
OPCIONES_PRUEBA='-c default_transaction_read_only=off'

# sql <usuario> <base> <sentencia>: ejecuta y deja la salida en $salida.
sql() {
    salida=$(PGOPTIONS="${opciones-$OPCIONES_PRUEBA}" PGPASSWORD=$(clave "$1") psql -X -q -tA -v ON_ERROR_STOP=1 -v VERBOSITY=verbose -U "$1" -d "$2" -c "$3" 2>&1)
}

# igual <usuario> <base> <descripción> <sentencia> <esperado>: la salida debe ser exactamente <esperado>.
igual() {
    total=$((total + 1))
    if sql "$1" "$2" "$4" && [ "$salida" = "$5" ]; then
        echo "  ok   $1 $3"
    else
        fallas=$((fallas + 1))
        echo "  FALLA $1 debería $3 (se obtuvo: $(echo "$salida" | head -n 2 | tr '\n' ' '))"
    fi
}

# por_defecto <función> <args...>: corre la comprobación sin opciones de sesión.
por_defecto() {
    opciones=""
    "$@"
    unset opciones
}

puede() {
    total=$((total + 1))
    if sql "$1" "$2" "$4"; then
        echo "  ok   $1 puede $3"
    else
        fallas=$((fallas + 1))
        echo "  FALLA $1 debería poder $3"
        echo "        $(echo "$salida" | head -n 2 | tr '\n' ' ')"
    fi
}

# no_puede: exige que falle por falta de privilegios (SQLSTATE 42501 o conexión
# denegada) o, sin opciones de sesión, por transacción de solo lectura (25006).
no_puede() {
    total=$((total + 1))
    if sql "$1" "$2" "$4"; then
        fallas=$((fallas + 1))
        echo "  FALLA $1 pudo $3"
    elif echo "$salida" | grep -Eq '42501|25006|permission denied|permiso denegado|read-only transaction'; then
        echo "  ok   $1 no puede $3"
    else
        fallas=$((fallas + 1))
        echo "  FALLA $1 no pudo $3, pero no por permisos:"
        echo "        $(echo "$salida" | head -n 2 | tr '\n' ' ')"
    fi
}

admin() {
    sql "$DB_ADMIN_USERNAME" "$BASE" "$1" || { echo "pruebas_permisos.sh: error como $DB_ADMIN_USERNAME: $salida" >&2; exit 2; }
}

# Un camión para probar inserciones: el primero que exista o uno temporal.
admin "select camion_id from camion order by camion_id limit 1"
CAMION="$salida"
TEMPORAL=""
if [ -z "$CAMION" ]; then
    CAMION="CAM-PRUEBA"
    TEMPORAL=1
    admin "insert into camion (camion_id, patente, capacidad_kg) values ('$CAMION', 'ZZZZ99', 1000)"
fi
limpiar() { [ -n "$TEMPORAL" ] && admin "delete from camion where camion_id = '$CAMION'"; }
trap limpiar EXIT

LECTURA="insert into telemetria (camion_id, temp_camara, lat, \"long\", combustible_pct, alerta_ruptura_frio) values ('$CAMION', 9.4, -38.7359, -72.5904, 63, true)"

echo "== $IOT"
puede "$IOT" "$BASE" "insertar telemetría y su alerta" \
    "begin; with t as ($LECTURA returning id_telemetria) insert into alerta (id_telemetria, tipo, severidad) select id_telemetria, 'ruptura_frio', 'alta' from t; rollback;"
puede "$IOT" "$BASE" "leer iot_umbral_camion" "select camion_id, temp_min_c, temp_max_c, alerta_ruptura_abierta from iot_umbral_camion limit 1"
no_puede "$IOT" "$BASE" "leer cliente" "select * from cliente limit 1"
no_puede "$IOT" "$BASE" "leer pedido" "select * from pedido limit 1"
no_puede "$IOT" "$BASE" "leer precios" "select * from detalle_lista_precio limit 1"
no_puede "$IOT" "$BASE" "leer las lecturas de telemetría" "select temp_camara from telemetria limit 1"
no_puede "$IOT" "$BASE" "actualizar telemetría" "update telemetria set temp_camara = 0 where false"
no_puede "$IOT" "$BASE" "borrar telemetría" "delete from telemetria where false"
no_puede "$IOT" "$BASE" "actualizar alertas" "update alerta set estado = 'resuelta' where false"
no_puede "$IOT" "$BASE" "crear tablas" "create table prueba_t005 (x int)"
no_puede "$IOT" "$GF_BASE" "conectarse a la base de Grafana" "select 1"

echo "== $BI"
# Cada tabla con su primera columna no identity, para probar UPDATE.
admin "select string_agg(t.table_name || ':' || (select c.column_name from information_schema.columns c where c.table_schema = 'public' and c.table_name = t.table_name and c.is_identity = 'NO' order by c.ordinal_position limit 1), ' ') from information_schema.tables t where t.table_schema = 'public' and t.table_type = 'BASE TABLE'"
for par in $salida; do
    tabla=${par%%:*}
    columna=${par#*:}
    no_puede "$BI" "$BASE" "insertar en $tabla" "insert into \"$tabla\" default values"
    no_puede "$BI" "$BASE" "actualizar $tabla" "update \"$tabla\" set \"$columna\" = \"$columna\" where false"
    no_puede "$BI" "$BASE" "borrar en $tabla" "delete from \"$tabla\" where false"
done
no_puede "$BI" "$BASE" "leer cliente directamente" "select * from cliente limit 1"
no_puede "$BI" "$BASE" "leer telemetría directamente" "select * from telemetria limit 1"
no_puede "$BI" "$BASE" "crear tablas" "create table prueba_t005 (x int)"
# Vistas bi_* (T-020): las lee todas y no escribe a través de ellas.
admin "select string_agg(viewname, ' ' order by viewname) from pg_views where schemaname = 'public' and viewname like 'bi\_%'"
VISTAS_BI="$salida"
if [ -n "$VISTAS_BI" ]; then
    for vista in $VISTAS_BI; do
        puede "$BI" "$BASE" "leer $vista" "select * from \"$vista\" limit 1"
    done
    no_puede "$BI" "$BASE" "insertar a través de bi_telemetria" "insert into bi_telemetria (camion_id, temp_camara, combustible_pct) values ('$CAMION', 4, 50)"
    no_puede "$BI" "$BASE" "borrar a través de bi_telemetria" "delete from bi_telemetria where false"
    por_defecto igual "$BI" "$BASE" "trabaja en solo lectura por defecto" "show default_transaction_read_only" "on"
    por_defecto no_puede "$BI" "$BASE" "escribir aunque tuviera permiso (solo lectura)" "create temporary table prueba_t020 (x int)"
else
    echo "  (aún no hay vistas bi_*: corre db/aplicar.sh después de migrar)"
fi

echo "== $APP"
puede "$APP" "$BASE" "leer y escribir datos de negocio" \
    "begin; insert into region (nombre) values ('Región de prueba T-005'); update region set nombre = nombre where nombre = 'Región de prueba T-005'; delete from region where nombre = 'Región de prueba T-005'; rollback;"
puede "$APP" "$BASE" "leer telemetría" "select count(*) from telemetria"
no_puede "$APP" "$BASE" "crear tablas" "create table prueba_t005 (x int)"
no_puede "$APP" "$BASE" "eliminar tablas" "drop table region"
no_puede "$APP" "$BASE" "alterar tablas" "alter table region add column prueba int"
no_puede "$APP" "$BASE" "vaciar tablas" "truncate region"
no_puede "$APP" "$BASE" "crear roles" "create role prueba_t005"
no_puede "$APP" "$BASE" "modificar historial_estado_pedido" "update historial_estado_pedido set comentario = comentario where false"
no_puede "$APP" "$BASE" "borrar historial_etapa" "delete from historial_etapa where false"
no_puede "$APP" "$BASE" "modificar movimiento_inventario" "update movimiento_inventario set cantidad = cantidad where false"
no_puede "$APP" "$BASE" "insertar telemetría" "$LECTURA"
no_puede "$APP" "$GF_BASE" "conectarse a la base de Grafana" "select 1"

echo "== $GF"
puede "$GF" "$GF_BASE" "usar su base" "begin; create table prueba_t005 (x int); rollback;"
no_puede "$GF" "$BASE" "conectarse a la base de negocio" "select 1"

echo
if [ "$fallas" -eq 0 ]; then
    echo "pruebas_permisos.sh: $total comprobaciones, todas correctas."
else
    echo "pruebas_permisos.sh: $fallas de $total comprobaciones fallaron." >&2
    exit 1
fi
