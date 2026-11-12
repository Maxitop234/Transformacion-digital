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

- [x] Test por cada condición de transición de `BUSINESS_RULES.md §5`.
- [x] Test: no se puede mover una oportunidad cerrada.
- [x] Test: perder sin motivo se rechaza.
- [x] Test: una recompra B2B sin oportunidad abierta no crea una nueva.
- [x] El vendedor ve solo su pipeline.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Recortable: mover la tarjeta puede ser un formulario simple en vez de arrastrar y soltar.
- Hecho con formularios en el detalle, sin arrastrar y soltar: cada destino muestra por qué aún no se puede. Tests en `apps/web/tests/Feature/Crm/PipelineOportunidadesTest.php` (`php artisan test --filter=PipelineOportunidadesTest`).
- No se saltan etapas: solo las transiciones de §5, de a una (409 si no).
- Ganar a mano exige elegir un pedido del cliente (sin oportunidad, no cancelado ni rechazado), porque la condición de §5 es «existe un pedido vinculado».
- «Deshacer» solo aplica si el último paso a «Ganado» lo hizo el usuario `sistema`. Desvincula los pedidos, vuelve a la etapa anterior (según el historial) y lo registra con el motivo. Agregué esta excepción en BUSINESS_RULES.md §5.
- El alcance del vendedor es por cartera del cliente (`visiblePara`, RN-10), no por `oportunidad.id_vendedor`. Reasignar la cartera no cambia el `id_vendedor` de las oportunidades existentes, pero el nuevo vendedor ya las ve.
- Quité la vista `crm/seccion-pendiente`, que quedó sin uso: todas las secciones del menú ya existen.
