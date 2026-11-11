# T-016 · Gestión de pedidos en el CRM

| Campo | Valor |
| --- | --- |
| Fase | 2 · CRM |
| Dimensión de la pauta | 2 |
| Depende de | [T-011](../T-011-checkout-y-pedido/README.md), [T-013](../T-013-panel-crm/README.md) |
| Desbloquea | [T-017](../T-017-logistica-y-alertas/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Permitir que el equipo siga y avance los pedidos generados en la web y en el CRM.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) §4 y RN-05, RN-09
- [`docs/API.md`](../../docs/API.md) §1.4
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Listado con filtros por estado, canal, fecha y cliente.
- Detalle: productos con precio pactado, historial de estados con responsable, pago, despacho y tickets vinculados.
- Cambio de estado que solo ofrece las transiciones válidas para el rol y exige comentario cuando corresponde.
- Confirmación de orden de compra B2B por el vendedor.
- Cancelación con liberación de la reserva.

## Fuera de alcance

- Armado de despachos (T-017).

## Criterios de aceptación

- [ ] Un pedido creado en el portal aparece en el CRM sin recargar datos a mano.
- [ ] Test: el vendedor no puede pasar un pedido a «En ruta»; el despachador sí.
- [ ] Test: confirmar la orden de compra deja el pago aprobado y el pedido «Confirmado».
- [ ] Cada cambio muestra quién, cuándo y por qué.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
