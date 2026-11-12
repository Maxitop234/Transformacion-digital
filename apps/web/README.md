# apps/web · Portal de ventas y CRM

Aplicación Laravel que contiene el portal B2B/B2C, el CRM, el worker de colas y la integración con la IA externa. Creada en [T-001](../../tasks/T-001-bootstrap-laravel/README.md).

## Qué vivirá aquí

| Módulo | Superficie | Tareas |
| --- | --- | --- |
| Clientes y accesos | Inicio de sesión, registro, roles | T-006, T-009 |
| Catálogo e inventario | `/catalogo`, mantenedores | T-007, T-008 |
| Ventas | Carrito (`app/Domain/Ventas`), checkout, pedidos, pagos simulados, seguimiento y puntos de entrega | T-010 a T-012 |
| CRM | `/crm`: panel (`app/Domain/Crm`), clientes, pipeline de oportunidades, pedidos, tickets | T-013 a T-016, T-018 |
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

## Registro de clientes (T-009)

- **`App\Domain\Clientes\RegistrarCliente`**: `persona()` y `empresa()`, cada uno en una transacción. Persona: `usuario` (`cliente_b2c`) + `cliente` activo con «Público general» + dirección. Empresa: `usuario` (`cliente_b2b`) + `cliente` `pendiente_validacion` con RUT normalizado + contacto principal + punto de entrega con ventana + oportunidad «Prospecto nuevo» (`origen = portal`) con su `historial_etapa`.
- **Asignación:** la empresa (cliente y oportunidad) va al vendedor activo con menos clientes en cartera; empata el de menor `id_vendedor`. Así aparece en «Empresas pendientes» de su panel. Sin vendedores activos queda sin asignar, la ve el administrador y no se crea oportunidad.
- **Controlador** `Auth\RegistroController` (middleware `guest`, `throttle:10,1`): valida RUT (dígito verificador y unicidad sobre el normalizado), correo único sin distinguir mayúsculas, contraseña confirmada (mínimo 8, letras y números) y consentimiento obligatorio (`accepted`). Un choque con el índice único por registros simultáneos vuelve como error de validación. Al terminar inicia la sesión y lleva al catálogo.
- **Consentimiento:** se exige y su fecha y hora quedan en `cliente.consentimiento_datos_en` (nulo en prospectos del CRM y en clientes anteriores al cambio).

## Carrito (T-010)

- **Servicio:** `App\Domain\Ventas\Carritos` (`agregar`, `cambiarCantidad`, `quitar`, `resumen`). Controlador delgado `Portal\CarritoController`; rutas `/carrito` y `/carrito/items[/{sku}]` con el gate `portal.comprar`.
- **Sin identificador en la URL:** el carrito se resuelve siempre desde la sesión (cliente → su carrito `activo`), así que un cliente no puede nombrar ni tocar el de otro.
- **Precios (RN-01):** el carrito no guarda precio. `resumen()` los pide a `Precios` con la lista vigente del cliente en cada vista. Subtotal = precio × cantidad en centésimas enteras, redondeado al peso; IVA sobre el neto total; total = neto + IVA. B2B ve las líneas en neto y B2C con IVA, y ambos ven el desglose neto, IVA y total.
- **Stock (RN-04):** agregar no reserva. Si la cantidad supera el disponible para venta se ajusta y se avisa (`session('aviso')`); sin disponible, no se agrega. Si después otro pedido reserva stock, la vista marca la línea (`data-excede`).
- **Cantidades:** `kg` acepta dos decimales; `un` y `caja`, solo enteros.
- **Abandono:** `php artisan carritos:marcar-abandonados` (`MarcarCarritosAbandonados`) pasa a `abandonado` los carritos `activo` cuyo `actualizado_en` tiene más de `CARRITO_ABANDONO_HORAS` horas. Corre cada 15 minutos en el planificador (`routes/console.php`, `onOneServer`); en local lo ejecuta el servicio `scheduler` de Compose.
- **Concurrencia:** cada escritura bloquea la fila del carrito (`FOR UPDATE`); la creación usa `createOrFirst` sobre el índice único parcial `carrito_activo_unique`.

## Pedidos y pago (T-011)

- **`CrearPedido`** (`app/Domain/Ventas`): `__invoke` (portal) y `telefonico` (CRM, T-014) comparten el mismo núcleo. Rechaza pedidos que mezclan rangos de conservación (RN-07, `mensajeRangosMixtos()`, que también usan el carrito y el checkout para avisar). En una transacción bloquea el carrito (portal), valida punto de entrega propio y activo, fecha (`CalendarioEntregas`: desde el siguiente día hábil, lunes a sábado) y pedido mínimo (`negocio.pedido_minimo`; B2C sobre el total con IVA, B2B sobre el neto), crea `pedido` y `detalle_pedido` con el precio de la lista vigente (RN-01), reserva stock, escribe el historial «Pendiente» y pasa el carrito a `convertido`. B2B deja un `pago` `credito_30d` pendiente con la orden de compra.
- **`ReservasStock`** (`app/Domain/Catalogo`): la reserva es un `UPDATE` condicional sobre `inventario` (`stock_disponible − stock_reservado >= cantidad`), en orden de `id_producto`. Dos compras del último stock: la segunda espera el bloqueo de fila y luego no actualiza nada → `SinStock` (409). Cada reserva y liberación deja su `movimiento_inventario`.
- **`TransicionesPedido`**: único lugar que cambia `pedido.id_estado_pedido`. La tabla `TRANSICIONES` replica BUSINESS_RULES.md §4 con quién puede hacer cada paso (`sistema`, `cliente` dueño, `vendedor` de la cartera, `despachador`; el administrador puede todo). Transición inexistente → `TransicionInvalida` (409); actor no permitido → 403. Al cancelar libera la reserva; al entregar o rechazar, la libera y descuenta el stock (T-016). El actor `null` es el usuario técnico `sistema`.
- **`PagarPedido`**: pasarela simulada B2C. Cada intento es una fila de `pago`; el aprobado confirma el pedido (actor: sistema). Vence a los `negocio.reserva_stock_minutos_b2c` minutos de `fecha_pedido`.
- **`CancelarPedidosSinPago`**: `php artisan pedidos:cancelar-sin-pago`, cada minuto en el planificador (`onOneServer`). Cancela los B2C «Pendiente» vencidos y libera su reserva; los B2B esperan al vendedor.
- Conflictos de negocio (`ConflictoPedido` y subclases) son `HttpException` 409: JSON `{message}` o la vista `errors/409`.
- `tests/Feature/Portal/PedidoConcurrenciaTest` usa dos conexiones reales a PostgreSQL: confirma sus datos (sin transacción envolvente) y vacía las tablas en `tearDown`.

## Seguimiento de pedidos (T-012)

- **Controladores:** `Portal\MisPedidosController` (`/mis-pedidos`, detalle, cancelar, repetir) y `Portal\DireccionController` (`/mis-direcciones`), con el gate `portal.comprar`. Todo se resuelve con `visiblePara()`: B2B ve los pedidos de todos sus puntos de entrega; un pedido o punto ajeno responde 404.
- **`SeguimientoPedido`** (`app/Domain/Ventas`): línea de tiempo desde `historial_estado_pedido` y paradas del pedido con camión y patente. Las rupturas de frío salen de `AfectadosPorAlerta::delPedido()` (mismo cruce que el panel y la vista BI). Nunca carga `conductor` ni `despacho.id_conductor` (SECURITY.md §6).
- **Cancelar:** pasa por `TransicionesPedido` con el actor cliente (motivo obligatorio, se guarda como «Cancelado por el cliente: …»). Solo desde «Pendiente» o «Confirmado»; después, 409. Libera la reserva.
- **Repetir** (`RepetirPedido`, solo B2B): llama a `Carritos::agregar` por cada línea, así que aplica el precio vigente (RN-01) y el ajuste al disponible (RN-04); lo que no se puede agregar vuelve como aviso.
- **Puntos de entrega** (`App\Domain\Clientes\PuntosEntrega`): B2B agrega puntos y los activa o desactiva (no se borran: los pedidos los referencian). Siempre queda uno activo.
- «Reportar un problema» crea un ticket ligado al pedido (T-018).

## Tickets (T-018)

- **`App\Domain\Crm\CrearTicket`**: único punto de creación (portal, rechazo de carga en `RegistrarResultadoParada` y, en T-021, el asistente). Nace `abierto`, `sin_clasificar`/`media` (salvo que una regla fije categoría y prioridad, como la carga rechazada: `reclamo`/`alta`), `clasificado_por_ia = false` y con el vendedor del cliente. Rechaza un pedido de otro cliente.
- **Evento `App\Events\TicketCreado`** (`ShouldDispatchAfterCommit`, solo `idTicket`): `AppServiceProvider` encola `ClasificarTicketConIa` (T-021).
- **`GestionarTicket`**: clasificar, reasignar, cambiar estado y cerrar con bloqueo de fila. Ver las reglas en API.md §1.4.
- **Controladores:** `Portal\TicketController` (`/tickets`, el formulario también está en el detalle del pedido) y `Crm\TicketController` (`/crm/tickets`, gate `tickets`). Todo con `visiblePara()` (RN-10).
- Los tickets aparecen en la ficha del cliente, en el detalle del pedido del CRM y en el del portal.

## Integración de IA (T-021)

- **Proveedor** (ADR-0015): `App\Domain\Ia\ClienteIa`, implementada por `ClienteIaCompatibleOpenAi` (Groq por defecto, Gemini de respaldo; `config/ia.php`). Variables: `AI_ENABLED`, `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`, `AI_URL_BASE`, `AI_TIMEOUT_SEGUNDOS`, `AI_ASISTENTE_POR_MINUTO` y `AI_ASISTENTE_POR_DIA`. En Docker van en el `.env` de la raíz; hay que reiniciar `web` y `worker` al cambiarlas.
- **Clasificación:** `TicketCreado` → `App\Jobs\ClasificarTicketConIa` (cola `database`, la procesa el servicio `worker`) → `App\Domain\Ia\ClasificarTicket`. Valida contra `Ticket::CATEGORIAS` y `PRIORIDADES`, y escribe solo si el ticket sigue `sin_clasificar`. Ante `IaNoDisponible` reintenta dos veces; después deja el ticket para clasificación manual.
- **Asistente:** `GET /asistente` y `POST /asistente/mensajes` (`Portal\AsistenteController` → `AsistentePortal`), con `throttle:asistente` por usuario.
- **Datos personales:** `FiltroDatosPersonales` enmascara RUT, correos, teléfonos y nombres conocidos (RN-11).
- **Tests:** `tests/Support/ClienteIaFalso` (se registra con `$this->app->instance(ClienteIa::class, …)`) y `Http::fake`. `phpunit.xml` fuerza `AI_API_KEY` vacía.
- **Probar con la API real:** pon la clave en `AI_API_KEY` del `.env` de la raíz, recrea `web` y `worker` con `docker compose -f infra/docker/compose.dev.yml up -d web worker` (un `restart` no relee el `.env`) y crea un ticket desde `/tickets`.

## Panel del CRM (T-013)

- **Servicio:** `App\Domain\Crm\PanelCrm`; controlador `Crm\InicioController` (`GET /crm`). Todo se filtra con `visiblePara()` (RN-10): vendedor, su cartera; administrador y despachador, todo.
- **Bloques por rol:** los comerciales (indicadores, empresas pendientes, oportunidades sin actividad) solo con el gate `clientes` (vendedor y administrador); las alertas con `alertas` (los tres). El despachador no ve datos comerciales.
- **Indicadores:** oportunidades abiertas (etapa sin cierre); conversión = creadas en la ventana que hoy están en etapa ganada ÷ creadas en la ventana; pedidos por canal en la ventana sin los cancelados, con monto neto Σ cantidad × precio pactado; tickets `abierto` o `en_proceso`.
- **Sin actividad:** la última actividad es la interacción más reciente ligada a la oportunidad o, si la interacción no tiene oportunidad, al mismo cliente; sin interacciones, la fecha de creación.
- **Alertas:** rupturas de frío no resueltas cruzadas con el despacho del camión que estaba en ruta a la hora de la lectura y sus paradas aún no entregadas, el mismo criterio que la vista `bi_pedido_afectado_alerta` (T-020). El vendedor ve solo las alertas que tocan a sus clientes, y solo esos clientes. Administrador y despachador ven además el total de alertas abiertas de la flota.
- **Umbrales:** `OPORTUNIDAD_SIN_ACTIVIDAD_DIAS` (15) y `CONVERSION_VENTANA_DIAS` (90) en `config/negocio.php`.

## Gestión de clientes (T-014)

- **Controladores:** `Crm\ClienteController` (listado con filtros, ficha, prospecto, validación), `Crm\FichaClienteController` (contactos e interacciones) y `Crm\PedidoTelefonicoController`. Todos resuelven el cliente con `visiblePara()` (RN-10: ajeno → 404) y la policy `update`.
- **Prospecto** (`App\Domain\Clientes\RegistrarProspecto`): estado `prospecto`, lista «Público general», sin cuenta; abre su oportunidad en «Prospecto nuevo» con `historial_etapa`. RUT opcional, validado y único (se guarda normalizado `12345678-5`).
- **Validación** (`ValidarEmpresa`, RN-02): solo B2B `pendiente_validacion` con RUT; lista institucional activa y vigente; el vendedor queda como responsable (el administrador puede elegir otro). Desde ese momento `Precios::perfil()` le da su lista y puede comprar.
- **Interacciones** (`App\Domain\Crm\RegistrarInteraccion`): fecha `now()`, tipo de la lista y vendedor (el que la registra o, si es administrador, el del cliente); la oportunidad debe ser del mismo cliente. **Contactos** (`AgregarContacto`): un nuevo principal reemplaza al anterior en la misma transacción.
- **Pedido telefónico** (RN-08): `CrearPedido::telefonico()` comparte con el portal el mismo núcleo (`registrar()`): precio de la lista del cliente (`Precios::perfilDeCliente()`), mínimo, reserva de stock, historial y OC para B2B. Queda con `canal = crm`, `id_vendedor_registra` y sin carrito. Un B2C paga desde el portal dentro de los 30 minutos de reserva.

## Pipeline de oportunidades (T-015)

- **`App\Domain\Crm\TransicionesOportunidad`**: único lugar que cambia `oportunidad.id_etapa`. La tabla `TRANSICIONES` replica BUSINESS_RULES.md §5. Una cerrada o una transición inexistente devuelve 409; una condición no cumplida, 422 con el motivo. Cada cambio escribe `historial_etapa`; el actor `null` es el usuario `sistema`.
- **Condiciones:** contactado exige una interacción (ligada a la oportunidad o al cliente sin oportunidad, como el panel); calificado, monto estimado; propuesta, cliente activo (B2B con `fecha_validacion`) y con lista; negociación, una interacción posterior a la última entrada en «Propuesta»; ganado, un pedido vinculado (se puede elegir uno del cliente, sin oportunidad y no cancelado ni rechazado); perdido, motivo.
- **Vínculo automático:** `GanarOportunidadConPedido` (lo llama `ConfirmarOrdenCompra`) usa `ganarConPedido` con el actor sistema. `deshacerVinculo` lo revierte si el último cambio lo hizo el sistema.
- **`GuardarOportunidad`**: alta en «Prospecto nuevo» (orígenes manuales: `vendedor`, `telefono`, `referido`; `portal` queda para el registro web) y edición mientras está abierta.
- **Controlador** `Crm\OportunidadController` (gate `oportunidades`). Siempre `visiblePara()`.

## Pedidos en el CRM (T-016)

- **Controlador:** `Crm\PedidoController` (`/crm/pedidos`, gate `pedidos`). Lee directo de la base con `visiblePara()` (RN-10), así que un pedido del portal aparece apenas se crea.
- **Cambio de estado:** `TransicionesPedido::disponibles()` da las transiciones que el rol puede hacer desde el estado actual; la vista solo ofrece esas. `CON_COMENTARIO` (cancelar, rechazar y reprogramar una entrega fallida) exige motivo. «Pendiente → Confirmado» queda fuera del cambio genérico: se confirma con el pago en línea (B2C) o con la OC (B2B).
- **`ConfirmarOrdenCompra`** (RN-05): con el pedido bloqueado, confirma por `TransicionesPedido` (rol y cartera), pasa el pago `credito_30d` a `aprobado` y llama a `App\Domain\Crm\GanarOportunidadConPedido` (BUSINESS_RULES.md §6: vincula y gana la oportunidad en «Propuesta» o «Negociación», con el usuario `sistema` en `historial_etapa`).
- **Stock por estado:** Cancelado libera la reserva; Entregado libera y descuenta (`salida`); Rechazado libera y da de baja (`merma`), en `ReservasStock::despachar()`.

## Logística y alertas (T-017)

- **Dominio** `app/Domain/Logistica`: `ArmarDespacho` (RN-07: un solo rango de conservación por despacho; pedidos «Confirmado» sin parada pendiente; camión y conductor libres), `RegistrarSalida`, `RegistrarResultadoParada` (entregado → salida de stock; rechazado → merma y ticket de reclamo; fallido → vuelve a «Confirmado»; cierra el despacho sin paradas pendientes), `ResolverAlerta` (acción correctiva obligatoria) y `AfectadosPorAlerta` (cruce alerta → lectura → despacho en ruta a esa hora → paradas → clientes; lo usan el panel, las alertas y los avisos).
- Todos los cambios de estado del pedido pasan por `TransicionesPedido`, con el comentario «Asignado al despacho N», «Salida del camión X» o el motivo de la parada.
- **Avisos:** `php artisan alertas:notificar` (cada minuto, `onOneServer`) envía por correo (`AlertaFrioNueva`, `MAIL_MAILER`; en local al log) cada ruptura de frío nueva a los despachadores activos y a los vendedores de los clientes afectados. La marca de la última alerta avisada vive en la caché de la base (`alertas.ultima_notificada`); la primera pasada solo la fija.
- **Controladores:** `Crm\DespachoController` y `Crm\FlotaController` (gates `despachos` y `flota`: despachador y administrador) y `Crm\AlertaController` (gate `alertas`; resolver solo con `despachos`).

## Configuración relevante

- `DB_SSLMODE`: `prefer` en local, `require` (o `verify-full`) contra RDS.
- `DB_CONNECT_TIMEOUT`: segundos antes de abandonar la conexión; mantiene `/health` por debajo del timeout del balanceador.
- La zona horaria de la aplicación y de la sesión de PostgreSQL es `APP_TIMEZONE` (`America/Santiago`). Las columnas `TIMESTAMPTZ` guardan el instante absoluto (UTC), así que no hay ambigüedad al leerlas desde otros componentes.
- Sesiones, caché, colas y modo mantenimiento usan la base (`database`); ninguno usa `file`.
- Cookie de sesión `HttpOnly` y `SameSite=lax` (valores de Laravel); `SESSION_SECURE_COOKIE=true` en AWS para que viaje solo por HTTPS.
- IA externa en `config/ia.php` (ver «Integración de IA»). Parámetros de negocio (pedido mínimo, minutos de reserva, horas de abandono del carrito, umbrales del panel del CRM, IVA) en `config/negocio.php`, leídos de `PEDIDO_MINIMO_B2C`, `PEDIDO_MINIMO_B2B`, `RESERVA_STOCK_MINUTOS_B2C`, `CARRITO_ABANDONO_HORAS`, `OPORTUNIDAD_SIN_ACTIVIDAD_DIAS`, `CONVERSION_VENTANA_DIAS` e `IVA_PCT`.

## Referencias

[`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) · [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) · [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) · [`docs/API.md`](../../docs/API.md)
