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
| GET, POST | `/registro/persona` | Registro B2C: cuenta activa de inmediato |
| GET, POST | `/registro/empresa` | Registro B2B: crea cliente pendiente, contacto principal, punto de entrega y oportunidad «Prospecto nuevo» (RN-02) |
| — | `/bi/*` | Grafana, servido por Nginx detrás del mismo balanceador. Tiene su propio inicio de sesión |

### 1.3 Portal (roles `cliente_b2c`, `cliente_b2b`)

| Método | Ruta | Descripción | Reglas |
| --- | --- | --- | --- |
| GET | `/carrito` | Carrito abierto con precios de la lista del cliente | RN-01 |
| POST | `/carrito/items` | Agrega `{sku, cantidad}`; crea el carrito si no existe | RN-04 |
| PATCH | `/carrito/items/{sku}` | Cambia la cantidad; se ajusta al disponible y avisa | RN-04 |
| DELETE | `/carrito/items/{sku}` | Quita el producto | |
| GET | `/checkout` | Punto de entrega, fecha, forma de pago y totales (neto, IVA, total) | RN-03, RN-05, RN-06 |
| POST | `/pedidos` | Crea el pedido «Pendiente», copia el precio pactado y reserva stock | RN-01, RN-03, RN-04 |
| GET | `/pedidos/{id}/pago` | Pasarela simulada (solo B2C) | RN-05 |
| POST | `/pedidos/{id}/pago` | Resultado simulado `{resultado: aprobado \| rechazado}`; aprobado confirma el pedido | RN-05 |
| GET | `/mis-pedidos` | Historial del cliente (B2B: de toda la empresa) | |
| GET | `/mis-pedidos/{id}` | Línea de tiempo, despacho y estado de frío, sin datos del conductor | RN-09 |
| POST | `/mis-pedidos/{id}/cancelar` | Cancela si el estado lo permite y libera la reserva | RN-04 |
| POST | `/mis-pedidos/{id}/repetir` | Copia los productos al carrito con precios vigentes (B2B) | RN-01 |
| GET, POST | `/mis-direcciones` | Puntos de entrega del cliente | RN-06 |
| POST | `/tickets` | Reporta un problema, opcionalmente ligado a un pedido | RN-11 |
| POST | `/asistente/mensajes` | `JSON`. Mensaje al asistente IA (ver 1.5) | RN-11 |

### 1.4 CRM (prefijo `/crm`)

Roles: `V` vendedor, `D` despachador, `A` administrador.

| Método | Ruta | Roles | Descripción | Reglas |
| --- | --- | --- | --- | --- |
| GET | `/crm` | V D A | Panel: indicadores, validaciones pendientes, oportunidades sin actividad, alertas de frío de la cartera | RN-10 |
| GET | `/crm/clientes` | V A | Cartera (el vendedor ve solo la suya) | RN-10 |
| GET, POST | `/crm/clientes/nuevo` | V A | Ingresa un prospecto | |
| GET | `/crm/clientes/{id}` | V A | Ficha: datos, contactos, puntos de entrega, interacciones, pedidos, tickets | |
| POST | `/crm/clientes/{id}/validar` | V A | Valida una empresa: asigna lista de precios y vendedor | RN-02 |
| PATCH | `/crm/clientes/{id}/vendedor` | A | Reasigna la cartera | RN-10 |
| POST | `/crm/clientes/{id}/contactos` | V A | Agrega un contacto | |
| POST | `/crm/clientes/{id}/interacciones` | V A | Registra llamada, visita o correo | |
| POST | `/crm/clientes/{id}/pedidos` | V A | Pedido telefónico con los mismos precios y reglas (`canal = crm`) | RN-08 |
| GET | `/crm/oportunidades` | V A | Pipeline en columnas por etapa | RN-10 |
| POST | `/crm/oportunidades` | V A | Crea una oportunidad | |
| PATCH | `/crm/oportunidades/{id}/etapa` | V A | Mueve de etapa si se cumple la condición; exige motivo al perder | RN-09 |
| GET | `/crm/pedidos` | V D A | Listado con filtros por estado, canal y fecha | |
| GET | `/crm/pedidos/{id}` | V D A | Detalle, historial, despacho, temperatura y tickets | |
| POST | `/crm/pedidos/{id}/estado` | V D A | Aplica una transición válida para el rol `{estado, comentario}` | RN-09 |
| POST | `/crm/pedidos/{id}/confirmar-oc` | V A | Valida la orden de compra B2B y confirma | RN-05 |
| GET | `/crm/despachos` | D A | Despachos por fecha y estado | |
| POST | `/crm/despachos` | D A | Crea un despacho con camión, conductor y pedidos del mismo rango de frío | RN-07 |
| POST | `/crm/despachos/{id}/salida` | D A | Registra la salida; los pedidos pasan a «En ruta» | |
| POST | `/crm/despachos/{id}/paradas/{pedido}` | D A | Resultado de la parada: entregado, rechazado o fallido | |
| GET | `/crm/flota` | D A | Última lectura de cada camión | |
| GET | `/crm/alertas` | V D A | Alertas de frío (el vendedor ve las de su cartera) | RN-07 |
| POST | `/crm/alertas/{id}/resolver` | D A | Registra la acción correctiva y cierra la alerta | |
| GET | `/crm/tickets` | V A | Tickets con categoría y prioridad | RN-11 |
| PATCH | `/crm/tickets/{id}` | V A | Reclasifica, asigna o cierra | RN-11 |
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

- El servidor arma el contexto con datos del propio cliente (estado de pedidos, catálogo) y llama a la API externa; el navegador nunca habla con la IA directamente.
- Si la IA no puede resolver, o la API falla, responde con `derivado_a_ticket: true` y crea el ticket.
- Límite de uso por usuario para proteger la cuota gratuita: `429` al superarlo.

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

Proveedor por definir (ADR-0015). La aplicación depende de una interfaz interna, no del proveedor.

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
{ "categoria": "calidad", "prioridad": "alta" }
```

- `categoria` y `prioridad` deben pertenecer a las listas de [`DATA_MODEL.md`](DATA_MODEL.md#ticket). Una respuesta fuera de lista se descarta y el ticket queda `sin_clasificar` con `clasificado_por_ia = false`.
- Tiempo máximo de espera configurable (`AI_TIMEOUT_SEGUNDOS`); hasta dos reintentos en el worker.

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
