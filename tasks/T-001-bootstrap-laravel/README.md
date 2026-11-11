# T-001 · Aplicación Laravel base

| Campo | Valor |
| --- | --- |
| Fase | 0 · Fundaciones |
| Dimensión de la pauta | 1, 2 |
| Depende de | Ninguna |
| Desbloquea | [T-002](../T-002-entorno-local-docker/README.md), [T-003](../T-003-esquema-base-de-datos/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Crear la aplicación Laravel que alojará el portal y el CRM, configurada para PostgreSQL y lista para correr en dos instancias sin estado local.

## Referencias

Leer antes de empezar:

- [`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) §4.1
- [`docs/ADR/0005`](../../docs/ADR/README.md)
- [`docs/ADR/0013`](../../docs/ADR/README.md)
- [`docs/ADR/0016`](../../docs/ADR/README.md)
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Proyecto Laravel (versión estable vigente, PHP 8.3 o superior) creado en `apps/web`; fijar las versiones exactas en `apps/web/README.md`.
- Conexión `pgsql` leída de variables de entorno, con `sslmode` configurable.
- Sesiones, caché y colas con driver `database` y sus migraciones.
- Ruta `GET /health` que responde `200 {"status":"ok"}` si hay conexión a la base y `503` si no.
- Localización en español y zona horaria `America/Santiago`.
- Estructura de carpetas `app/Domain/{Clientes,Catalogo,Ventas,Crm,Logistica,Ia}` y layouts base separados para portal y CRM.
- Formateador (Pint) y framework de tests configurados; un test de `/health`.
- Flujo de CI en `.github/workflows` que ejecuta formateo y tests de `apps/web` en cada push.
- `apps/web/README.md` con los comandos de instalación, tests y formateo.

## Fuera de alcance

- Tablas de negocio (T-003).
- Docker (T-002).
- Autenticación (T-006).

## Criterios de aceptación

- [x] `php artisan test` pasa.
- [x] `GET /health` responde 200 con la base arriba y 503 con la base detenida.
- [x] No queda ningún driver `file` para sesiones, caché o colas.
- [ ] El flujo de CI termina en verde.
- [x] `.env.example` de la raíz cubre todas las variables que la aplicación lee.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Decidir aquí la capa de interfaz propuesta en ADR-0013 (Blade con Livewire) y marcar ese ADR como aceptado o reemplazarlo.
  - **Hecho (2026-11-10):** Blade + Livewire 4 confirmado en ADR-0013. Versiones exactas en `apps/web/README.md`.
- **PHP 8.4 como mínimo.** Laravel 13 resuelve Symfony 8, que exige PHP ≥ 8.4.1; `composer.json` fija `platform.php` en 8.4.1 para que el lock sirva en CI y en la imagen de T-002. Usar PHP 8.4 en Docker y EC2.
- **`/health`** vive en `routes/health.php`, fuera del grupo `web` (no abre sesión) y excluido del modo mantenimiento, porque ambos usan la base y con ella caída darían 500. Responde `503 {"status":"unavailable"}`; `DB_CONNECT_TIMEOUT` (3 s) evita que un host inalcanzable supere el timeout del ALB. Se quitó la ruta `/up` del esqueleto.
- **Verificación manual:** contra `postgis/postgis:16-3.4` en local, `/health` dio 200 con la base arriba y 503 al detenerla; con host inalcanzable, 503 en 3 s. Las sesiones de `/` y `/crm` quedaron en la tabla `sessions`.
- **Drivers:** sesiones, caché, colas y modo mantenimiento usan `database` por defecto en `config/` y en ambos `.env.example`. El store `file` de caché sigue definido porque el framework lo fusiona siempre, pero nada lo usa. `phpunit.xml` usa `array`/`sync`, solo en tests.
- **Zona horaria:** `APP_TIMEZONE` también fija la zona de la sesión de PostgreSQL, así que los `TIMESTAMPTZ` guardan el instante correcto. En T-003, los modelos con `TIMESTAMPTZ` conviene que declaren `$dateFormat = 'Y-m-d H:i:sP'`.
- **Tests:** SQLite en memoria; T-003 decidirá si pasa a PostgreSQL cuando haya PostGIS.
- **Pendiente para T-006:** `/crm` es hoy una página de muestra sin autenticación ni datos; T-006 debe protegerla.
- **Fuera de alcance, anotado:** `FILESYSTEM_DISK=local` y los logs en `storage/logs` siguen en disco; los archivos subidos y los logs a CloudWatch se resuelven en las tareas que los necesiten (T-024/T-026).
- **CI:** `.github/workflows/web.yml` (Pint `--test` + `php artisan test`, PHP 8.4). Simulado en un clon limpio con éxito; queda verificar la primera ejecución en GitHub.
