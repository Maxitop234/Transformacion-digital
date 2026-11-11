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
- Pedido telefónico: el vendedor compra a nombre del cliente con los mismos precios y reglas, con `canal = telefono` e `id_vendedor_registra`.

## Fuera de alcance

- Reasignación de carteras (T-007).

## Criterios de aceptación

- [ ] Tras la validación, la empresa ve su lista institucional y puede comprar.
- [ ] Test: el pedido telefónico reutiliza `CrearPedido` y respeta mínimo, stock y precio de lista.
- [ ] Test: un vendedor no puede validar ni abrir un cliente de otra cartera.
- [ ] Cada interacción queda con fecha, tipo y vendedor.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
