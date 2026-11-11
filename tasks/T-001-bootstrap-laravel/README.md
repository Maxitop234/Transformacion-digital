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

- [ ] `php artisan test` pasa.
- [ ] `GET /health` responde 200 con la base arriba y 503 con la base detenida.
- [ ] No queda ningún driver `file` para sesiones, caché o colas.
- [ ] El flujo de CI termina en verde.
- [ ] `.env.example` de la raíz cubre todas las variables que la aplicación lee.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Decidir aquí la capa de interfaz propuesta en ADR-0013 (Blade con Livewire) y marcar ese ADR como aceptado o reemplazarlo.
