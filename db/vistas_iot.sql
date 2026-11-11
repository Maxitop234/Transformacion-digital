-- =============================================================================
-- Cofrial · Vista de solo lectura para el simulador IoT (T-005)
--
-- Se ejecuta con el usuario maestro después de las migraciones (la vista
-- depende de las tablas). Idempotente. Normalmente lo llama db/aplicar.sh.
--
-- `iot_writer` no puede leer pedidos, productos ni clientes; esta vista le da
-- solo lo que necesita para cada camión (docs/API.md §2.3, RN-07):
--   - el rango de frío aplicable: el más exigente de la carga de su despacho
--     en ruta, o NULL si no tiene despacho en ruta (el simulador usa entonces
--     su rango por defecto configurable);
--   - si ya tiene una alerta de ruptura abierta, para no repetirla cada 30 s.
-- La vista corre con los privilegios de su dueño, así que no expone nada más.
-- =============================================================================

\set ON_ERROR_STOP on
\set QUIET on
\if :{?iot_user} \else \set iot_user iot_writer \endif

CREATE OR REPLACE VIEW iot_umbral_camion AS
SELECT
    c.camion_id,
    c.activo,
    rango.temp_min_c,
    rango.temp_max_c,
    EXISTS (
        SELECT 1
        FROM alerta a
        JOIN telemetria t ON t.id_telemetria = a.id_telemetria
        WHERE t.camion_id = c.camion_id
          AND a.tipo = 'ruptura_frio'
          AND a.estado <> 'resuelta'
    ) AS alerta_ruptura_abierta
FROM camion c
LEFT JOIN LATERAL (
    -- Rango más exigente: el mínimo más alto y el máximo más bajo de la carga.
    SELECT max(rc.temp_min_c) AS temp_min_c, min(rc.temp_max_c) AS temp_max_c
    FROM despacho d
    JOIN despacho_pedido dp ON dp.id_despacho = d.id_despacho
    JOIN detalle_pedido dt ON dt.id_pedido = dp.id_pedido
    JOIN producto p ON p.id_producto = dt.id_producto
    JOIN rango_conservacion rc ON rc.id_rango = p.id_rango
    WHERE d.camion_id = c.camion_id
      AND d.estado = 'en_ruta'
) AS rango ON true;

COMMENT ON VIEW iot_umbral_camion IS 'Umbral de frío y alerta abierta por camión para el simulador IoT (iot_writer). Ver db/vistas_iot.sql.';

GRANT SELECT ON iot_umbral_camion TO :"iot_user";

\echo 'vistas_iot.sql: vista iot_umbral_camion lista en' :DBNAME
