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

- [x] `iot_writer` puede insertar en `telemetria` y `alerta`, y no puede leer `cliente` ni actualizar o borrar `telemetria`.
- [x] `bi_reader` no puede insertar, actualizar ni borrar en ninguna tabla.
- [x] `app_rw` no puede crear ni eliminar tablas.
- [x] Ejecutar `db/roles.sql` dos veces no falla.
- [x] Las pruebas anteriores quedan como script reproducible en `db/`.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-10):** entorno local recreado desde cero y simulación del flujo de CI en un PostGIS limpio: `aplicar.sh` sin tablas → migración con el maestro → `aplicar.sh` dos veces → siembra como `app_rw` → `pruebas_permisos.sh`, con 153 comprobaciones correctas (insertar, actualizar y borrar en cada una de las 42 tablas para `bi_reader`, más los casos de cada usuario). Se comprobó que el script detecta un permiso de más (`GRANT SELECT ON cliente TO iot_writer`) y que volver a correr `aplicar.sh` lo quita. Tests de la aplicación y `/health`, `/`, `/crm` y `/bi` siguen bien.
- **Dueño del esquema:** las migraciones corren con el usuario maestro (nueva conexión `pgsql_admin` en `config/database.php`, `php artisan migrate --database=pgsql_admin`), como ya pedían SECURITY.md y DATA_MODEL.md. Cambia el arranque local de T-002: `cofrial` pertenece a `postgres` y el contenedor `web` migra como maestro y luego corre `db/aplicar.sh` (la imagen incluye `postgresql-client` y monta `db/`). `cofrial_test` sigue siendo de `app_rw` porque los tests recrean el esquema.
- **`roles.sql` parte de cero:** revoca todo a los tres usuarios y concede lo justo, así es idempotente en el sentido fuerte (deja siempre el mismo estado). Verifica además que la base no pertenezca a un rol de aplicación y se detiene si es así (volúmenes locales anteriores a T-005: `down -v`).
- **Decisiones propias:** `app_rw` sin `UPDATE`/`DELETE`/`TRUNCATE` en los tres historiales (refuerza `SoloInsercion`, nota de T-003) y sin `INSERT`/`UPDATE` en `telemetria` (la escribe el simulador; se deja `DELETE` para la purga por retención, punto abierto 5). `iot_writer` tiene `SELECT` solo sobre `id_telemetria` e `id_alerta`, lo justo para `RETURNING`.
- **Vista `iot_umbral_camion`:** además de `temp_min_c`/`temp_max_c` (nulos si no hay despacho en ruta) expone `activo` y `alerta_ruptura_abierta`, para que el simulador no repita alertas (DATA_MODEL.md, punto 3) sin leer `alerta`. Resuelve el punto abierto 2.
- **`bi_reader`:** hoy solo tiene `CONNECT` y `USAGE`; `roles.sql` concede `SELECT` a toda vista `bi_*` que exista. **T-020** debe volver a correr `db/aplicar.sh` después de crear sus vistas.
- **`grafana_rw` y la base `grafana`** se crean aquí (pendiente que dejó T-002); en RDS el maestro se hace miembro de `grafana_rw` para poder crearla.
- **CI:** nuevo flujo `.github/workflows/db.yml` que repite la secuencia completa en cada cambio a `db/`, a las migraciones o a `config/database.php`.
- **Para T-019:** el simulador se conecta como `iot_writer`, lee `iot_umbral_camion` e inserta con `INSERT ... RETURNING id_telemetria` (docs/API.md §2.3).
- **Para T-023/T-024:** en RDS, crear PostGIS con el maestro antes de migrar, y en cada despliegue correr `migrate --database=pgsql_admin` seguido de `db/aplicar.sh`.

