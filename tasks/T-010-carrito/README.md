# T-010 · Carrito de compras

| Campo | Valor |
| --- | --- |
| Fase | 1 · Portal de ventas |
| Dimensión de la pauta | 2 |
| Depende de | [T-008](../T-008-catalogo-y-precios/README.md) |
| Desbloquea | [T-011](../T-011-checkout-y-pedido/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Permitir que el cliente arme su compra con los precios de su lista.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) RN-01, RN-04
- [`docs/API.md`](../../docs/API.md) §1.3
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) `carrito`, `item_carrito`
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Agregar, cambiar cantidad y quitar productos; un solo carrito `activo` por cliente.
- El carrito no guarda precios: se recalculan con el servicio de precios en cada vista.
- Si la cantidad supera el disponible, se ajusta y se avisa.
- Totales con neto, IVA y total.
- Trabajo programado que marca como `abandonado` los carritos sin actividad por 24 horas.

## Fuera de alcance

- Reserva de stock y pedido (T-011).

## Criterios de aceptación

- [ ] Agregar dos veces el mismo producto suma cantidades en una sola fila.
- [ ] Test: agregar al carrito no modifica `stock_reservado`.
- [ ] Test: el total del carrito cambia si cambia el precio de la lista.
- [ ] Test: un carrito con 24 horas sin actividad pasa a `abandonado`.
- [ ] Un cliente no puede ver ni modificar el carrito de otro.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
