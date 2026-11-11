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

- [x] Un ticket nuevo nace `sin_clasificar` y con `clasificado_por_ia = false`.
- [x] El ticket aparece en la ficha del cliente y en el detalle del pedido.
- [x] Test: un cliente no puede crear un ticket sobre un pedido ajeno.
- [x] Cerrar un ticket registra `cerrado_en`.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Tests en `apps/web/tests/Feature/Crm/TicketsTest.php` (`php artisan test --filter=TicketsTest`).
- El ticket automático de carga rechazada (T-017) pasa ahora por `CrearTicket`, pero sigue naciendo `reclamo`/`alta`, como define API.md §1.4. Es la única excepción a «nace `sin_clasificar`».
- **Para T-021:** escuchar `App\Events\TicketCreado` con un listener encolado, releer el ticket y clasificar solo si sigue `sin_clasificar`. Al guardar el resultado de la IA, poner `clasificado_por_ia = true`; si un ejecutivo lo reclasifica, `GestionarTicket` lo deja en `false`.
- La categoría `calidad` del ejemplo de API.md §3.1 no está en el `CHECK` de la base. T-021 debe usar las categorías de DATA_MODEL.md o pasar por un cambio de modelo.
- Un ticket cerrado es final (409). Resolver o cerrar exige categoría.
- El vendedor solo puede tomar un ticket para sí; reasignar a otro lo hace el administrador.
- Agregué `GET /tickets` (formulario e historial del cliente) y `GET /crm/tickets/{id}`, ya documentados en API.md.
