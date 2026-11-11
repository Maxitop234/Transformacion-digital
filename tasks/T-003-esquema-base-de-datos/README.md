# T-003 · Esquema de base de datos

| Campo | Valor |
| --- | --- |
| Fase | 0 · Fundaciones |
| Dimensión de la pauta | 2 |
| Depende de | [T-001](../T-001-bootstrap-laravel/README.md) |
| Desbloquea | [T-004](../T-004-datos-semilla/README.md), [T-005](../T-005-roles-base-de-datos/README.md), [T-006](../T-006-autenticacion-y-roles/README.md), [T-019](../T-019-simulador-iot/README.md), [T-023](../T-023-base-de-datos-rds/README.md) |
| Componentes | `apps/web`, `db` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Crear las 34 tablas del modelo con sus claves, restricciones e índices mediante migraciones, y un modelo Eloquent por tabla.

## Referencias

Leer antes de empezar:

- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) (completo)
- [`docs/ADR/0016`](../../docs/ADR/README.md)
- [`docs/ADR/0007`](../../docs/ADR/README.md)
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Migraciones para las 34 tablas en orden de dependencias, con PK, FK, `UNIQUE`, `CHECK` y nulidad exactamente como en `DATA_MODEL.md`.
- Índices: `telemetria (camion_id, fecha_hora DESC)`, FK de uso frecuente y unicidad parcial de un carrito `activo` por cliente.
- Extensión PostGIS habilitada por migración.
- Un modelo Eloquent por tabla con `$table`, `$primaryKey`, claves compuestas y relaciones declaradas.
- Resolver los puntos abiertos 1 y 6 de `DATA_MODEL.md` (particionamiento y listas de valores) y dejar el documento actualizado.

## Fuera de alcance

- Datos (T-004).
- Roles de base de datos (T-005).
- Vistas de BI (T-020).

## Criterios de aceptación

- [x] `php artisan migrate:fresh` corre sin errores en una base vacía.
- [x] Un test verifica que existen las 34 tablas de negocio.
- [x] Tests muestran que la base rechaza: stock negativo, `combustible_pct` fuera de 0–100, cantidad cero y un `canal` fuera de lista.
- [x] `php artisan migrate:rollback` deshace todo sin errores.
- [x] `DATA_MODEL.md` no tiene diferencias con las migraciones.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Si el tiempo apremia, migrar primero las tablas de la ruta crítica (ver `ROADMAP.md`).
- **Fuente del modelo:** el diccionario de datos del informe (Anexo A) se comparó columna por columna con `DATA_MODEL.md`: las 34 tablas, columnas, tipos y nulidad coincidían; difería la mayoría de las listas de valores. Decisiones del equipo (2026-11-10): se adoptan las listas, reglas `CHECK` y valores por defecto del Anexo, salvo los **roles** (se mantienen `administrador`, `vendedor`, `despachador`, `cliente_b2b`, `cliente_b2c`) y `ticket.categoria`, que agrega `sin_clasificar` como valor por defecto. ADR-0016 pasó a «Aceptada» y el particionamiento se resolvió en ADR-0017 (sin particionar).
- **Reglas del Anexo que cambian respecto del documento anterior:** `detalle_lista_precio.precio_unitario >= 0` (antes `> 0`) y `movimiento_inventario.cantidad <> 0` con signo según el tipo (antes `> 0`). Se agregaron los `CHECK` del Anexo que faltaban: `temp_camara` entre -60 y 60, `orden_parada > 0`, `precio_unitario_pactado >= 0` y `tipo_cliente.codigo`. Se mantiene `stock_reservado <= stock_disponible` del documento.
- **Decisión propia:** todas las FK son `ON DELETE RESTRICT` (nada se borra en cascada; los historiales y pedidos se conservan). Documentado en `DATA_MODEL.md`.
- **Verificación:** `migrate:fresh` y `migrate:rollback` completos contra PostgreSQL 16 + PostGIS 3.4 con `app_rw` (42 tablas: 34 de negocio, 7 de Laravel y `spatial_ref_sys`). 31 tests en verde; `EsquemaCoincideConDataModelTest` compara tipos, nulidad, orden de columnas y las 18 listas `CHECK` con este documento, y se comprobó que falla si se altera una lista, un tipo o una nulidad.
- **Tests sobre PostgreSQL:** `phpunit.xml` usa la base `cofrial_test` (la crea `infra/docker/db/01-inicializar-dev.sh`; con un volumen anterior hay que hacer `down -v`). El CI levanta un servicio `postgis/postgis:16-3.4`.
- **PostGIS:** la migración `enable_postgis_extension` hace `CREATE EXTENSION IF NOT EXISTS` y no la elimina al revertir. En RDS la extensión debe crearla el usuario maestro antes de migrar (T-023).
- **Se quitaron** `users`, `password_reset_tokens`, `App\Models\User` y `UserFactory` del esqueleto; `config/auth.php` apunta a `App\Models\Usuario` (`password_hash`, sin «recordarme»).
- **Para T-004:** las PK son `GENERATED ALWAYS`, así que los seeders no fijan IDs (usar los devueltos o buscar por nombre/código). Falta sembrar el usuario técnico `sistema`.
- **Para T-005:** los historiales son de solo inserción en los modelos (`SoloInsercion`); conviene reforzarlo negando `UPDATE`/`DELETE` a `app_rw` sobre esas tablas.
- **Para T-006:** el broker de contraseñas de `config/auth.php` aún apunta a `password_reset_tokens`, que ya no existe; definir el restablecimiento de contraseña (o quitarlo) y el «recordarme» si se necesita.
