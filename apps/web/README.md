# apps/web · Portal de ventas y CRM

Aplicación Laravel que contiene el portal B2B/B2C, el CRM, el worker de colas y la integración con la IA externa. Creada en [T-001](../../tasks/T-001-bootstrap-laravel/README.md).

## Qué vivirá aquí

| Módulo | Superficie | Tareas |
| --- | --- | --- |
| Clientes y accesos | Inicio de sesión, registro, roles | T-006, T-009 |
| Catálogo e inventario | `/catalogo`, mantenedores | T-007, T-008 |
| Ventas | Carrito (`app/Domain/Ventas`), checkout, pedidos, pagos simulados | T-010 a T-012 |
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
app/Domain/Clientes/Permisos.php                        matriz de áreas por rol; cada área es un gate (`can:oportunidades`)
app/Domain/Clientes/AlcanceCartera.php                  RN-10: qué clientes ve cada usuario; scope `visiblePara()`
app/Http/Middleware/{ExigirRol,CerrarSesionSiInactivo}  `rol:...` (403) y revocación inmediata al desactivar
app/Http/Controllers/Auth/LoginController.php           inicio y cierre de sesión con límite de intentos
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

Ajusta `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` y `DB_ADMIN_*` en `.env` según tu PostgreSQL local. Para levantar todo con Docker (base, web, worker, Nginx y Grafana) sigue [`infra/docker/README.md`](../../infra/docker/README.md). Sin Docker:

```bash
php artisan migrate --database=pgsql_admin
```

Las migraciones corren con el usuario maestro (conexión `pgsql_admin`), dueño del esquema; la aplicación usa `app_rw`, que no puede crear ni borrar tablas. Después de migrar se aplican los permisos con `db/aplicar.sh` ([`db/README.md`](../../db/README.md)).

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

Estilos (Tailwind 4) y JS con Vite; en Docker los compila el servicio `assets`. Sin Docker (Node 22):

```bash
npm ci
```

```bash
npm run build
```

Las vistas cargan los assets y las fuentes (`@fonts`, Instrument Sans servida desde `public/build`, sin pedirla a terceros) solo si existe `public/build/manifest.json`; sin compilar, las páginas funcionan pero sin estilos.

Los tests corren contra PostgreSQL + PostGIS en la base `cofrial_test` (`phpunit.xml` la fuerza, junto con el resto del entorno de test), así que necesitan el contenedor `db` levantado (`docker compose -f infra/docker/compose.dev.yml up -d db`) y `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` apuntando a él. `EsquemaCoincideConDataModelTest` lee `docs/DATA_MODEL.md` y se salta si no está disponible (dentro del contenedor solo se monta `apps/web`). El flujo [`.github/workflows/web.yml`](../../.github/workflows/web.yml) corre formateo y tests en cada push que toque `apps/web`.

## Datos semilla

Para recrear la base con datos (el maestro borra y crea las tablas; luego se reaplican los permisos):

```bash
php artisan migrate:fresh --database=pgsql_admin --seed
```

```bash
sh ../../db/aplicar.sh
```

En Docker: `docker compose -f infra/docker/compose.dev.yml exec web php artisan migrate:fresh --database=pgsql_admin --seed --force` seguido de `docker compose -f infra/docker/compose.dev.yml exec web sh /var/www/db/aplicar.sh`.

| Seeder | Qué crea | Dónde corre |
| --- | --- | --- |
| `CatalogoSeeder` | Regiones y comunas de la macrozona sur, roles, tipos de cliente, 7 estados de pedido, 7 etapas del pipeline, categorías, rangos de conservación, lista «Público general» y el usuario técnico `sistema`. Idempotente | Todos los entornos. En producción: `php artisan db:seed --class=CatalogoSeeder` |
| `DemoSeeder` (`database/seeders/Demo/`) | Equipo, 32 productos con tres listas de precios e inventario, 50 camiones y conductores, clientes B2C/B2B (una empresa pendiente de validación y dos prospectos) y unas 8 semanas de pedidos, despachos, oportunidades, interacciones y tickets | Solo fuera de producción (se niega a correr con `APP_ENV=production`). Si ya hay datos de demo no hace nada |

Los datos de demo son ficticios y siempre los mismos (azar con semilla fija). Los RUT están en el rango 45.000.000–45.999.999, con dígito verificador válido; los correos usan dominios `.example` y los teléfonos el bloque `+56 9 5555`. Las fechas del historial son relativas al momento de sembrar.

### Usuarios de demo

**Solo para local y demo.** Todos usan la contraseña `cofrial-demo` (`DemoSeeder::CLAVE_DEMO`); nunca se siembran en producción.

| Rol | Correo |
| --- | --- |
| Administrador | `admin@cofrial.example` |
| Despachador | `despacho@cofrial.example` |
| Vendedor | `vendedor1@cofrial.example`, `vendedor2@cofrial.example`, `vendedor3@cofrial.example` |
| Cliente B2C | `camila.rojas@correo.example`, `benjamin.munoz@correo.example`, `isidora.contreras@correo.example`, `vicente.torres@correo.example`, `florencia.reyes@correo.example`, `joaquin.espinoza@correo.example` |
| Cliente B2B | `compras@clinica-araucaria.example`, `compras@hospital-lagosur.example`, `compras@casino-cautin.example`, `compras@colegio-bosquenativo.example`, `compras@ribera.example` |
| Cliente B2B pendiente de validación | `compras@volcanazul.example` |

`sistema@cofrial.example` es el usuario técnico de los cambios automáticos: está inactivo y su contraseña es aleatoria.

## Acceso y roles

- **Sesión:** `/login` y `POST /logout` sobre `usuario` (`email`, `password_hash`). Un usuario inactivo no entra (mismo mensaje que una contraseña incorrecta) y, si se desactiva con la sesión abierta, sale en la siguiente petición. Cada inicio registra `ultimo_acceso`. 5 intentos fallidos por cuenta e IP bloquean un minuto (más un tope de 20 por minuto por IP).
- **Destino tras entrar:** el personal va a `/crm` y los clientes a `/`, o a la página que pidieron si su rol la permite.
- **Áreas por rol** (`App\Domain\Clientes\Permisos`, una por gate; el menú del CRM muestra solo las permitidas):

| Área | Administrador | Vendedor | Despachador | Clientes |
| --- | :-: | :-: | :-: | :-: |
| `crm` (todo `/crm`; si no, 403) | ● | ● | ● | |
| `clientes`, `oportunidades`, `tickets` | ● | ● | | |
| `pedidos`, `alertas` | ● | ● | ● | |
| `despachos`, `flota` | ● | | ● | |
| `administracion` | ● | | | |
| `portal.comprar` | | | | ● |

- **Cartera (RN-10):** `Cliente::visiblePara($usuario)` (y lo mismo en `ContactoCliente`, `DireccionDespacho`, `Carrito`, `Oportunidad`, `Interaccion`, `Ticket` y `Pedido`, este último a través de su dirección) limita la consulta: el vendedor ve solo sus clientes, el cliente del portal solo a sí mismo y el administrador y el despachador todo. Un identificador ajeno responde 404. `ClientePolicy` decide las acciones sobre un cliente visible (por ejemplo, solo el administrador reasigna).
- Las secciones del CRM que aún no existen (`/crm/oportunidades`, `/crm/pedidos`, `/crm/despachos`, …) ya tienen ruta y gate y muestran «se construye en T-0xx».

## Carrito (T-010)

- **Servicio:** `App\Domain\Ventas\Carritos` (`agregar`, `cambiarCantidad`, `quitar`, `resumen`). Controlador delgado `Portal\CarritoController`; rutas `/carrito` y `/carrito/items[/{sku}]` con el gate `portal.comprar`.
- **Sin identificador en la URL:** el carrito se resuelve siempre desde la sesión (cliente → su carrito `activo`), así que un cliente no puede nombrar ni tocar el de otro.
- **Precios (RN-01):** el carrito no guarda precio. `resumen()` los pide a `Precios` con la lista vigente del cliente en cada vista. Subtotal = precio × cantidad en centésimas enteras, redondeado al peso; IVA sobre el neto total; total = neto + IVA. B2B ve las líneas en neto y B2C con IVA, y ambos ven el desglose neto, IVA y total.
- **Stock (RN-04):** agregar no reserva. Si la cantidad supera el disponible para venta se ajusta y se avisa (`session('aviso')`); sin disponible, no se agrega. Si después otro pedido reserva stock, la vista marca la línea (`data-excede`).
- **Cantidades:** `kg` acepta dos decimales; `un` y `caja`, solo enteros.
- **Abandono:** `php artisan carritos:marcar-abandonados` (`MarcarCarritosAbandonados`) pasa a `abandonado` los carritos `activo` cuyo `actualizado_en` tiene más de `CARRITO_ABANDONO_HORAS` horas. Corre cada 15 minutos en el planificador (`routes/console.php`, `onOneServer`); en local lo ejecuta el servicio `scheduler` de Compose.
- **Concurrencia:** cada escritura bloquea la fila del carrito (`FOR UPDATE`); la creación usa `createOrFirst` sobre el índice único parcial `carrito_activo_unique`.

## Configuración relevante

- `DB_SSLMODE`: `prefer` en local, `require` (o `verify-full`) contra RDS.
- `DB_CONNECT_TIMEOUT`: segundos antes de abandonar la conexión; mantiene `/health` por debajo del timeout del balanceador.
- La zona horaria de la aplicación y de la sesión de PostgreSQL es `APP_TIMEZONE` (`America/Santiago`). Las columnas `TIMESTAMPTZ` guardan el instante absoluto (UTC), así que no hay ambigüedad al leerlas desde otros componentes.
- Sesiones, caché, colas y modo mantenimiento usan la base (`database`); ninguno usa `file`.
- Cookie de sesión `HttpOnly` y `SameSite=lax` (valores de Laravel); `SESSION_SECURE_COOKIE=true` en AWS para que viaje solo por HTTPS.
- Parámetros de negocio (pedido mínimo, minutos de reserva, horas de abandono del carrito, IVA) en `config/negocio.php`, leídos de `PEDIDO_MINIMO_B2C`, `PEDIDO_MINIMO_B2B`, `RESERVA_STOCK_MINUTOS_B2C`, `CARRITO_ABANDONO_HORAS` e `IVA_PCT`.

## Referencias

[`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) · [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) · [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) · [`docs/API.md`](../../docs/API.md)
