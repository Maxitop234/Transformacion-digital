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

- [x] Tras crear un pedido en el portal, el panel de ventas cambia sin intervención.
- [x] Tras una ruptura de frío simulada, la alerta y los pedidos afectados aparecen en el dashboard.
- [x] El datasource usa `bi_reader` y una consulta de escritura desde Grafana falla.
- [x] Levantar Grafana desde cero carga el dashboard sin pasos manuales.
- [x] Al menos un panel combina datos comerciales y de IoT en la misma consulta.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Entregables.** Vistas en [`db/vistas_bi.sql`](../../db/vistas_bi.sql) (`bi_pedido`, `bi_venta_diaria`, `bi_pedido_por_estado`, `bi_embudo_pipeline`, `bi_conversion_pipeline`, `bi_telemetria`, `bi_flota_posicion`, `bi_alerta`, `bi_pedido_afectado_alerta`), aplicadas por `db/aplicar.sh`. Datasource, provider y dashboard en [`infra/grafana`](../../infra/grafana/README.md), montados por el servicio `grafana` de `compose.dev.yml`. El dashboard es la página de inicio de `/bi` y se refresca cada 30 s.
- **Decisiones.**
  - Pedido afectado: parada aún no entregada (`fecha_entrega` nula o posterior) de un despacho `en_ruta`/`finalizado` del camión cuyo intervalo `salida_real`–`llegada_real` contiene la lectura de la alerta. Es más preciso que «despacho en ruta ahora» y sigue valiendo después de que el despacho termina.
  - Sin datos personales: los clientes B2C se muestran como «Cliente B2C #id»; de los B2B, la razón social. `bi_telemetria` no trae posición; la posición solo sale como última posición de la flota. Nada del conductor.
  - Montos netos (sin IVA), porque el IVA es configuración de Laravel y la base no lo conoce. «Hoy» y los días se calculan en America/Santiago.
  - `bi_reader` queda con `default_transaction_read_only = on` y `statement_timeout = 15s` (`db/roles.sql`), además de tener solo `SELECT` en las vistas. `pruebas_permisos.sh` prueba los permisos con la sesión en lectura-escritura y aparte comprueba el modo de solo lectura.
  - El datasource reutiliza `GF_DATABASE_SSL_MODE` (misma instancia que la base interna de Grafana) en vez de `DB_SSLMODE`: es la misma instancia, ya trae valores que el datasource de Grafana entiende (`disable`/`require`) y no hace falta una variable nueva; `DB_SSLMODE=prefer` no está entre los modos que ofrece el datasource.
- **Verificación (2026-11-11)** en un proyecto Docker aislado (`docker compose -p cofrial-t020`, puertos 8091/5442) con base nueva, semilla de demo y las cinco réplicas IoT:
  - `pruebas_permisos.sh`: 167 comprobaciones correctas, con `aplicar.sh` corrido tres veces seguidas (idempotente).
  - Las 15 consultas del dashboard responden por `/bi/api/ds/query`.
  - Pedido nuevo con canal `portal` (insertado como `app_rw`, porque el checkout es T-011): «Ventas netas hoy / Pedidos hoy» pasó de `$0 | 0` a `$125K | 1` en el navegador sin recargar.
  - Despacho en ruta de CAM-011 con ese pedido + `docker kill -s USR1 cofrial-t020-iot-2-1`: la alerta apareció en «Alertas abiertas» y el pedido y su cliente en «Pedidos y clientes afectados», sin recargar.
  - Desde Grafana como `bi_reader`: `INSERT`, `DELETE` y `CREATE TABLE` fallan con 25006 (solo lectura); con `SET TRANSACTION READ WRITE`, `UPDATE alerta` falla con 42501; `SELECT rut FROM cliente` falla con 42501.
  - Grafana desde cero (contenedor eliminado y base `grafana` borrada y recreada por `aplicar.sh`): arrancó, migró su esquema, cargó datasource (health OK) y dashboard provisionado sin pasos manuales.
  - El mapa usa WebGL: en el navegador embebido sin WebGL queda en blanco; se comprobó en Chrome que muestra los 50 camiones con los de ruptura en rojo.
- **Para tu entorno local existente:** al recrear `web` (`docker compose -f infra/docker/compose.dev.yml up -d`) se aplican las vistas, y al recrear `grafana` toma el provisioning. No hace falta borrar volúmenes.
- **Pendientes fuera de alcance.**
  - T-024: `compose.prod.yml` debe montar `infra/grafana/provisioning` y `infra/grafana/dashboards` y fijar `GF_DASHBOARDS_DEFAULT_HOME_DASHBOARD_PATH`, igual que `compose.dev.yml`; con RDS, `GF_DATABASE_SSL_MODE=require`.
  - T-017: hoy los despachos solo nacen en la semilla (todos `finalizado`); hasta que exista la logística del CRM, la demo de pedidos afectados requiere un despacho `en_ruta` creado a mano.
  - Con muchas semanas de telemetría, el panel de temperatura recorre `telemetria` por `fecha_hora`, que no tiene índice propio (solo `(camion_id, fecha_hora DESC)`). Si se vuelve lento, evaluar un índice por `fecha_hora` (migración) junto con la purga de retención (DATA_MODEL.md, punto abierto 5).
  - Con `IOT_PROB_RUPTURA_FRIO=0.02` aparecen varias rupturas por minuto y la tabla de alertas se llena rápido; para la defensa conviene bajarla.
