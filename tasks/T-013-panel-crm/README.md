# T-013 · Panel del CRM

| Campo | Valor |
| --- | --- |
| Fase | 2 · CRM |
| Dimensión de la pauta | 2 |
| Depende de | [T-006](../T-006-autenticacion-y-roles/README.md) |
| Desbloquea | [T-014](../T-014-gestion-de-clientes/README.md), [T-016](../T-016-gestion-de-pedidos/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Dar al equipo de Cofrial una pantalla de inicio con lo que requiere su atención.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) §1, RN-10 y §7
- [`docs/API.md`](../../docs/API.md) §1.4
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Layout del CRM con menú por rol.
- Indicadores: oportunidades abiertas, conversión de los últimos 90 días, pedidos por canal y tickets abiertos.
- Lista de empresas pendientes de validación.
- Oportunidades sin interacciones en 15 días o más.
- Alertas de frío abiertas que afectan a la cartera, cruzando alerta, despacho, pedido y cliente.

## Fuera de alcance

- Las pantallas de detalle a las que enlaza (T-014 a T-018).

## Criterios de aceptación

- [ ] El vendedor ve indicadores solo de su cartera; el administrador, de todo el equipo (con test).
- [ ] El despachador no ve bloques comerciales.
- [ ] Una alerta abierta sobre un camión en ruta aparece con los clientes afectados.
- [ ] Los umbrales de 15 y 90 días se leen de configuración.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
