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

- [x] Un pedido creado en el portal aparece en el CRM sin recargar datos a mano.
- [x] Test: el vendedor no puede pasar un pedido a «En ruta»; el despachador sí.
- [x] Test: confirmar la orden de compra deja el pago aprobado y el pedido «Confirmado».
- [x] Cada cambio muestra quién, cuándo y por qué.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-11):** 164 tests en verde (1 omitido, `EsquemaCoincideConDataModelTest`, que dentro del contenedor no ve `docs/`), 12 nuevos en `tests/Feature/Crm/GestionPedidosTest.php`: pedido del portal visible en el CRM con su detalle; filtros por estado, canal, fecha y cliente con cartera (pedido ajeno 404); vendedor sin «En ruta» (403) y despachador sí; «Pendiente → Confirmado» bloqueado en el cambio genérico; transición inexistente 409; RN-05 OC confirmada (pago aprobado, pedido confirmado, historial), segunda vez 409, cartera ajena 404 y despachador 403; B2C sin OC; oportunidad en «Propuesta» ganada y recompra sin oportunidad; cancelación con motivo que libera la reserva; entrega (`salida`) y rechazo con motivo (`merma`); historial con quién, cuándo y por qué. Recorrido en el navegador con `vendedor2@cofrial.example`: el pedido telefónico 177 (Hospital Demo Lago Sur, OC `OC-TEL-DEMO-1`) quedó «Confirmado» con el pago aprobado y su historial.
- **Servicio de transiciones ampliado:** `disponibles()`, `exigeComentario()` y efectos de stock al entregar y rechazar, con el mismo criterio que los datos de demo (`liberacion` + `salida` o `merma`). T-017 debe crear despachos y paradas alrededor de estas mismas transiciones, no cambiar el estado por otro camino.
- **Decisiones:** el cambio genérico permite al despachador mover «Confirmado → En preparación → En ruta → Entregado/Rechazado» aunque aún no exista el armado de despachos (T-017); al llegar esa tarea, conviene exigir un despacho para «En preparación» y «En ruta». «Pendiente → Cancelado» sigue siendo solo del sistema o del cliente (BUSINESS_RULES.md §4): el vendedor cancela desde «Confirmado». El despachador ve todos los pedidos (necesita los puntos de entrega), pero no los enlaces a la ficha comercial.
- **Para T-015:** `GanarOportunidadConPedido` ya mueve la oportunidad a «Ganado» al confirmar la OC; el pipeline debe reconocer esa fila de `historial_etapa` del usuario `sistema`.
