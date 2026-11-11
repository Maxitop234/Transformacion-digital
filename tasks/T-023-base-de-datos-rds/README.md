# T-023 · Base de datos en Amazon RDS

| Campo | Valor |
| --- | --- |
| Fase | 5 · Despliegue en AWS |
| Dimensión de la pauta | 1 |
| Depende de | [T-003](../T-003-esquema-base-de-datos/README.md), [T-005](../T-005-roles-base-de-datos/README.md), [T-022](../T-022-red-aws/README.md) |
| Desbloquea | [T-024](../T-024-despliegue-web-y-balanceador/README.md), [T-025](../T-025-despliegue-nodo-iot/README.md) |
| Componentes | `infra/aws`, `db` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Dejar operativa la base PostgreSQL en la nube con el esquema, los roles y los respaldos.

## Referencias

Leer antes de empezar:

- [`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) §3.4
- [`docs/ADR/0007`](../../docs/ADR/README.md) a 0009
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §4 y §5
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Grupo de subredes con `datos-a` y `datos-b`.
- Instancia RDS PostgreSQL 16 `db.t3.micro`, Multi-AZ, sin acceso público, cifrada, con respaldos de 7 días.
- Grupo de parámetros con TLS obligatorio.
- PostGIS habilitado, roles aplicados con `db/roles.sql`, migraciones y seeders de catálogo ejecutados.
- Procedimiento documentado para migrar y para tomar un snapshot manual.

## Fuera de alcance

- Vistas de BI (T-020).

## Criterios de aceptación

- [ ] La instancia figura como Multi-AZ y no accesible públicamente.
- [ ] Una conexión sin TLS es rechazada.
- [ ] La base no es alcanzable desde fuera de la VPC.
- [ ] Existen las 34 tablas y los tres roles con sus permisos.
- [ ] Hay al menos un snapshot manual.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Si el laboratorio no permite Multi-AZ, desplegar Single-AZ, registrar el ADR y explicarlo en la defensa.
