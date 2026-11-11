# T-017 · Despachos, flota y alertas de frío

| Campo | Valor |
| --- | --- |
| Fase | 2 · CRM |
| Dimensión de la pauta | 2, 3 |
| Depende de | [T-016](../T-016-gestion-de-pedidos/README.md) |
| Desbloquea | Ninguna |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Conectar el pedido con el camión y la telemetría, para que una ruptura de frío se gestione antes de la entrega.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) RN-07 y §4
- [`docs/API.md`](../../docs/API.md) §1.4
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) §5
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Crear un despacho con camión, conductor, fecha y pedidos confirmados, ordenados por parada.
- Validación: todos los productos del despacho comparten rango de conservación.
- Registrar la salida: los pedidos pasan a «En ruta».
- Resultado por parada: entregado (genera la salida de inventario), rechazado (crea un ticket) o fallido (el pedido vuelve a «Confirmado»).
- Vista de flota con la última lectura de cada camión.
- Alertas: listado, pedidos y clientes afectados, y cierre con acción correctiva obligatoria.
- Notificación al despachador y al vendedor de los clientes afectados cuando aparece una alerta nueva.

## Fuera de alcance

- Optimización de rutas.
- Generación de telemetría (T-019).

## Criterios de aceptación

- [x] Test: no se puede crear un despacho que mezcle refrigerados y congelados.
- [x] Test: la entrega descuenta `stock_disponible` y `stock_reservado` y registra el movimiento de salida.
- [x] Test: una entrega fallida devuelve el pedido a «Confirmado» y permite asignarlo a otro despacho.
- [x] Test: resolver una alerta sin acción correctiva se rechaza.
- [x] El vendedor solo ve alertas de su cartera.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-11):** 176 tests en verde (1 omitido, `EsquemaCoincideConDataModelTest`, que dentro del contenedor no ve `docs/`), 12 nuevos en `tests/Feature/Crm/LogisticaTest.php`: RN-07 sin mezclar refrigerados y congelados; armado con orden de paradas, camión ocupado y pedido no confirmado; salida a «En ruta» (dos veces: 409); entrega que descuenta disponible y reservado con movimiento `salida` y cierre del despacho; rechazo con motivo, merma y ticket; entrega fallida que vuelve a «Confirmado» y entra en otro despacho; alerta sin acción correctiva rechazada (y resuelta luego, 409 al repetir, 403 al vendedor); RN-10 alertas por cartera; aviso a despachador y vendedor afectado sin repetir; planificador; flota con la última lectura; acceso por rol. Recorrido en el navegador con `despacho@cofrial.example`: el despacho mixto (pedidos 167 cárnico fresco + 170 refrigerado) se rechazó por RN-07; el despacho 95 (CAM-001, refrigerado, pedidos 170 y 171) salió a ruta y `iot_umbral_camion` pasó a 0–5 °C para el simulador; se resolvió la alerta 49; la flota mostró las lecturas en vivo; la parada 170 quedó entregada. **El despacho 95 quedó en ruta a propósito** con la parada 171, para que la demo muestre alertas con clientes afectados en el panel; se cierra registrando esa parada.
- **Avisos:** correo por `MAIL_MAILER` a los despachadores y a los vendedores de los clientes afectados, sin tocar el modelo de datos (la marca va en la caché de la base). Si se prefiere un aviso dentro de la aplicación (campana con no leídas), hace falta la tabla `notifications` de Laravel: es un cambio de esquema y requiere decisión del equipo. En AWS el correo necesita un transporte decidido (SES no está en los ADR); mientras tanto, `MAIL_MAILER=log`.
- **Pedidos que mezclan rangos (resuelto, 2026-11-11):** se decidió bloquearlos en origen. `CrearPedido` rechaza un pedido con productos de más de un rango de conservación (portal y pedido telefónico, RN-07); el carrito lo advierte y el checkout deshabilita «Confirmar pedido». Dividir un pedido por rango al despachar exigiría paradas por línea (cambio de esquema); queda como mejora futura. Tests: `PedidoTest::test_rn07_un_pedido_no_puede_mezclar_rangos_de_frio` y `GestionClientesTest::test_rn07_el_pedido_telefonico_tampoco_mezcla_rangos_de_frio`. Los pedidos mixtos creados antes de esta regla (si los hay en la demo) siguen sin poder despacharse; el armado los marca «(mezcla rangos)».
- **Decisiones:** capacidad del camión no validada (las unidades mezclan kg, unidades y cajas); un camión o conductor no puede tener dos despachos programados o en ruta; la fecha del despacho no puede ser pasada. El despachador ve el nombre del conductor solo en el CRM; el portal sigue sin mostrarlo.
