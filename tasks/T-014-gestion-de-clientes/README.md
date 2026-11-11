# T-014 · Gestión de clientes y prospectos

| Campo | Valor |
| --- | --- |
| Fase | 2 · CRM |
| Dimensión de la pauta | 2 |
| Depende de | [T-013](../T-013-panel-crm/README.md) |
| Desbloquea | [T-015](../T-015-pipeline-de-oportunidades/README.md), [T-018](../T-018-tickets/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Centralizar la cartera: ficha del cliente, contactos, interacciones, validación de empresas y pedidos telefónicos.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) RN-02, RN-08, RN-10
- [`docs/API.md`](../../docs/API.md) §1.4
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) §1 y `interaccion`
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Listado de la cartera con búsqueda y filtros por tipo y estado.
- Ficha del cliente: encabezado, contactos, puntos de entrega, interacciones, pedidos con su canal y tickets.
- Ingreso manual de prospectos.
- Validar una empresa: asigna lista de precios y vendedor, registra `fecha_validacion` y activa la cuenta.
- Registrar interacciones, opcionalmente ligadas a una oportunidad.
- Pedido telefónico: el vendedor compra a nombre del cliente con los mismos precios y reglas, con `canal = crm` e `id_vendedor_registra` (RN-08).

## Fuera de alcance

- Reasignación de carteras (T-007).

## Criterios de aceptación

- [x] Tras la validación, la empresa ve su lista institucional y puede comprar.
- [x] Test: el pedido telefónico reutiliza `CrearPedido` y respeta mínimo, stock y precio de lista.
- [x] Test: un vendedor no puede validar ni abrir un cliente de otra cartera.
- [x] Cada interacción queda con fecha, tipo y vendedor.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-11):** 152 tests en verde (1 omitido, `EsquemaCoincideConDataModelTest`, que dentro del contenedor no ve `docs/`), 11 nuevos en `tests/Feature/Crm/GestionClientesTest.php`: búsqueda por nombre o RUT y filtros dentro de la cartera; ficha completa; prospecto con oportunidad e historial; RUT inválido o repetido; RN-02 validación (la empresa pasa a ver su lista institucional y a poder comprar) y sus rechazos (lista pública, sin RUT, ya activa → 409); RN-10 sin acceso a clientes de otra cartera (404 en ficha, validación, contactos, interacciones y pedido); interacción con fecha, tipo y vendedor; contacto principal; RN-08 pedido telefónico con mínimo, stock y precio de lista; y bloqueo para cuentas que no pueden comprar. Recorrido en el navegador con `vendedor2@cofrial.example`: filtro de pendientes, ficha de Hotel Demo Volcán Azul, interacción registrada y pedido telefónico N.º 177 al Hospital Demo Lago Sur (20 kg de CAR-001 a $11.170, OC pendiente). No se validó Volcán Azul en la demo, para conservar la empresa pendiente para la defensa; la validación está cubierta por tests.
- **Reutilización:** `CrearPedido` ahora tiene `__invoke` (carrito del portal) y `telefonico` (líneas del vendedor), con un único núcleo privado `registrar()`. `Precios` suma `perfilDeCliente()` para clientes sin cuenta. Los mensajes de `CrearPedido` y `SinStock` quedaron neutros («del cliente»), porque sirven a los dos canales.
- **Decisiones:** solo se valida una empresa `pendiente_validacion` con RUT (un prospecto manual pasa a pendiente cuando se registra en el portal, T-009). Al validar, el vendedor queda él mismo como responsable; solo el administrador elige otro. Un prospecto manual siempre abre su oportunidad en «Prospecto nuevo» (BUSINESS_RULES.md §5 y §6). El pedido telefónico de un B2C queda «Pendiente» y lo paga el cliente en el portal; si no paga en 30 minutos, se cancela como cualquier pedido B2C.
- **Fuera de alcance, sin hacer:** alta y edición de puntos de entrega desde el CRM (no estaba en el alcance; los crea el registro de T-009 o `/mis-direcciones` de T-012) y la edición de los datos del cliente.
