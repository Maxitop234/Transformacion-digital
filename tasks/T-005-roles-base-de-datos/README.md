# T-005 · Roles de base de datos de mínimo privilegio

| Campo | Valor |
| --- | --- |
| Fase | 0 · Fundaciones |
| Dimensión de la pauta | 1 |
| Depende de | [T-003](../T-003-esquema-base-de-datos/README.md) |
| Desbloquea | [T-019](../T-019-simulador-iot/README.md), [T-020](../T-020-dashboard-bi/README.md), [T-023](../T-023-base-de-datos-rds/README.md) |
| Componentes | `db` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Crear los tres usuarios de base de datos con los permisos mínimos que necesita cada componente.

## Referencias

Leer antes de empezar:

- [`docs/SECURITY.md`](../../docs/SECURITY.md) §5
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) (Roles y puntos abiertos 2)
- [`docs/ADR/0008`](../../docs/ADR/README.md)
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- `db/roles.sql` idempotente que crea `app_rw`, `iot_writer` y `bi_reader` y asigna sus permisos, con las contraseñas pasadas como variables (nunca escritas en el archivo).
- Vista `iot_umbral_camion` de solo lectura para que el simulador conozca el rango de frío aplicable a cada camión.
- Permisos por defecto para que las tablas creadas por migraciones futuras queden cubiertas.
- Script o comando documentado en `db/README.md` para aplicar los roles en local y en RDS.

## Fuera de alcance

- Vistas `bi_*` (T-020); aquí solo se deja el permiso preparado.

## Criterios de aceptación

- [ ] `iot_writer` puede insertar en `telemetria` y `alerta`, y no puede leer `cliente` ni actualizar o borrar `telemetria`.
- [ ] `bi_reader` no puede insertar, actualizar ni borrar en ninguna tabla.
- [ ] `app_rw` no puede crear ni eliminar tablas.
- [ ] Ejecutar `db/roles.sql` dos veces no falla.
- [ ] Las pruebas anteriores quedan como script reproducible en `db/`.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
