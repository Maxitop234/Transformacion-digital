"""Punto de entrada: `python -m simulador`.

Cada IOT_INTERVALO_SEGUNDOS escribe una lectura por camión del grupo. Ante un error
de base reintenta con espera creciente y sigue; no termina (docs/API.md §2.3).
Señales: SIGTERM/SIGINT detienen; SIGUSR1 provoca una ruptura de frío (demo).
"""

from __future__ import annotations

import logging
import random
import signal
import sys
import threading
import time

import psycopg

from . import base
from .config import ConfiguracionInvalida, cargar
from .flota import Flota, ResultadoCiclo, ejecutar_ciclo

log = logging.getLogger("iot")


def configurar_registro() -> None:
    logging.basicConfig(
        stream=sys.stdout,
        level=logging.INFO,
        format="%(asctime)s %(levelname)-5s %(message)s",
        datefmt="%Y-%m-%dT%H:%M:%SZ",
    )
    logging.Formatter.converter = time.gmtime


def describir(resultado: ResultadoCiclo, grupo: int, numero: int) -> str:
    """Bloque legible para `docker logs`: un resumen y una línea por camión."""
    rupturas = sum(1 for lectura in resultado.lecturas if lectura.alerta_ruptura_frio)
    lineas = [
        f"grupo {grupo} · lote {numero}: {len(resultado.lecturas)} lecturas insertadas en telemetria"
        f" · {rupturas} fuera de rango · {len(resultado.alertas_nuevas)} alertas nuevas"
    ]
    for lectura in resultado.lecturas:
        if lectura.camion_id in resultado.alertas_nuevas:
            estado = f"RUPTURA DE FRÍO · alerta #{resultado.alertas_nuevas[lectura.camion_id]} creada"
        elif lectura.alerta_ruptura_frio:
            estado = "RUPTURA DE FRÍO · alerta ya abierta"
        else:
            estado = "ok"
        origen = " (defecto)" if lectura.rango.por_defecto else ""
        lineas.append(
            f"  {lectura.camion_id}  temp {lectura.temp_camara:6.1f} °C  rango {lectura.rango}{origen:<10}"
            f"  pos {lectura.lat:9.5f},{lectura.long:10.5f}  comb {lectura.combustible_pct:3d}%  {estado}"
        )
    if resultado.sin_registrar:
        lineas.append(f"  sin registrar en la base (se omiten): {', '.join(resultado.sin_registrar)}")
    return "\n".join(lineas)


def main() -> int:
    configurar_registro()
    try:
        cfg = cargar()
    except ConfiguracionInvalida as error:
        log.error("Configuración inválida: %s", error)
        return 2

    semilla = None if cfg.semilla is None else cfg.semilla * 1000 + cfg.grupo
    flota = Flota(cfg, random.Random(semilla))
    detener = threading.Event()

    def al_detener(signum, _frame) -> None:
        log.info("Señal %s recibida: deteniendo el simulador", signal.Signals(signum).name)
        detener.set()

    def al_forzar(_signum, _frame) -> None:
        camiones = flota.pedir_ruptura()
        log.warning("SIGUSR1: ruptura de frío forzada en la próxima lectura de %s", ", ".join(camiones) or "—")

    signal.signal(signal.SIGTERM, al_detener)
    signal.signal(signal.SIGINT, al_detener)
    signal.signal(signal.SIGUSR1, al_forzar)

    log.info(
        "Simulador IoT · grupo %d de %d · camiones %s a %s · cada %gs · usuario %s en %s:%d/%s",
        cfg.grupo,
        cfg.contenedores,
        cfg.camiones[0],
        cfg.camiones[-1],
        cfg.intervalo_segundos,
        cfg.db_usuario,
        cfg.db_host,
        cfg.db_port,
        cfg.db_nombre,
    )
    if cfg.forzar_ruptura:
        log.warning("IOT_FORZAR_RUPTURA=%s", ",".join(cfg.forzar_ruptura))

    conexion: psycopg.Connection | None = None
    espera = 1.0
    numero = 0
    proximo = time.monotonic()

    while not detener.is_set():
        try:
            if conexion is None or conexion.closed:
                conexion = base.conectar(cfg)
                log.info("Conectado a la base como %s", cfg.db_usuario)
            with conexion.transaction():
                resultado = ejecutar_ciclo(flota, base.RepositorioPostgres(conexion))
            numero += 1
            log.info(describir(resultado, cfg.grupo, numero))
            espera = 1.0
        except psycopg.Error as error:
            mensaje = str(error).strip().splitlines()[0] if str(error).strip() else type(error).__name__
            log.error("Error de base (%s): %s · reintento en %gs", type(error).__name__, mensaje, espera)
            if conexion is not None:
                conexion.close()
            conexion = None
            detener.wait(espera)
            espera = min(espera * 2, cfg.reintento_max_segundos)
            proximo = time.monotonic()
            continue

        # Ritmo fijo: el siguiente lote sale un intervalo después del anterior.
        proximo += cfg.intervalo_segundos
        ahora = time.monotonic()
        if proximo < ahora:
            proximo = ahora
        detener.wait(proximo - ahora)

    if conexion is not None:
        conexion.close()
    log.info("Simulador detenido")
    return 0


if __name__ == "__main__":
    sys.exit(main())
