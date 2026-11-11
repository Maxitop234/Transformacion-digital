"""Acceso a PostgreSQL con el usuario `iot_writer` (solo INSERT y la vista iot_umbral_camion)."""

from __future__ import annotations

from collections.abc import Iterable, Mapping

import psycopg

from .camion import Lectura
from .config import Configuracion
from .flota import Umbral

SQL_UMBRALES = """
SELECT camion_id, activo, temp_min_c, temp_max_c, alerta_ruptura_abierta
FROM iot_umbral_camion
WHERE camion_id = ANY(%s)
"""

SQL_LECTURA = """
INSERT INTO telemetria
    (camion_id, fecha_hora, temp_camara, lat, "long", combustible_pct, alerta_ruptura_frio)
VALUES (%s, now(), %s, %s, %s, %s, %s)
RETURNING id_telemetria
"""

SQL_ALERTA = """
INSERT INTO alerta (id_telemetria, tipo, severidad, estado)
VALUES (%s, 'ruptura_frio', 'alta', 'abierta')
RETURNING id_alerta
"""


def conectar(cfg: Configuracion) -> psycopg.Connection:
    return psycopg.connect(
        host=cfg.db_host,
        port=cfg.db_port,
        dbname=cfg.db_nombre,
        user=cfg.db_usuario,
        password=cfg.db_clave,
        sslmode=cfg.db_sslmode,
        connect_timeout=cfg.db_timeout_conexion,
        application_name=f"iot-simulator-grupo-{cfg.grupo}",
        # Cada lote abre su propia transacción con conexion.transaction().
        autocommit=True,
    )


class RepositorioPostgres:
    def __init__(self, conexion: psycopg.Connection) -> None:
        self.conexion = conexion

    def umbrales(self, camiones: Iterable[str]) -> Mapping[str, Umbral]:
        with self.conexion.cursor() as cur:
            cur.execute(SQL_UMBRALES, (list(camiones),))
            return {fila[0]: Umbral(*fila) for fila in cur.fetchall()}

    def insertar_lectura(self, lectura: Lectura) -> int:
        with self.conexion.cursor() as cur:
            cur.execute(
                SQL_LECTURA,
                (
                    lectura.camion_id,
                    lectura.temp_camara,
                    lectura.lat,
                    lectura.long,
                    lectura.combustible_pct,
                    lectura.alerta_ruptura_frio,
                ),
            )
            return cur.fetchone()[0]

    def insertar_alerta(self, id_telemetria: int) -> int:
        with self.conexion.cursor() as cur:
            cur.execute(SQL_ALERTA, (id_telemetria,))
            return cur.fetchone()[0]
