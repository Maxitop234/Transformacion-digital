# db · SQL fuera de las migraciones

Las tablas de negocio se crean con las migraciones de Laravel en `apps/web`. Aquí vive el SQL que las migraciones no deben manejar porque requiere el usuario maestro o pertenece a otro componente.

| Archivo | Contenido | Tarea |
| --- | --- | --- |
| [`roles.sql`](roles.sql) | Usuarios `app_rw`, `iot_writer`, `bi_reader` y `grafana_rw`, la base `grafana` y todos los permisos | [T-005](../tasks/T-005-roles-base-de-datos/README.md) |
| [`vistas_iot.sql`](vistas_iot.sql) | Vista `iot_umbral_camion` para el simulador | T-005 |
| [`aplicar.sh`](aplicar.sh) | Aplica `roles.sql` y `vistas_iot.sql` con los valores del `.env` | T-005 |
| [`pruebas_permisos.sh`](pruebas_permisos.sh) | Comprobación reproducible de los permisos de cada usuario | T-005 |
| `vistas_bi.sql` (por crear) | Vistas `bi_*` que consulta Grafana | [T-020](../tasks/T-020-dashboard-bi/README.md) |

## Usuarios y permisos

| Usuario | Puede | No puede |
| --- | --- | --- |
| Maestro (`postgres` en local; el maestro de RDS) | Dueño de la base y de todas las tablas: corre las migraciones y estos scripts | Usarse desde la aplicación en ejecución |
| `app_rw` | `SELECT`, `INSERT`, `UPDATE`, `DELETE` en las tablas de negocio y de Laravel; leer y purgar `telemetria` | Crear, alterar, vaciar o borrar tablas; crear roles; modificar o borrar `historial_estado_pedido`, `historial_etapa` y `movimiento_inventario`; insertar o modificar `telemetria`; conectarse a `grafana` |
| `iot_writer` | `INSERT` en `telemetria` y `alerta` (con `RETURNING` de la clave); leer `iot_umbral_camion` | Leer cualquier otra tabla (clientes, pedidos, precios, la propia telemetría); actualizar o borrar |
| `bi_reader` | `SELECT` en las vistas `bi_*` (T-020) | Leer tablas directamente; insertar, actualizar o borrar |
| `grafana_rw` | Dueño de la base `grafana` | Conectarse a la base de negocio |

`roles.sql` parte de cero en cada ejecución: revoca todo a los usuarios de los componentes y concede exactamente lo de esta tabla, así un permiso dado a mano desaparece al volver a correrlo. Los privilegios por defecto (`ALTER DEFAULT PRIVILEGES`) dan a `app_rw` acceso a las tablas que creen las migraciones futuras; las restricciones por tabla (historiales, telemetría) y las vistas se aplican al volver a correr `aplicar.sh` después de migrar.

## Orden de aplicación

1. `db/aplicar.sh`: crea los usuarios (si la base aún no tiene tablas, solo eso).
2. `php artisan migrate --database=pgsql_admin --force`: migra con el usuario maestro.
3. `db/aplicar.sh` otra vez: permisos sobre las tablas y vista `iot_umbral_camion`.
4. `db/pruebas_permisos.sh`: comprueba el resultado (todas las comprobaciones deben pasar).

Repetir los pasos 2 y 3 en cada despliegue con migraciones nuevas, y el 3 después de crear o recrear vistas `bi_*`.

### En local (Docker)

El contenedor `db` corre `roles.sql` al crear el volumen y `web` hace los pasos 2 y 3 al arrancar. Para repetirlos o probar:

```bash
docker compose -f infra/docker/compose.dev.yml exec web sh /var/www/db/aplicar.sh
```

```bash
docker compose -f infra/docker/compose.dev.yml exec web sh /var/www/db/pruebas_permisos.sh
```

Una base creada antes de T-005 pertenece a `app_rw` y `roles.sql` se niega a seguir: recrea el volumen con `docker compose -f infra/docker/compose.dev.yml down -v`.

### En RDS (T-023)

Desde una EC2 de la VPC con `psql` y el `.env` de producción (`DB_HOST` = endpoint de RDS, `DB_SSLMODE=require`, `DB_ADMIN_*` = usuario maestro):

```bash
set -a; . ./.env; set +a; sh db/aplicar.sh
```

Antes de la primera migración el usuario maestro debe crear PostGIS (`CREATE EXTENSION IF NOT EXISTS postgis;`). En RDS el maestro no es superusuario; `roles.sql` lo hace miembro de `grafana_rw` para poder crear la base `grafana` a su nombre.

El flujo [`.github/workflows/db.yml`](../.github/workflows/db.yml) repite estos pasos sobre una base limpia en cada cambio a `db/` o a las migraciones.

## Reglas

- Todo script es idempotente: se puede ejecutar más de una vez sin fallar.
- Ninguna contraseña se escribe en los archivos; se pasan como variables al ejecutar (`aplicar.sh` las toma del `.env`).
- Las vistas de BI no exponen datos personales que el panel no necesite.

## Referencias

[`docs/DATA_MODEL.md`](../docs/DATA_MODEL.md) · [`docs/SECURITY.md`](../docs/SECURITY.md#5-acceso-a-la-base-de-datos-mínimo-privilegio)
