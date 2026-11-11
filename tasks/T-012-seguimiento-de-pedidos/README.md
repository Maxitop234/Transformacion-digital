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

- [ ] Test: un cliente que abre un pedido ajeno recibe 404.
- [ ] La respuesta del detalle no contiene nombre, RUT ni teléfono del conductor.
- [ ] Test: repetir un pedido usa el precio actual, no el pactado antes.
- [ ] No se puede cancelar un pedido «En preparación» o posterior.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Recortable: repetir pedido y direcciones múltiples pueden quedar para el final.
