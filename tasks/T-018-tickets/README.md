# T-018 · Tickets de requerimientos

| Campo | Valor |
| --- | --- |
| Fase | 2 · CRM |
| Dimensión de la pauta | 2, 5 |
| Depende de | [T-012](../T-012-seguimiento-de-pedidos/README.md), [T-014](../T-014-gestion-de-clientes/README.md) |
| Desbloquea | [T-021](../T-021-integracion-ia/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Registrar los requerimientos y reclamos de clientes en un solo lugar, listos para ser clasificados.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) RN-11
- [`docs/API.md`](../../docs/API.md) §1.3 y §1.4
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) `ticket`
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Creación desde el portal, opcionalmente ligada a un pedido.
- Creación automática cuando una carga es rechazada.
- Bandeja en el CRM con filtros por categoría, prioridad y estado.
- Clasificación y reclasificación manual, asignación y cierre.
- Evento «ticket creado» al que se enganchará la clasificación con IA (T-021).

## Fuera de alcance

- La llamada a la IA (T-021).

## Criterios de aceptación

- [ ] Un ticket nuevo nace `sin_clasificar` y con `clasificado_por_ia = false`.
- [ ] El ticket aparece en la ficha del cliente y en el detalle del pedido.
- [ ] Test: un cliente no puede crear un ticket sobre un pedido ajeno.
- [ ] Cerrar un ticket registra `cerrado_en`.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
