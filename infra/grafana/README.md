# infra/grafana · Business Intelligence

Configuración versionada de Grafana (provisioning): el datasource de PostgreSQL con el usuario `bi_reader` y los dashboards en JSON. Todavía vacío; se construye en [T-020](../../tasks/T-020-dashboard-bi/README.md).

- Los dashboards se editan en Grafana y se exportan aquí; no se configuran a mano en cada instancia.
- Las consultas usan las vistas `bi_*` definidas en [`db/`](../../db/README.md), no las tablas directamente.
- Los indicadores a mostrar están en [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md#7-indicadores-que-el-sistema-debe-poder-calcular).
