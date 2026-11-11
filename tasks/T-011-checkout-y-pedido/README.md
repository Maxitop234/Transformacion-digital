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

- [x] Test: no se puede confirmar bajo el pedido mínimo (B2C y B2B).
- [x] Test: dos compras simultáneas del último stock no generan sobreventa.
- [x] Test: el `precio_unitario_pactado` coincide con la lista del cliente al momento de comprar.
- [x] Test: un pago aprobado deja el pedido en «Confirmado» con su fila de historial.
- [x] Test: un pedido B2C sin pago se cancela a los 30 minutos y `stock_reservado` vuelve a su valor.
- [x] Una transición inválida responde 409.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-11):** 134 tests en verde (1 omitido, `EsquemaCoincideConDataModelTest`, que dentro del contenedor no ve `docs/`). Nuevos: `tests/Feature/Portal/PedidoTest.php` (19: RN-03 mínimo B2C y B2B, RN-01 precio pactado, RN-04 reserva y movimientos, sin stock, RN-06 fecha y punto de entrega, RN-05 pago aprobado/rechazado/B2B con OC, pedido ajeno 404, plazo vencido 409, RN-04 cancelación a los 30 min y lo que no cancela, transición inválida 409, estado final, rol y cartera) y `PedidoConcurrenciaTest.php` (dos conexiones reales: la segunda compra espera el bloqueo y después recibe `SinStock`). Recorrido en el navegador: `compras@clinica-araucaria.example` creó el pedido 175 (15 kg de CAR-001, total $199.385) pendiente con `OC-DEMO-0001`; `camila.rojas@correo.example` creó el 176 ($52.312) y el pago simulado aprobado lo dejó «Confirmado» con historial Pendiente → Confirmado.
- **Servicios:** `App\Domain\Ventas\{CrearPedido, TransicionesPedido, PagarPedido, CancelarPedidosSinPago, CalendarioEntregas, Montos}` y `App\Domain\Catalogo\ReservasStock`. Los detalles están en `apps/web/README.md` («Pedidos y pago»).
- **Para T-016:** la validación de la OC es `TransicionesPedido::cambiar($pedido, 'Confirmado', $vendedor, ...)` (ya valida la cartera, RN-10). Falta pasar el `pago` `credito_30d` de `pendiente` a `aprobado` en esa misma acción y, en la primera compra de una empresa, enlazar `id_oportunidad`. El pedido telefónico (canal `crm`) debería reutilizar `CrearPedido` con una variante que reciba las líneas y el vendedor que lo registra.
- **Para T-012:** la cancelación del cliente es `cambiar($pedido, 'Cancelado', $cliente)`; ya libera la reserva.
- **Para T-017:** `TransicionesPedido` aún no aplica efectos de stock al entregar (`salida` y `liberacion`) ni al rechazar; agregarlos ahí, no en los controladores.
- **Decisiones:** el mínimo B2C se mide sobre el total con IVA y el B2B sobre el neto (comentario de `config/negocio.php`). Días hábiles: lunes a sábado, sin feriados (mismo criterio que los datos de demo). La pasarela registra un `pago` por intento con referencia `SIM-…`; el método (tarjeta o transferencia) se elige en la pasarela, no en el checkout. Tras crear un pedido B2B el cliente vuelve al inicio con el número del pedido, porque «Mis pedidos» llega en T-012.
- **Planificador:** `pedidos:cancelar-sin-pago` corre cada minuto en el servicio `scheduler`; T-024 debe levantar el planificador en producción.
