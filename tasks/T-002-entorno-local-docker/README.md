# T-002 · Entorno local con Docker Compose

| Campo | Valor |
| --- | --- |
| Fase | 0 · Fundaciones |
| Dimensión de la pauta | 1, 3 |
| Depende de | [T-001](../T-001-bootstrap-laravel/README.md) |
| Desbloquea | [T-024](../T-024-despliegue-web-y-balanceador/README.md) |
| Componentes | `infra/docker` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Levantar todo el sistema en un equipo de desarrollo con un comando, usando el mismo motor de base de datos que RDS.

## Referencias

Leer antes de empezar:

- [`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) §5
- [`docs/ADR/0007`](../../docs/ADR/README.md)
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- `infra/docker/web/Dockerfile` (PHP-FPM con extensiones `pdo_pgsql`) y configuración de Nginx.
- `infra/docker/compose.dev.yml` con los servicios `db` (PostgreSQL 16 + PostGIS), `web`, `nginx`, `worker` y `grafana`.
- Nginx publica la aplicación en `:8080` y deriva `/bi` a Grafana.
- Volumen persistente para la base y chequeos de salud (`healthcheck`) en `db` y `web`.
- `infra/docker/README.md` con el paso a paso: copiar `.env`, levantar, migrar, sembrar, detener.

## Fuera de alcance

- Compose de producción (T-024).
- Servicio del simulador (se agrega en T-019).

## Criterios de aceptación

- [ ] `docker compose -f infra/docker/compose.dev.yml up -d` deja todos los servicios saludables desde un clon limpio.
- [ ] `http://localhost:8080/health` responde 200.
- [ ] `http://localhost:8080/bi` muestra el inicio de sesión de Grafana.
- [ ] `SELECT postgis_version();` funciona en la base local.
- [ ] Los datos de la base sobreviven a un `down` y `up`.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
