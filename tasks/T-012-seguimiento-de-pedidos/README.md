# T-012 · Seguimiento de pedidos en el portal

| Campo | Valor |
| --- | --- |
| Fase | 1 · Portal de ventas |
| Dimensión de la pauta | 2 |
| Depende de | [T-011](../T-011-checkout-y-pedido/README.md) |
| Desbloquea | [T-018](../T-018-tickets/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Dar al cliente visibilidad de sus pedidos, de su despacho y de la cadena de frío, y permitirle recomprar.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) §1 y §4
- [`docs/API.md`](../../docs/API.md) §1.3
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §6
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- `/mis-pedidos`: historial del cliente; en B2B, de toda la empresa.
- Detalle con línea de tiempo desde `historial_estado_pedido`.
- Bloque de despacho: camión, parada y si el trayecto tuvo rupturas de frío, sin datos del conductor.
- Cancelar cuando el estado lo permite, liberando la reserva.
- Repetir pedido: copia los productos al carrito con precios vigentes.
- Gestión de puntos de entrega (B2B) en `/mis-direcciones`.
- Botón «Reportar un problema» (el ticket se implementa en T-018).

## Fuera de alcance

- Mapa en vivo del camión.

## Criterios de aceptación

- [x] Test: un cliente que abre un pedido ajeno recibe 404.
- [x] La respuesta del detalle no contiene nombre, RUT ni teléfono del conductor.
- [x] Test: repetir un pedido usa el precio actual, no el pactado antes.
- [x] No se puede cancelar un pedido «En preparación» o posterior.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Recortable: repetir pedido y direcciones múltiples pueden quedar para el final.
- Hecho completo, incluidos repetir y direcciones. Tests en `apps/web/tests/Feature/Portal/SeguimientoPedidoTest.php` (`php artisan test --filter=SeguimientoPedidoTest`).
- Repetir pedido es solo B2B, como dice API.md §1.3 y BUSINESS_RULES.md §2. A B2C se le responde 403.
- Se agregó `PATCH /mis-direcciones/{id}` para activar o desactivar puntos. Los puntos no se borran porque los pedidos los referencian, y siempre queda uno activo.
- Cancelar un pedido B2C ya pagado («Confirmado») no genera devolución: el pago simulado queda «aprobado». Si hace falta reembolso, es trabajo aparte.
- «Reportar un problema» queda deshabilitado en el detalle. T-018 debe habilitarlo con `POST /tickets` y el `id_pedido`.
- El historial muestra los comentarios del personal tal cual (por ejemplo, el motivo de una entrega fallida). Hoy ninguno incluye datos del conductor, pero quien escriba comentarios debe tenerlo presente.
