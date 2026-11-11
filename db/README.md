# db · SQL fuera de las migraciones

Las tablas de negocio se crean con las migraciones de Laravel en `apps/web`. Aquí vive el SQL que las migraciones no deben manejar porque requiere el usuario maestro o pertenece a otro componente.

| Archivo (por crear) | Contenido | Tarea |
| --- | --- | --- |
| `roles.sql` | Usuarios `app_rw`, `iot_writer`, `bi_reader` y sus permisos | [T-005](../tasks/T-005-roles-base-de-datos/README.md) |
| `vistas_iot.sql` | Vista `iot_umbral_camion` para el simulador | T-005 |
| `vistas_bi.sql` | Vistas `bi_*` que consulta Grafana | [T-020](../tasks/T-020-dashboard-bi/README.md) |
| `pruebas_permisos.sql` | Comprobación reproducible de los permisos de cada rol | T-005 |

## Reglas

- Todo script es idempotente: se puede ejecutar más de una vez sin fallar.
- Ninguna contraseña se escribe en los archivos; se pasan como variables al ejecutar.
- Las vistas de BI no exponen datos personales que el panel no necesite.

## Referencias

[`docs/DATA_MODEL.md`](../docs/DATA_MODEL.md) · [`docs/SECURITY.md`](../docs/SECURITY.md#5-acceso-a-la-base-de-datos-mínimo-privilegio)
