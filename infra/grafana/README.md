# infra/grafana · Business Intelligence

Configuración versionada de Grafana OSS (provisioning, [ADR-0010](../../docs/ADR/0010-bi-grafana-e-ia-costo-cero.md)): el datasource de PostgreSQL con el usuario `bi_reader` y el dashboard en JSON. Construido en [T-020](../../tasks/T-020-dashboard-bi/README.md).

| Ruta | Contenido |
| --- | --- |
| [`provisioning/datasources/cofrial.yaml`](provisioning/datasources/cofrial.yaml) | Datasource `Cofrial BI` (uid `cofrial-bi`): base de negocio con `bi_reader`, valores tomados del entorno |
| [`provisioning/dashboards/cofrial.yaml`](provisioning/dashboards/cofrial.yaml) | Carga los JSON de `dashboards/` en la carpeta «Cofrial»; no se pueden guardar cambios desde la interfaz |
| [`dashboards/cofrial-bi.json`](dashboards/cofrial-bi.json) | Dashboard «Cofrial · Ventas y cadena de frío» (uid `cofrial-bi`), también página de inicio de `/bi` |
| `provisioning/plugins/`, `provisioning/alerting/` | Vacías a propósito: Grafana las busca al arrancar. Las alertas de Grafana están fuera de alcance |

## Cómo se carga

El servicio `grafana` de [`compose.dev.yml`](../docker/compose.dev.yml) monta `provisioning/` en `/etc/grafana/provisioning` y `dashboards/` en `/etc/grafana/dashboards`, y fija `GF_DASHBOARDS_DEFAULT_HOME_DASHBOARD_PATH`. Al arrancar sobre una base `grafana` vacía, Grafana crea su esquema y carga datasource y dashboard sin pasos manuales; el JSON se relee cada 30 s.

El datasource usa estas variables del `.env` (ninguna contraseña queda en el repositorio):

| Variable | Uso |
| --- | --- |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE` | Base de negocio (en RDS, su endpoint) |
| `BI_DB_USERNAME`, `BI_DB_PASSWORD` | Usuario de solo lectura `bi_reader` |
| `GF_DATABASE_SSL_MODE` | Modo TLS: el mismo que la base interna de Grafana, porque ambas están en la misma instancia (`disable` en local, `require` en RDS) |

En las EC2 web (T-024) hay que montar las mismas dos carpetas y la misma variable de inicio.

## Paneles

Se actualizan solos cada 30 s (las lecturas IoT llegan cada 30 s). Todas las consultas leen vistas `bi_*` de [`db/vistas_bi.sql`](../../db/vistas_bi.sql), nunca tablas.

| Panel | Vista | Indicador |
| --- | --- | --- |
| Ventas netas hoy, Pedidos hoy, Ventas netas 30 días | `bi_pedido`, `bi_venta_diaria` | Monto neto (sin IVA) sin pedidos cancelados ni rechazados; «hoy» en America/Santiago |
| Conversión del pipeline | `bi_conversion_pipeline` | Ganadas ÷ creadas (BUSINESS_RULES.md §7) |
| Rupturas abiertas | `bi_alerta` | Alertas `ruptura_frio` no resueltas |
| Pedidos afectados | `bi_pedido_afectado_alerta` | Pedidos en despachos con una ruptura sin resolver (cruce IoT y ventas) |
| Ventas netas por día y canal | `bi_venta_diaria` | Barras apiladas por canal, últimos 30 días |
| Ventas por canal (30 días) | `bi_venta_diaria` | Participación por canal |
| Pedidos por estado | `bi_pedido_por_estado` | Pedidos en cada estado actual |
| Embudo del pipeline | `bi_embudo_pipeline` | Oportunidades que alcanzaron cada etapa |
| Mapa de la flota | `bi_flota_posicion` | Última posición; rojo si hay ruptura |
| Temperatura de cámara por camión | `bi_telemetria` | Serie por camión (variable «Camión»), con el rango por defecto 0 a 5 °C |
| Pedidos y clientes afectados por rupturas de frío | `bi_pedido_afectado_alerta` | Alerta, camión, despacho, parada, pedido, cliente y monto |
| Alertas abiertas | `bi_alerta` | Alertas de frío y combustible sin resolver |
| Estado de la flota | `bi_flota_posicion` | Última lectura, combustible y antigüedad de cada camión |

El mapa usa WebGL: en un navegador sin WebGL (algunos navegadores embebidos o sin aceleración) queda en blanco, aunque los datos llegan.

## Cambiar el dashboard

1. Edita en Grafana una copia («Save as» en otra carpeta) o directamente el JSON.
2. Exporta el JSON (Share → Export, sin «Export for sharing externally») y reemplaza `dashboards/cofrial-bi.json`, conservando `uid`, `editable: false` y el datasource `cofrial-bi`.
3. Prueba cada consulta como `bi_reader`: si necesitas otra columna, agrégala a una vista de `db/vistas_bi.sql` (sin datos personales) y vuelve a correr `db/aplicar.sh`.

## Seguridad

- `bi_reader` solo tiene `SELECT` sobre las vistas `bi_*`, trabaja en transacciones de solo lectura y con `statement_timeout` de 15 s ([`db/roles.sql`](../../db/roles.sql)). Una consulta de escritura escrita en un panel falla en la base.
- Las vistas no exponen RUT, correos, teléfonos, direcciones ni conductores; los clientes B2C aparecen como «Cliente B2C #id».
- Grafana tiene su propio inicio de sesión (`GF_SECURITY_ADMIN_*`); no hay acceso anónimo.
