# Interfaces

Contratos del sistema: las rutas HTTP de la aplicación web, el contrato de telemetría que escribe el simulador IoT y el contrato con la API de IA externa.

> **Estado: contrato objetivo.** Nada de esto está implementado todavía. Cada tarea que cree o cambie una ruta actualiza este documento en el mismo cambio. Si la implementación se aparta del contrato, gana lo que quede escrito aquí después del cambio.

## 1. Aplicación web

La aplicación es un monolito Laravel renderizado en el servidor. La mayoría de las rutas devuelve HTML; las marcadas con `JSON` devuelven `application/json`.

### 1.1 Convenciones

- **Autenticación:** sesión con cookie (guardada en base de datos). Toda petición que modifique estado lleva token CSRF.
- **Autorización:** por rol. Una ruta fuera del rol responde `403`; un recurso fuera del alcance del usuario (por ejemplo, un cliente de otra cartera) responde `404` para no revelar su existencia.
- **Validación:** errores de formulario vuelven a la vista con mensajes; en rutas JSON responden `422` con `{ "message": "...", "errors": { "campo": ["..."] } }`.
- **Identificadores:** numéricos internos en la URL, salvo productos (`sku`).
- **Dinero:** enteros o decimales en CLP, sin separadores, en JSON. El formato con puntos es solo de presentación.
- **Fechas:** ISO 8601 con zona en JSON; `America/Santiago` en pantalla.

### 1.2 Rutas públicas y de sistema

| Método | Ruta | Descripción |
| --- | --- | --- |
| GET | `/health` | `JSON`. Chequeo del balanceador: `200 {"status":"ok"}` si la aplicación responde y llega a la base; `503 {"status":"unavailable"}` si no (la conexión corta a los `DB_CONNECT_TIMEOUT` segundos). Sin autenticación, sin sesión, fuera del modo mantenimiento, sin datos sensibles |
| GET | `/` | Portada del portal |
| GET | `/catalogo` | Catálogo de productos activos, 24 por página. El precio sale de `App\Domain\Catalogo\Precios`: lista del cliente si su cuenta está activa y la lista vigente; si no (visitante, personal, empresa pendiente, lista vencida), «Público general». B2B ve precios netos; el resto, con IVA. Filtros combinables: `categoria` (id), `rango` (id), `disponible=1` (disponible para venta > 0), `q` (nombre o SKU, sin distinguir mayúsculas) |
| GET | `/productos/{sku}` | Detalle de producto con conservación y disponible para venta. Un SKU inexistente o inactivo responde `404` |
| GET, POST | `/login` | Inicio de sesión (clientes y equipo) |
| POST | `/logout` | Cierre de sesión |
| GET, POST | `/registro/persona` | Registro B2C (solo sin sesión) `{nombre, email, password, password_confirmation, calle, id_comuna, referencia?, ventana_inicio?, ventana_fin?, consentimiento}`: `usuario`, `cliente` activo con «Público general» y `direccion_despacho` en una transacción; inicia la sesión. Correo repetido, contraseña débil (mínimo 8, letras y números) o sin consentimiento: vuelve con errores. 10 intentos por minuto |
| GET, POST | `/registro/empresa` | Registro B2B (solo sin sesión) `{rut, razon_social, contacto_nombre, contacto_cargo?, contacto_telefono?, email, password, password_confirmation, alias?, calle, id_comuna, referencia?, ventana_inicio, ventana_fin, consentimiento}`. Crea el cliente `pendiente_validacion`, el contacto principal (con el correo de la cuenta), el punto de entrega con su ventana y la oportunidad «Prospecto nuevo» con origen `portal`, asignada al vendedor activo con menos clientes (RN-02). RUT con dígito verificador inválido o ya registrado: error en `rut`. Ve el catálogo, pero no compra hasta la validación |
| — | `/bi/*` | Grafana, servido por Nginx detrás del mismo balanceador. Tiene su propio inicio de sesión |

### 1.3 Portal (roles `cliente_b2c`, `cliente_b2b`)

| Método | Ruta | Descripción | Reglas |
| --- | --- | --- | --- |
| GET | `/carrito` | Carrito activo con precios de la lista vigente del cliente; neto, IVA y total | RN-01 |
| POST | `/carrito/items` | Agrega `{sku, cantidad}` (cantidad opcional, 1 por defecto) y suma si el producto ya estaba; crea el carrito si no existe. No reserva stock | RN-02, RN-04 |
| PATCH | `/carrito/items/{sku}` | Fija la cantidad; si supera el disponible para venta se ajusta y avisa | RN-04 |
| DELETE | `/carrito/items/{sku}` | Quita el producto | |
| GET | `/checkout` | Punto de entrega activo con su ventana, fecha (desde el siguiente día hábil, lunes a sábado), forma de pago según el perfil y totales (neto, IVA, total). «Confirmar pedido» se deshabilita bajo el mínimo, con líneas sin precio o sobre el disponible | RN-03, RN-05, RN-06 |
| POST | `/pedidos` | `{id_direccion, fecha_entrega_solicitada, orden_compra (B2B), observaciones?}`. Crea el pedido «Pendiente», copia el precio pactado, reserva stock y marca el carrito `convertido`. B2C → pasarela; B2B → pago `credito_30d` pendiente con la OC. Bajo el mínimo o con productos de distintos rangos de frío: 422. Sin stock: 409 (en HTML vuelve al carrito con el aviso) | RN-01, RN-03, RN-04, RN-05, RN-07 |
| GET | `/pedidos/{id}/pago` | Pasarela simulada (solo B2C; B2B: 403; pedido ajeno: 404) | RN-05 |
| POST | `/pedidos/{id}/pago` | Resultado simulado `{metodo: tarjeta \| transferencia, resultado: aprobado \| rechazado}`; aprobado confirma el pedido; rechazado permite reintentar. Pedido que ya no está «Pendiente» o con el plazo de 30 min vencido: 409 | RN-05 |
| GET | `/mis-pedidos` | Historial del cliente, 20 por página (B2B: de todos los puntos de entrega de la empresa) | RN-10 |
| GET | `/mis-pedidos/{id}` | Productos con precio pactado, línea de tiempo desde `historial_estado_pedido` y, por despacho, camión, parada, resultado de la entrega y rupturas de frío del trayecto (mismo cruce que las alertas del CRM). Sin datos del conductor. Pedido ajeno: 404 | RN-09 |
| POST | `/mis-pedidos/{id}/cancelar` | `{motivo}`. Cancela desde «Pendiente» o «Confirmado» y libera la reserva; desde «En preparación» o posterior: 409 | RN-04 |
| POST | `/mis-pedidos/{id}/repetir` | Copia productos y cantidades al carrito activo; el carrito cobra el precio vigente de la lista, no el pactado. Omite con aviso lo que ya no se vende o no tiene stock. Solo B2B (B2C: 403) | RN-01, RN-04 |
| GET, POST | `/mis-direcciones` | Puntos de entrega del cliente. POST `{alias, calle, id_comuna, referencia?, ventana_inicio?, ventana_fin?}` solo B2B (B2C: 403) | RN-06 |
| PATCH | `/mis-direcciones/{id}` | `{activa}`. Activa o desactiva un punto (B2B); siempre queda al menos uno activo. Punto ajeno: 404 | RN-06 |
| GET | `/tickets` | Formulario «Reportar un problema» (`?pedido=` lo preselecciona) y los tickets del propio cliente | RN-10 |
| POST | `/tickets` | `{asunto, descripcion, id_pedido?}`. Crea el ticket `abierto`, `sin_clasificar`, prioridad `media`, `clasificado_por_ia = false`, asignado al vendedor del cliente, y emite `TicketCreado`. Pedido ajeno: 404. Límite de 10 por minuto | RN-11 |
| POST | `/asistente/mensajes` | `JSON`. Mensaje al asistente IA (ver 1.5) | RN-11 |

### 1.4 CRM (prefijo `/crm`)

Roles: `V` vendedor, `D` despachador, `A` administrador.

| Método | Ruta | Roles | Descripción | Reglas |
| --- | --- | --- | --- | --- |
| GET | `/crm` | V D A | Panel. V y A: indicadores (oportunidades abiertas, conversión y pedidos por canal en `CONVERSION_VENTANA_DIAS`, tickets abiertos), empresas pendientes de validación y oportunidades sin interacciones en `OPORTUNIDAD_SIN_ACTIVIDAD_DIAS`. V, D y A: alertas de frío abiertas con los pedidos y clientes afectados. El vendedor ve solo su cartera; el despachador, ningún bloque comercial | RN-10 |
| GET | `/crm/clientes` | V A | Cartera (el vendedor ve solo la suya). Filtros `q` (nombre o RUT), `tipo` (`B2B`, `B2C`) y `estado` | RN-10 |
| GET, POST | `/crm/clientes/nuevo` | V A | Ingresa un prospecto `{tipo, nombre_razon_social, rut?, origen, titulo_oportunidad, id_vendedor (A), contacto_*?}` en estado `prospecto`, con su oportunidad en «Prospecto nuevo». El vendedor lo ingresa a su propia cartera | RN-10 |
| GET | `/crm/clientes/{id}` | V A | Ficha: datos, contactos, puntos de entrega, interacciones, pedidos, tickets | |
| POST | `/crm/clientes/{id}/validar` | V A | Valida una empresa pendiente con RUT `{id_lista_precio, id_vendedor (A)}`: lista institucional vigente, vendedor (el vendedor se asigna a sí mismo), `fecha_validacion` y estado `activo`. Otro estado: 409 | RN-02 |
| PATCH | `/crm/clientes/{id}/vendedor` | A | Reasigna la cartera | RN-10 |
| POST | `/crm/clientes/{id}/contactos` | V A | Agrega un contacto `{nombre, cargo?, email, telefono?, es_principal?}`; un nuevo principal reemplaza al anterior | |
| POST | `/crm/clientes/{id}/interacciones` | V A | Registra `{tipo: llamada\|correo\|reunion\|visita\|mensaje, resumen, id_oportunidad?}` con fecha y vendedor (el del cliente si la registra un administrador) | |
| GET | `/crm/clientes/{id}/pedidos/nuevo` | V A | Formulario del pedido telefónico con los precios de la lista del cliente | RN-08 |
| POST | `/crm/clientes/{id}/pedidos` | V A | Pedido telefónico `{lineas[{id_producto, cantidad}], id_direccion, fecha_entrega_solicitada, orden_compra (B2B), observaciones?}` con `CrearPedido::telefonico()`: mismos precios, mínimo, rango de frío único y reserva que el portal; `canal = crm` e `id_vendedor_registra`. Sin stock: 409 en JSON, en HTML vuelve con el error | RN-08 |
| GET | `/crm/oportunidades` | V A | Pipeline en columnas por etapa | RN-10 |
| POST | `/crm/oportunidades` | V A | Crea una oportunidad | |
| PATCH | `/crm/oportunidades/{id}/etapa` | V A | Mueve de etapa si se cumple la condición; exige motivo al perder | RN-09 |
| GET | `/crm/pedidos` | V D A | Listado con filtros `estado`, `canal`, `desde`, `hasta` (fecha del pedido) y `cliente` (razón social). El vendedor ve solo su cartera | RN-10 |
| GET | `/crm/pedidos/{id}` | V D A | Detalle: productos con precio pactado, historial con responsable y comentario, pago, despacho y tickets; ofrece solo las transiciones válidas para el rol | RN-09 |
| POST | `/crm/pedidos/{id}/estado` | V D A | Aplica una transición válida para el rol `{estado, comentario}`. Cancelar, rechazar y reprogramar exigen comentario (422). Rol no permitido: 403; transición inexistente: 409. «Pendiente → Confirmado» no va por aquí (pago en línea o `confirmar-oc`) | RN-09 |
| POST | `/crm/pedidos/{id}/confirmar-oc` | V A | `{comentario?}`. Valida la orden de compra B2B: pago `credito_30d` → `aprobado`, pedido → «Confirmado» y, si el cliente tenía una oportunidad en «Propuesta» o «Negociación», la gana con este pedido. Sin OC pendiente: 409 | RN-05 |
| GET | `/crm/despachos` | D A | Despachos por `fecha` y `estado`, con el formulario de armado y los pedidos «Confirmado» sin parada pendiente (con su rango de frío) | |
| GET | `/crm/despachos/{id}` | D A | Paradas en orden, estado de cada una y acciones (salida, resultado) | |
| POST | `/crm/despachos` | D A | `{camion_id, id_conductor, fecha_programada, pedidos[], orden[id_pedido]}`. Pedidos «Confirmado» sin otra parada pendiente, todos del mismo rango de conservación (si no, 422); camión y conductor sin otro despacho programado o en ruta. Los pedidos pasan a «En preparación» | RN-07 |
| POST | `/crm/despachos/{id}/salida` | D A | Registra la salida (`salida_real`); el despacho y sus pedidos pasan a «En ruta». Si no está programado: 409 | |
| POST | `/crm/despachos/{id}/paradas/{pedido}` | D A | `{resultado: entregado\|rechazado\|fallido, motivo}`. Entregado: pedido «Entregado» y salida de inventario. Rechazado: «Rechazado», merma y ticket de reclamo de prioridad alta. Fallido: vuelve a «Confirmado» para otro despacho. Rechazado y fallido exigen motivo. Sin paradas pendientes, el despacho queda `finalizado` | RN-07, RN-09 |
| GET | `/crm/flota` | D A | Última lectura de cada camión, su despacho en ruta y si tiene una ruptura de frío abierta | |
| GET | `/crm/alertas` | V D A | Alertas (`estado`: `abiertas`, `resueltas`, `todas`) con pedidos y clientes afectados. El vendedor ve solo las que tocan su cartera y solo esos clientes | RN-07, RN-10 |
| POST | `/crm/alertas/{id}/resolver` | D A | `{accion_correctiva}` obligatoria (mínimo 10 caracteres); registra quién y cuándo. Ya resuelta: 409 | |
| GET | `/crm/tickets` | V A | Bandeja con filtros `categoria`, `prioridad` y `estado` (por defecto `pendientes`: abierto o en proceso), ordenada por prioridad. El vendedor ve solo su cartera | RN-10, RN-11 |
| GET | `/crm/tickets/{id}` | V A | Detalle con cliente, pedido y formulario de gestión. Ajeno: 404 | RN-10 |
| PATCH | `/crm/tickets/{id}` | V A | `{categoria?, prioridad?, estado?, id_vendedor?}`. Clasificar a mano deja `clasificado_por_ia = false`; resolver o cerrar exige categoría (422); cerrar registra `cerrado_en`; un ticket cerrado no cambia (409). El administrador asigna a cualquier vendedor activo; el vendedor solo puede tomarlo para sí (403) | RN-11 |
| GET | `/crm/admin` | A | Índice de los mantenedores | |
| GET, POST | `/crm/admin/productos`, `/crm/admin/productos/nuevo` | A | Catálogo: listado con búsqueda y alta (crea su `inventario` en cero) | |
| GET, PATCH | `/crm/admin/productos/{id}` | A | Edita categoría, rango de conservación, unidad y activo | |
| GET, POST | `/crm/admin/listas-precio`, `/crm/admin/listas-precio/nueva` | A | Listas de precios y alta | |
| GET, PATCH | `/crm/admin/listas-precio/{id}` | A | Edita nombre, vigencia y activa | |
| PATCH | `/crm/admin/listas-precio/{id}/precios` | A | Fija `precios[id_producto]`; vacío quita el producto de la lista. No toca pedidos existentes | RN-01 |
| GET, POST | `/crm/admin/inventario` | A | Stock por producto y registro `{id_producto, tipo: ingreso\|ajuste, cantidad}`; cada uno genera `movimiento_inventario` con el responsable | RN-04 |
| GET, POST | `/crm/admin/usuarios`, `/crm/admin/usuarios/nuevo` | A | Usuarios; el alta es solo para personal (las cuentas de cliente nacen en el portal) | |
| GET, PATCH | `/crm/admin/usuarios/{id}` | A | Correo, rol, contraseña, activar y desactivar | |
| PATCH | `/crm/admin/usuarios/{id}/cartera` | A | Mueve toda la cartera del vendedor a `id_vendedor_destino` | RN-10 |

### 1.5 Asistente IA

`POST /asistente/mensajes`

```json
{ "mensaje": "¿Dónde está mi pedido 1042?" }
```

Respuesta `200`:

```json
{
  "respuesta": "Tu pedido 1042 está en ruta y llegaría hoy entre 10:00 y 12:00.",
  "derivado_a_ticket": false,
  "id_ticket": null
}
```

- Solo clientes (`cliente_b2c`, `cliente_b2b`); la página está en `GET /asistente`. `mensaje`: hasta 500 caracteres (422 si falta).
- El servidor arma el contexto con datos del propio cliente y llama a la API externa; el navegador nunca habla con la IA directamente. El contexto lleva sus 10 pedidos más recientes y los que nombre (estado, fechas, ventana, camión, parada, rupturas de frío) y hasta 5 productos activos que coincidan con su mensaje. Un número de pedido ajeno solo aparece como «no encontrado».
- Si la IA no puede resolver, o la API falla, responde con `derivado_a_ticket: true` y crea el ticket.
- Límite de uso por usuario para proteger la cuota gratuita (`AI_ASISTENTE_POR_MINUTO`, 5 por defecto, y `AI_ASISTENTE_POR_DIA`, 50): `429` al superarlo.
- Si deriva, el ticket queda con asunto «Consulta desde el asistente» y el mensaje del cliente como descripción; luego se clasifica como cualquier otro.

## 2. Contrato de telemetría IoT

El simulador **no usa HTTP**: escribe directamente en PostgreSQL con el usuario `iot_writer` (ADR-0006). El contrato es la fila que inserta.

### 2.1 Lectura

Una fila en `telemetria` por camión cada 30 segundos:

| Campo | Tipo | Regla |
| --- | --- | --- |
| `camion_id` | String | Debe existir en `camion`. Formato `CAM-001` … `CAM-050` |
| `fecha_hora` | Timestamp con zona | Momento de la lectura, en UTC |
| `temp_camara` | Float | °C en la cámara de frío |
| `lat` | Float | -90 a 90 |
| `long` | Float | -180 a 180 |
| `combustible_pct` | Integer | 0 a 100 |
| `alerta_ruptura_frio` | Boolean | `true` si `temp_camara` está fuera del rango de conservación aplicable al camión |

Ejemplo:

```sql
INSERT INTO telemetria
    (camion_id, fecha_hora, temp_camara, lat, "long", combustible_pct, alerta_ruptura_frio)
VALUES
    ('CAM-017', now(), 9.4, -38.7359, -72.5904, 63, true)
RETURNING id_telemetria;
```

### 2.2 Alerta

Cuando una lectura inicia un episodio de ruptura, el simulador inserta además una fila en `alerta` que referencia esa lectura:

```sql
INSERT INTO alerta (id_telemetria, tipo, severidad, estado)
VALUES (:id_telemetria, 'ruptura_frio', 'alta', 'abierta');
```

- No se crea una alerta nueva mientras el camión tenga una alerta de ruptura abierta.
- La resolución (`accion_correctiva`, `id_usuario_resuelve`, `fecha_resolucion`) la registra el despachador desde el CRM, no el simulador.

### 2.3 Comportamiento

- Rango aplicable: el del despacho en ruta del camión, leído de la vista `iot_umbral_camion` (`temp_min_c`, `temp_max_c`; ver [`db/vistas_iot.sql`](../db/vistas_iot.sql)); si es nulo, un rango por defecto configurable. La misma vista indica en `alerta_ruptura_abierta` si ya hay una alerta abierta para ese camión.
- `iot_writer` solo puede insertar en `telemetria` y `alerta` y leer esa vista; para obtener `id_telemetria` usa `RETURNING`.
- Ante un error de conexión, reintenta con espera creciente y sigue; no termina el proceso.
- Registra en la salida estándar cada lote insertado, para poder mostrarlo con `docker logs` en la defensa.

## 3. Contrato con la API de IA externa

Groq con `openai/gpt-oss-20b` y Gemini de respaldo, ambos por su endpoint compatible con OpenAI (`POST {AI_URL_BASE}/chat/completions`, `response_format` `json_schema` estricto, temperatura 0; ADR-0015). La aplicación depende de la interfaz `App\Domain\Ia\ClienteIa`, no del proveedor.

### 3.1 Clasificación de tickets

Entrada que se envía (después de depurar datos personales):

```json
{
  "asunto": "Carga llegó tibia",
  "descripcion": "El pedido llegó con los lácteos a temperatura ambiente...",
  "tiene_pedido_asociado": true
}
```

Salida que se exige al modelo (JSON estricto, validado antes de guardar):

```json
{ "categoria": "reclamo", "prioridad": "alta" }
```

- La entrada va delimitada entre `<ticket>` y `</ticket>`. No lleva identificadores del cliente ni del pedido, solo si existe uno.
- `categoria` (`consulta`, `reclamo`, `cambio_pedido`, `cotizacion`, `otro`) y `prioridad` deben pertenecer a las listas de [`DATA_MODEL.md`](DATA_MODEL.md#ticket). Una respuesta fuera de lista se descarta y el ticket queda `sin_clasificar` con `clasificado_por_ia = false`.
- Solo se clasifican tickets que siguen `sin_clasificar`: los de regla (carga rechazada) y los que un ejecutivo clasificó mientras esperaban no se tocan.
- Tiempo máximo de espera configurable (`AI_TIMEOUT_SEGUNDOS`, 15 por defecto); hasta dos reintentos en el worker (cola `database`), con espera de 10 y 20 segundos.

### 3.2 Reglas comunes

- **Nunca se envían** RUT, nombres, razón social, correos, teléfonos ni direcciones (RN-11). El texto libre del cliente pasa por un filtro que enmascara RUT, correos y teléfonos.
- La clave de la API vive solo en `.env` del servidor.
- El texto del cliente se trata como dato, no como instrucciones: el prompt lo delimita y la salida se valida contra un esquema.
- Una falla de la IA nunca bloquea una venta ni la creación de un ticket.

## 4. Códigos de estado

| Código | Uso |
| --- | --- |
| 200 / 201 | Operación correcta |
| 302 | Redirección tras un formulario |
| 401 | Sin sesión (JSON); en HTML redirige a `/login` |
| 403 | El rol no permite la acción |
| 404 | No existe o está fuera del alcance del usuario |
| 409 | Conflicto de negocio: sin stock, transición de estado inválida |
| 422 | Datos inválidos |
| 429 | Límite de uso superado |
| 503 | `/health` cuando no hay conexión con la base |
