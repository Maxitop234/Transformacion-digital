# T-020 · Dashboard de Business Intelligence

| Campo | Valor |
| --- | --- |
| Fase | 4 · BI e IA |
| Dimensión de la pauta | 4 |
| Depende de | [T-004](../T-004-datos-semilla/README.md), [T-005](../T-005-roles-base-de-datos/README.md), [T-019](../T-019-simulador-iot/README.md) |
| Desbloquea | [T-027](../T-027-ensayo-de-defensa/README.md) |
| Componentes | `db`, `infra/grafana` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Mostrar en vivo, en un solo panel, las métricas comerciales cruzadas con las de la cadena de frío.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) §7
- [`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) §4.3
- [`docs/ADR/0010`](../../docs/ADR/README.md)
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Vistas `bi_*` en `db/` (ventas por día y canal, pedidos por estado, embudo, última posición de la flota, alertas, pedidos afectados por alertas), sin datos personales innecesarios, con permiso para `bi_reader`.
- Datasource de PostgreSQL y dashboards versionados en `infra/grafana` (provisioning).
- Paneles: ventas por día y por canal, pedidos por estado, conversión del pipeline, temperatura por camión, mapa de la flota, alertas abiertas, y la tabla de pedidos y clientes afectados por rupturas de frío.
- Actualización automática cada 30 segundos o menos.
- Grafana servido bajo `/bi` con su base interna en PostgreSQL.

## Fuera de alcance

- Reportes exportables.
- Alertas de Grafana.

## Criterios de aceptación

- [ ] Tras crear un pedido en el portal, el panel de ventas cambia sin intervención.
- [ ] Tras una ruptura de frío simulada, la alerta y los pedidos afectados aparecen en el dashboard.
- [ ] El datasource usa `bi_reader` y una consulta de escritura desde Grafana falla.
- [ ] Levantar Grafana desde cero carga el dashboard sin pasos manuales.
- [ ] Al menos un panel combina datos comerciales y de IoT en la misma consulta.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
