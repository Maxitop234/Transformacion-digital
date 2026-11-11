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

- [x] Agregar dos veces el mismo producto suma cantidades en una sola fila.
- [x] Test: agregar al carrito no modifica `stock_reservado`.
- [x] Test: el total del carrito cambia si cambia el precio de la lista.
- [x] Test: un carrito con 24 horas sin actividad pasa a `abandonado`.
- [x] Un cliente no puede ver ni modificar el carrito de otro.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-11):** 114 tests en verde (1 omitido, `EsquemaCoincideConDataModelTest`, que dentro del contenedor no ve `docs/`), 21 nuevos en `tests/Feature/Portal/CarritoTest.php`: suma en una sola fila, RN-04 sin tocar `stock_reservado`, ajuste y aviso al superar el disponible, RN-01 total que cambia con el precio de la lista, neto/IVA/total para B2B y B2C, RN-04 abandono a las 24 h y renovación de actividad, carrito ajeno, solo cuentas de cliente y RN-02 empresa pendiente. Recorrido en el navegador con `compras@clinica-araucaria.example`: CON-002 × 3 y CAR-001 × 2,5 kg dan neto $33.565, IVA $6.377 y total $39.942; pedir 100.000 ajusta a 327 con aviso; quitar deja el carrito vacío; `stock_reservado` sin cambios.
- **Servicio:** `App\Domain\Ventas\Carritos`. T-011 debe tomar el carrito con `Carritos::activo()` o `resumen()` (líneas con precio vigente y totales), copiar el precio a `detalle_pedido.precio_unitario_pactado`, reservar stock y pasar el carrito a `convertido` dentro de la misma transacción. Las líneas sin precio (`LineaCarrito::tienePrecio()`) o que exceden el disponible (`excedeDisponible()`) deben bloquear el checkout.
- **Formularios en vez de Livewire:** ADR-0013 menciona Livewire para el carrito, pero el contrato de `API.md` §1.3 son rutas `POST`/`PATCH`/`DELETE`, así que se implementó con formularios Blade sobre esas rutas. Si más adelante se quiere edición sin recargar, un componente Livewire puede llamar al mismo servicio.
- **Planificador:** `carritos:marcar-abandonados` cada 15 minutos con `onOneServer()` (lock en la caché de la base, hay dos EC2). En local corre en el nuevo servicio `scheduler` de `compose.dev.yml`; **T-024** debe incluir `php artisan schedule:work` (o cron con `schedule:run`) en `compose.prod.yml`, como ya anticipa `infra/docker/README.md`. T-011 puede registrar ahí su cancelación de pedidos sin pago.
- **Decisiones:** el abandono no toca `actualizado_en` (conserva la última actividad real); un cliente que vuelve tras el abandono empieza un carrito nuevo. Las cantidades en `kg` admiten dos decimales y en `un`/`caja` solo enteros. Una empresa pendiente de validación ve el carrito vacío, pero no puede agregar (RN-02).
- **Fuera de alcance, sin hacer:** contador de productos en el enlace «Carrito» del menú y respuestas JSON de estas rutas (hoy responden con redirección y mensajes de sesión).
