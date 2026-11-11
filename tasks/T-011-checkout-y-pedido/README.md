# T-011 · Checkout, pedido y pago

| Campo | Valor |
| --- | --- |
| Fase | 1 · Portal de ventas |
| Dimensión de la pauta | 2 |
| Depende de | [T-010](../T-010-carrito/README.md) |
| Desbloquea | [T-012](../T-012-seguimiento-de-pedidos/README.md), [T-016](../T-016-gestion-de-pedidos/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Convertir el carrito en un pedido con stock reservado y llevarlo a «Confirmado» según la forma de pago de cada perfil.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) RN-01, RN-03 a RN-06, RN-09 y §4
- [`docs/API.md`](../../docs/API.md) §1.3
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) §3
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Checkout con punto de entrega activo, fecha desde el siguiente día hábil, ventana tomada del punto de entrega y forma de pago según el perfil.
- Acción `CrearPedido` transaccional: valida mínimo y stock, crea `pedido` y `detalle_pedido` con el precio pactado, reserva stock, registra `movimiento_inventario` e historial, y marca el carrito como `convertido`.
- Servicio único de transiciones de estado del pedido, que valida la transición y el rol y escribe `historial_estado_pedido`.
- B2C: pasarela simulada con resultado aprobado o rechazado; aprobado confirma el pedido.
- B2B: pago con orden de compra en estado pendiente, a la espera del vendedor.
- Trabajo programado que cancela pedidos B2C «Pendiente» sin pago tras 30 minutos y libera la reserva.

## Fuera de alcance

- Confirmación de la orden de compra por el vendedor (T-016).
- Pasarela real.

## Criterios de aceptación

- [ ] Test: no se puede confirmar bajo el pedido mínimo (B2C y B2B).
- [ ] Test: dos compras simultáneas del último stock no generan sobreventa.
- [ ] Test: el `precio_unitario_pactado` coincide con la lista del cliente al momento de comprar.
- [ ] Test: un pago aprobado deja el pedido en «Confirmado» con su fila de historial.
- [ ] Test: un pedido B2C sin pago se cancela a los 30 minutos y `stock_reservado` vuelve a su valor.
- [ ] Una transición inválida responde 409.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
