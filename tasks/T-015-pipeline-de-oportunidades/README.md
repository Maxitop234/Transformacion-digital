# T-015 · Pipeline de oportunidades

| Campo | Valor |
| --- | --- |
| Fase | 2 · CRM |
| Dimensión de la pauta | 2 |
| Depende de | [T-014](../T-014-gestion-de-clientes/README.md) |
| Desbloquea | Ninguna |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Reemplazar el seguimiento comercial informal por un embudo con etapas, condiciones e historial.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) §5, §6 y RN-09
- [`docs/API.md`](../../docs/API.md) §1.4
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) §4
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Vista en columnas por etapa con título, cliente, monto estimado y días en la etapa.
- Crear y editar oportunidades.
- Servicio de cambio de etapa que valida la condición de cada transición y escribe `historial_etapa`.
- Motivo obligatorio al pasar a «Perdido»; las oportunidades cerradas no se reabren.
- Vínculo automático: un pedido B2B de un cliente con oportunidad en «Propuesta» o «Negociación» la mueve a «Ganado» con el usuario `sistema`; el vendedor puede deshacerlo.

## Fuera de alcance

- Pronósticos de venta.

## Criterios de aceptación

- [ ] Test por cada condición de transición de `BUSINESS_RULES.md §5`.
- [ ] Test: no se puede mover una oportunidad cerrada.
- [ ] Test: perder sin motivo se rechaza.
- [ ] Test: una recompra B2B sin oportunidad abierta no crea una nueva.
- [ ] El vendedor ve solo su pipeline.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Recortable: mover la tarjeta puede ser un formulario simple en vez de arrastrar y soltar.
