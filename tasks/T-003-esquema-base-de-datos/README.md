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
- Índices: `telemetria (camion_id, fecha_hora DESC)`, FK de uso frecuente y unicidad parcial de un carrito `abierto` por cliente.
- Extensión PostGIS habilitada por migración.
- Un modelo Eloquent por tabla con `$table`, `$primaryKey`, claves compuestas y relaciones declaradas.
- Resolver los puntos abiertos 1 y 6 de `DATA_MODEL.md` (particionamiento y listas de valores) y dejar el documento actualizado.

## Fuera de alcance

- Datos (T-004).
- Roles de base de datos (T-005).
- Vistas de BI (T-020).

## Criterios de aceptación

- [ ] `php artisan migrate:fresh` corre sin errores en una base vacía.
- [ ] Un test verifica que existen las 34 tablas de negocio.
- [ ] Tests muestran que la base rechaza: stock negativo, `combustible_pct` fuera de 0–100, cantidad cero y un `canal` fuera de lista.
- [ ] `php artisan migrate:rollback` deshace todo sin errores.
- [ ] `DATA_MODEL.md` no tiene diferencias con las migraciones.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Si el tiempo apremia, migrar primero las tablas de la ruta crítica (ver `ROADMAP.md`).
