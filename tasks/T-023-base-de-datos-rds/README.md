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

- [x] La instancia figura como Multi-AZ y no accesible públicamente.
- [x] Una conexión sin TLS es rechazada.
- [x] La base no es alcanzable desde fuera de la VPC.
- [x] Existen las 34 tablas y los tres roles con sus permisos.
- [x] Hay al menos un snapshot manual.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Si el laboratorio no permite Multi-AZ, desplegar Single-AZ, registrar el ADR y explicarlo en la defensa.
- **Hecho (2026-11-15).** Stack `cofrial-datos` ([`infra/aws/datos.yaml`](../../infra/aws/datos.yaml)): PostgreSQL 16.15, `db.t3.micro`, **Multi-AZ permitido por el laboratorio** (primaria en us-east-1b, réplica en us-east-1a), `gp2` cifrado, sin acceso público, respaldos de 7 días, grupo de parámetros `cofrial-postgres16` con `rds.force_ssl=1`. Usuario maestro `cofrial_admin`; su contraseña sale de Parameter Store (`secretos.sh`, ADR-0019).
- El esquema lo aplica `cofrial-web-1` al arrancar (migraciones con el maestro + `db/aplicar.sh`), porque la base solo es alcanzable desde la VPC. Catálogos con `CatalogoSeeder`; datos de demo con `infra/aws/cargar-demo.sh` (T-027).
- Verificación desde `cofrial-web-1`: `db/pruebas_permisos.sh` con TLS pasó las 167 comprobaciones; 34 tablas de negocio, PostGIS 3.4.6, roles `app_rw`, `iot_writer`, `bi_reader` y `grafana_rw`. Una conexión con `sslmode=disable` se rechaza (`no pg_hba.conf entry … no encryption`); con TLS usa TLSv1.3. Desde fuera de la VPC el endpoint resuelve a `10.20.22.50` y no es alcanzable.
- Snapshot manual `cofrial-db-manual-2026-11-15`. Procedimientos de migración, snapshot y restauración en [`infra/aws/README.md`](../../infra/aws/README.md).
- RDS no se detiene sola al terminar la sesión del laboratorio: detenerla a mano (`aws rds stop-db-instance --db-instance-identifier cofrial-db`).
