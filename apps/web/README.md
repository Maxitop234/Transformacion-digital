# apps/web · Portal de ventas y CRM

Aplicación Laravel que contiene el portal B2B/B2C, el CRM, el worker de colas y la integración con la IA externa. Creada en [T-001](../../tasks/T-001-bootstrap-laravel/README.md).

## Qué vivirá aquí

| Módulo | Superficie | Tareas |
| --- | --- | --- |
| Clientes y accesos | Inicio de sesión, registro, roles | T-006, T-009 |
| Catálogo e inventario | `/catalogo`, mantenedores | T-007, T-008 |
| Ventas | Carrito, checkout, pedidos, pagos simulados | T-010 a T-012 |
| CRM | `/crm`: panel, clientes, pipeline, pedidos, tickets | T-013 a T-016, T-018 |
| Logística | Despachos, flota, alertas de frío | T-017 |
| IA | Clasificación de tickets y asistente | T-021 |

## Reglas propias de este componente

- El esquema de negocio se cambia solo con migraciones ([ADR-0016](../../docs/ADR/0016-esquema-por-migraciones.md)).
- Sin estado en disco: sesiones, caché y colas en la base ([ADR-0005](../../docs/ADR/0005-alb-dos-ec2-web.md)).
- La lógica de negocio va en `app/Domain`, no en controladores.
- Cada regla `RN-xx` implementada lleva un test que la nombra.

## Versiones

| Pieza | Versión fijada | Dónde |
| --- | --- | --- |
| PHP | 8.4 o superior (`platform.php` = 8.4.1 para resolver dependencias) | `composer.json` |
| Laravel | 13.42.0 (`laravel/framework ^13.17`) | `composer.lock` |
| Livewire | 4.4.8 | `composer.lock` |
| Pint | 1.32.2 (preset `laravel`) | `pint.json` |
| PHPUnit | 12.5.43 | `phpunit.xml` |
| PostgreSQL | 16 con PostGIS (local: `postgis/postgis:16-3.4`) | ADR-0007 |

Extensiones de PHP necesarias: `pdo_pgsql`, `mbstring` e `intl`.

## Estructura

```text
app/Domain/{Clientes,Catalogo,Ventas,Crm,Logistica,Ia}   lógica de negocio por módulo
app/Http/Controllers/{Portal,Crm}                       controladores delgados por superficie
app/Http/Controllers/HealthController.php               GET /health (routes/health.php, sin sesión)
resources/views/layouts/{portal,crm}.blade.php          layouts base de cada superficie
lang/es                                                 textos del framework en español
app/Models                                              un modelo Eloquent por tabla del MER (34), sobre la base App\Models\Modelo
app/Models/Concerns                                     TieneClaveCompuesta (PK de dos columnas) y SoloInsercion (historiales)
database/migrations                                     una migración por tabla, en orden de dependencias (ADR-0016)
tests/Feature/Esquema                                   tablas, restricciones, modelos y coincidencia con DATA_MODEL.md
```

### Modelos

- Cada modelo declara `$table`, `$primaryKey` y `$fillable`. Las PK numéricas son `GENERATED ALWAYS AS IDENTITY`: nunca se asigna un ID a mano.
- Las tablas sin `created_at`/`updated_at` dejan las fechas de alta a la base (`DEFAULT now()`). `carrito` e `inventario` mantienen `actualizado_en` con los timestamps de Eloquent renombrados.
- Clave compuesta (`detalle_pedido`, `item_carrito`, `detalle_lista_precio`, `despacho_pedido`): se buscan con `where()` sobre ambas columnas, no con `find()`.
- `historial_estado_pedido`, `historial_etapa` y `movimiento_inventario` lanzan una excepción si se intenta actualizar o borrar una fila.
- `Usuario` es el modelo de autenticación (`config/auth.php`), con la contraseña en `password_hash`.

## Comandos

Todos se ejecutan dentro de `apps/web`.

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

Ajusta `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` en `.env` según tu PostgreSQL local. Para levantar todo con Docker (base, web, worker, Nginx y Grafana) sigue [`infra/docker/README.md`](../../infra/docker/README.md). Sin Docker:

```bash
php artisan migrate
```

```bash
php artisan serve --port=8080
```

```bash
php artisan test
```

```bash
vendor/bin/pint
```

Para revisar el formato sin modificar archivos (lo que hace el CI): `vendor/bin/pint --test`.

Los tests corren contra PostgreSQL + PostGIS en la base `cofrial_test` (`phpunit.xml` la fuerza, junto con el resto del entorno de test), así que necesitan el contenedor `db` levantado (`docker compose -f infra/docker/compose.dev.yml up -d db`) y `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` apuntando a él. `EsquemaCoincideConDataModelTest` lee `docs/DATA_MODEL.md` y se salta si no está disponible (dentro del contenedor solo se monta `apps/web`). El flujo [`.github/workflows/web.yml`](../../.github/workflows/web.yml) corre formateo y tests en cada push que toque `apps/web`.

## Configuración relevante

- `DB_SSLMODE`: `prefer` en local, `require` (o `verify-full`) contra RDS.
- `DB_CONNECT_TIMEOUT`: segundos antes de abandonar la conexión; mantiene `/health` por debajo del timeout del balanceador.
- La zona horaria de la aplicación y de la sesión de PostgreSQL es `APP_TIMEZONE` (`America/Santiago`). Las columnas `TIMESTAMPTZ` guardan el instante absoluto (UTC), así que no hay ambigüedad al leerlas desde otros componentes.
- Sesiones, caché, colas y modo mantenimiento usan la base (`database`); ninguno usa `file`.

## Referencias

[`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) · [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) · [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) · [`docs/API.md`](../../docs/API.md)
