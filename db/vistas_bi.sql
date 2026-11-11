-- =============================================================================
-- Cofrial · Vistas de Business Intelligence para Grafana (T-020)
--
-- Se ejecuta con el usuario maestro después de las migraciones (las vistas
-- dependen de las tablas). Idempotente. Normalmente lo llama db/aplicar.sh.
--
-- `bi_reader` solo puede leer estas vistas (db/roles.sql); no tiene permiso
-- sobre ninguna tabla. Las vistas corren con los privilegios de su dueño, así
-- que exponen únicamente las columnas que el dashboard necesita:
--   - sin RUT, correos, teléfonos, direcciones ni datos del conductor;
--   - los clientes B2C (personas) aparecen como «Cliente B2C #id»; de los B2B
--     se muestra la razón social, que identifica a una empresa.
--
-- Fechas en UTC (TIMESTAMPTZ); el «día» comercial se calcula en la zona de
-- negocio America/Santiago (AGENTS.md, Entorno).
--
-- Se recrean todas dentro de una transacción: Grafana nunca ve una vista a
-- medio crear, y una vista bi_* que ya no exista en este archivo desaparece.
-- Indicadores de referencia: docs/BUSINESS_RULES.md §7.
-- =============================================================================

\set ON_ERROR_STOP on
\set QUIET on
\if :{?bi_user} \else \set bi_user bi_reader \endif

BEGIN;
SET LOCAL client_min_messages = warning;

SELECT format('DROP VIEW IF EXISTS %I CASCADE', viewname)
FROM pg_views
WHERE schemaname = 'public' AND viewname LIKE 'bi\_%' \gexec

-- -----------------------------------------------------------------------------
-- Ventas
-- -----------------------------------------------------------------------------

-- Un pedido por fila con su monto neto (sin IVA; el total no se guarda,
-- DATA_MODEL.md `pedido`) y su estado actual.
CREATE VIEW bi_pedido AS
SELECT
    p.id_pedido,
    p.fecha_pedido,
    (p.fecha_pedido AT TIME ZONE 'America/Santiago')::date AS dia,
    p.canal,
    tc.codigo AS tipo_cliente,
    c.id_cliente,
    CASE WHEN tc.codigo = 'B2B' THEN c.nombre_razon_social
         ELSE 'Cliente B2C #' || c.id_cliente END AS cliente,
    co.nombre AS comuna,
    e.nombre AS estado,
    e.orden AS estado_orden,
    e.es_final,
    e.nombre IN ('Cancelado', 'Rechazado') AS anulado,
    p.fecha_entrega_solicitada,
    coalesce(m.monto_neto, 0)::numeric(14, 2) AS monto_neto,
    coalesce(m.lineas, 0) AS lineas
FROM pedido p
JOIN estado_pedido e ON e.id_estado_pedido = p.id_estado_pedido
JOIN direccion_despacho dd ON dd.id_direccion = p.id_direccion
JOIN comuna co ON co.id_comuna = dd.id_comuna
JOIN cliente c ON c.id_cliente = dd.id_cliente
JOIN tipo_cliente tc ON tc.id_tipo_cliente = c.id_tipo_cliente
LEFT JOIN LATERAL (
    SELECT sum(dp.cantidad * dp.precio_unitario_pactado) AS monto_neto, count(*) AS lineas
    FROM detalle_pedido dp
    WHERE dp.id_pedido = p.id_pedido
) AS m ON true;

COMMENT ON VIEW bi_pedido IS 'Pedido con monto neto, canal, tipo de cliente y estado actual, sin datos personales (T-020).';

-- Ventas por día comercial, canal y tipo de cliente. No cuenta pedidos
-- cancelados ni rechazados. `tiempo` es el inicio del día en Santiago, para
-- las series de tiempo de Grafana.
CREATE VIEW bi_venta_diaria AS
SELECT
    dia,
    (dia::timestamp AT TIME ZONE 'America/Santiago') AS tiempo,
    canal,
    tipo_cliente,
    count(*) AS pedidos,
    sum(monto_neto)::numeric(14, 2) AS monto_neto
FROM bi_pedido
WHERE NOT anulado
GROUP BY dia, canal, tipo_cliente;

COMMENT ON VIEW bi_venta_diaria IS 'Pedidos y monto neto por día (America/Santiago), canal y tipo de cliente; sin anulados (T-020).';

-- Pedidos en cada estado del catálogo, incluidos los estados sin pedidos.
CREATE VIEW bi_pedido_por_estado AS
SELECT
    e.orden,
    e.nombre AS estado,
    e.es_final,
    count(p.id_pedido) AS pedidos
FROM estado_pedido e
LEFT JOIN pedido p ON p.id_estado_pedido = e.id_estado_pedido
GROUP BY e.id_estado_pedido, e.orden, e.nombre, e.es_final;

COMMENT ON VIEW bi_pedido_por_estado IS 'Cantidad de pedidos por estado actual (T-020).';

-- -----------------------------------------------------------------------------
-- Pipeline comercial (CRM)
-- -----------------------------------------------------------------------------

-- Embudo por etapa:
--   - `oportunidades` / `monto_estimado`: las que están hoy en la etapa;
--   - `alcanzaron`: las que llegaron al menos a la etapa (por su historial o
--     su etapa actual). En «Ganado» son las ganadas; en «Perdido», las
--     perdidas (no es una etapa posterior a las demás).
CREATE VIEW bi_embudo_pipeline AS
SELECT
    ep.orden,
    ep.nombre AS etapa,
    ep.es_cierre,
    ep.es_ganada,
    (SELECT count(*) FROM oportunidad o WHERE o.id_etapa = ep.id_etapa) AS oportunidades,
    (SELECT coalesce(sum(o.monto_estimado), 0) FROM oportunidad o WHERE o.id_etapa = ep.id_etapa)::numeric(14, 2) AS monto_estimado,
    (
        SELECT count(*)
        FROM oportunidad o
        WHERE EXISTS (
            SELECT 1
            FROM (
                SELECT o.id_etapa
                UNION ALL
                SELECT h.id_etapa FROM historial_etapa h WHERE h.id_oportunidad = o.id_oportunidad
            ) AS paso
            JOIN etapa_pipeline e2 ON e2.id_etapa = paso.id_etapa
            WHERE CASE
                WHEN ep.es_cierre AND NOT ep.es_ganada THEN e2.id_etapa = ep.id_etapa
                ELSE e2.es_ganada OR (NOT e2.es_cierre AND e2.orden >= ep.orden)
            END
        )
    ) AS alcanzaron
FROM etapa_pipeline ep;

COMMENT ON VIEW bi_embudo_pipeline IS 'Embudo del pipeline: oportunidades actuales y que alcanzaron cada etapa (T-020).';

-- Tasa de conversión (BUSINESS_RULES.md §7): ganadas ÷ creadas.
CREATE VIEW bi_conversion_pipeline AS
SELECT
    count(*) AS creadas,
    count(*) FILTER (WHERE ep.es_ganada) AS ganadas,
    count(*) FILTER (WHERE ep.es_cierre AND NOT ep.es_ganada) AS perdidas,
    count(*) FILTER (WHERE NOT ep.es_cierre) AS abiertas,
    round(count(*) FILTER (WHERE ep.es_ganada)::numeric / nullif(count(*), 0), 4) AS tasa_conversion,
    coalesce(sum(o.monto_estimado) FILTER (WHERE ep.es_ganada), 0)::numeric(14, 2) AS monto_ganado
FROM oportunidad o
JOIN etapa_pipeline ep ON ep.id_etapa = o.id_etapa;

COMMENT ON VIEW bi_conversion_pipeline IS 'Oportunidades creadas, ganadas, perdidas y tasa de conversión (T-020).';

-- -----------------------------------------------------------------------------
-- Flota y cadena de frío (IoT)
-- -----------------------------------------------------------------------------

-- Lecturas de telemetría sin posición (para series de temperatura y
-- combustible). La posición solo se expone como última posición de la flota.
CREATE VIEW bi_telemetria AS
SELECT
    t.camion_id,
    t.fecha_hora,
    t.temp_camara,
    t.combustible_pct,
    t.alerta_ruptura_frio
FROM telemetria t;

COMMENT ON VIEW bi_telemetria IS 'Lecturas de temperatura y combustible por camión, sin posición (T-020).';

-- Última lectura de cada camión, su despacho en ruta y si tiene una alerta de
-- ruptura sin resolver. Sin datos del conductor.
CREATE VIEW bi_flota_posicion AS
SELECT
    c.camion_id,
    c.patente,
    c.activo,
    t.fecha_hora,
    t.temp_camara,
    t.lat,
    t."long",
    t.combustible_pct,
    t.alerta_ruptura_frio,
    d.id_despacho,
    d.id_despacho IS NOT NULL AS en_ruta,
    EXISTS (
        SELECT 1
        FROM alerta a
        JOIN telemetria ta ON ta.id_telemetria = a.id_telemetria
        WHERE ta.camion_id = c.camion_id
          AND a.tipo = 'ruptura_frio'
          AND a.estado <> 'resuelta'
    ) AS alerta_abierta,
    extract(epoch FROM now() - t.fecha_hora)::integer AS segundos_desde_lectura
FROM camion c
LEFT JOIN LATERAL (
    SELECT tl.fecha_hora, tl.temp_camara, tl.lat, tl."long", tl.combustible_pct, tl.alerta_ruptura_frio
    FROM telemetria tl
    WHERE tl.camion_id = c.camion_id
    ORDER BY tl.fecha_hora DESC
    LIMIT 1
) AS t ON true
LEFT JOIN LATERAL (
    SELECT ds.id_despacho
    FROM despacho ds
    WHERE ds.camion_id = c.camion_id AND ds.estado = 'en_ruta'
    ORDER BY ds.salida_real DESC NULLS LAST
    LIMIT 1
) AS d ON true;

COMMENT ON VIEW bi_flota_posicion IS 'Última posición, temperatura y combustible de cada camión, con despacho en ruta y alerta abierta (T-020).';

-- Pedidos y clientes afectados por cada alerta de ruptura de frío
-- (BUSINESS_RULES.md §7; DATA_MODEL.md `alerta`). Cruza datos de IoT y
-- comerciales: alerta → lectura → despacho del camión que estaba en ruta en
-- el momento de la lectura → paradas aún no entregadas a esa hora → pedido.
CREATE VIEW bi_pedido_afectado_alerta AS
SELECT
    a.id_alerta,
    a.severidad,
    a.estado AS estado_alerta,
    a.estado <> 'resuelta' AS alerta_abierta,
    t.fecha_hora AS fecha_alerta,
    t.camion_id,
    t.temp_camara,
    d.id_despacho,
    d.estado AS estado_despacho,
    dp.orden_parada,
    dp.estado_entrega,
    p.id_pedido,
    p.estado AS estado_pedido,
    p.fecha_entrega_solicitada,
    p.id_cliente,
    p.cliente,
    p.tipo_cliente,
    p.comuna,
    p.monto_neto
FROM alerta a
JOIN telemetria t ON t.id_telemetria = a.id_telemetria
JOIN despacho d
  ON d.camion_id = t.camion_id
 AND d.estado IN ('en_ruta', 'finalizado')
 AND coalesce(d.salida_real, '-infinity') <= t.fecha_hora
 AND (d.llegada_real IS NULL OR d.llegada_real >= t.fecha_hora)
JOIN despacho_pedido dp
  ON dp.id_despacho = d.id_despacho
 AND (dp.fecha_entrega IS NULL OR dp.fecha_entrega >= t.fecha_hora)
JOIN bi_pedido p ON p.id_pedido = dp.id_pedido
WHERE a.tipo = 'ruptura_frio';

COMMENT ON VIEW bi_pedido_afectado_alerta IS 'Pedidos y clientes en despachos con ruptura de frío: cruce IoT y comercial (T-020).';

-- Alertas con la lectura que las originó, minutos hasta resolver
-- (BUSINESS_RULES.md §7) y cuántos pedidos afectan.
CREATE VIEW bi_alerta AS
SELECT
    a.id_alerta,
    a.tipo,
    a.severidad,
    a.estado,
    a.estado <> 'resuelta' AS abierta,
    t.camion_id,
    t.fecha_hora,
    t.temp_camara,
    t.combustible_pct,
    a.fecha_resolucion,
    round((extract(epoch FROM a.fecha_resolucion - t.fecha_hora) / 60)::numeric, 1) AS minutos_hasta_resolver,
    coalesce(af.pedidos, 0) AS pedidos_afectados,
    coalesce(af.clientes, 0) AS clientes_afectados,
    coalesce(af.monto_neto, 0)::numeric(14, 2) AS monto_afectado
FROM alerta a
JOIN telemetria t ON t.id_telemetria = a.id_telemetria
LEFT JOIN LATERAL (
    SELECT count(DISTINCT x.id_pedido) AS pedidos, count(DISTINCT x.id_cliente) AS clientes, sum(x.monto_neto) AS monto_neto
    FROM bi_pedido_afectado_alerta x
    WHERE x.id_alerta = a.id_alerta
) AS af ON true;

COMMENT ON VIEW bi_alerta IS 'Alertas de frío y combustible con su lectura, tiempo de resolución y pedidos afectados (T-020).';

-- -----------------------------------------------------------------------------
-- Permisos: bi_reader lee las vistas y nada más (db/roles.sql §6).
-- -----------------------------------------------------------------------------
SELECT format('GRANT SELECT ON %I TO %I', viewname, :'bi_user')
FROM pg_views
WHERE schemaname = 'public' AND viewname LIKE 'bi\_%' \gexec

COMMIT;

\echo 'vistas_bi.sql: vistas bi_* listas en' :DBNAME
