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

- [x] `docker compose -f infra/docker/compose.dev.yml up -d` deja todos los servicios saludables desde un clon limpio.
- [x] `http://localhost:8080/health` responde 200.
- [x] `http://localhost:8080/bi` muestra el inicio de sesión de Grafana.
- [x] `SELECT postgis_version();` funciona en la base local.
- [x] Los datos de la base sobreviven a un `down` y `up`.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-10), desde un clon limpio:** `up -d --build` dejó `db`, `web`, `nginx` y `grafana` saludables y `worker` arriba; `/health` 200 (y 503 a través de Nginx con `db` detenida), `/bi` llevó al login de Grafana, `postgis_version()` = 3.4, y una tabla de prueba sobrevivió a `down` + `up`.
- **Imágenes fijadas:** `php:8.4.26-fpm-bookworm`, `postgis/postgis:16-3.4` (la línea de PostGIS que ofrece RDS para PostgreSQL 16), `nginx:1.30.5-alpine`, `grafana/grafana:13.2.5` (la imagen `grafana-oss` ya no se publica).
- **Sin BuildKit:** el Dockerfile no usa `COPY --chmod` para construir también con el constructor clásico de Docker.
- **Usuario `app_rw` local:** `db/01-inicializar-dev.sh` lo crea como dueño de `cofrial` y habilita PostGIS como superusuario (decisión del equipo, opción a). T-005 define los permisos reales en `db/roles.sql`.
- **Grafana guarda su estado en PostgreSQL** (base `grafana`, usuario `grafana_rw`), como pide ADR-0010 para que sea igual en las dos EC2. Es un cuarto usuario de base: se agregó a `SECURITY.md` y `DATA_MODEL.md`, y **T-005 debe crearlo en `db/roles.sql`**.
- **Migraciones automáticas en local:** `web` corre `php artisan migrate --force` al arrancar (`COFRIAL_MIGRAR=1`) para que el worker no se caiga por falta de las tablas `cache`/`jobs`.
- **`APP_KEY`:** si `.env` la deja vacía, el entrypoint genera una en `apps/web/storage/docker-app.key`; el README recomienda ponerla en `.env` con `openssl` para que también la vean los `docker compose exec`.
- **Hallazgo:** Docker Compose toma `VAR=   # comentario` como valor cuando `VAR` está vacía. Se movieron esos comentarios a la línea anterior en ambos `.env.example` y un test lo vigila.
- **Tests aislados:** `phpunit.xml` fuerza su entorno (`<env>` y `<server>` con `force="true"`) y trae su propia `APP_KEY`; así `php artisan test` dentro del contenedor usa SQLite y nunca la base de desarrollo.
- **Pendiente para T-024:** en local todos los servicios reciben el `.env` completo (incluidas claves de IA y del simulador). En producción cada servicio debe recibir solo sus variables.
- **Pendiente para T-019:** agregar el servicio del simulador a `compose.dev.yml`.
