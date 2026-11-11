# T-017 · Despachos, flota y alertas de frío

| Campo | Valor |
| --- | --- |
| Fase | 2 · CRM |
| Dimensión de la pauta | 2, 3 |
| Depende de | [T-016](../T-016-gestion-de-pedidos/README.md) |
| Desbloquea | Ninguna |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Conectar el pedido con el camión y la telemetría, para que una ruptura de frío se gestione antes de la entrega.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) RN-07 y §4
- [`docs/API.md`](../../docs/API.md) §1.4
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) §5
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Crear un despacho con camión, conductor, fecha y pedidos confirmados, ordenados por parada.
- Validación: todos los productos del despacho comparten rango de conservación.
- Registrar la salida: los pedidos pasan a «En ruta».
- Resultado por parada: entregado (genera la salida de inventario), rechazado (crea un ticket) o fallido (el pedido vuelve a «Confirmado»).
- Vista de flota con la última lectura de cada camión.
- Alertas: listado, pedidos y clientes afectados, y cierre con acción correctiva obligatoria.
- Notificación al despachador y al vendedor de los clientes afectados cuando aparece una alerta nueva.

## Fuera de alcance

- Optimización de rutas.
- Generación de telemetría (T-019).

## Criterios de aceptación

- [ ] Test: no se puede crear un despacho que mezcle refrigerados y congelados.
- [ ] Test: la entrega descuenta `stock_disponible` y `stock_reservado` y registra el movimiento de salida.
- [ ] Test: una entrega fallida devuelve el pedido a «Confirmado» y permite asignarlo a otro despacho.
- [ ] Test: resolver una alerta sin acción correctiva se rechaza.
- [ ] El vendedor solo ve alertas de su cartera.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
