"""Los camiones de un contenedor y el ciclo de lectura que los escribe en la base."""

from __future__ import annotations

import random
from collections.abc import Iterable, Mapping
from dataclasses import dataclass
from typing import Protocol

from .camion import Camion, Lectura, avanzar, crear_camion, iniciar_ruptura
from .config import Configuracion
from .frio import Rango, debe_crear_alerta, rango_aplicable


@dataclass(frozen=True)
class Umbral:
    """Una fila de la vista `iot_umbral_camion`."""

    camion_id: str
    activo: bool
    temp_min_c: float | None
    temp_max_c: float | None
    alerta_ruptura_abierta: bool


class Repositorio(Protocol):
    def umbrales(self, camiones: Iterable[str]) -> Mapping[str, Umbral]: ...

    def insertar_lectura(self, lectura: Lectura) -> int: ...

    def insertar_alerta(self, id_telemetria: int) -> int: ...


@dataclass(frozen=True)
class ResultadoCiclo:
    lecturas: list[Lectura]
    alertas_nuevas: dict[str, int]  # camion_id -> id_alerta
    sin_registrar: list[str]  # camiones del grupo que aún no existen en la base


class Flota:
    def __init__(self, cfg: Configuracion, rng: random.Random) -> None:
        self.cfg = cfg
        self.rng = rng
        self.camiones: dict[str, Camion] = {}
        self.defecto = Rango(cfg.temp_min_defecto, cfg.temp_max_defecto, por_defecto=True)
        self._rupturas_pedidas: set[str] = set()
        self.pedir_ruptura(solo_configurados=True)

    @property
    def km_por_ciclo(self) -> float:
        return self.cfg.velocidad_kmh * self.cfg.intervalo_segundos / 3600

    def pedir_ruptura(self, solo_configurados: bool = False) -> list[str]:
        """Demo: fuerza un episodio en la próxima lectura.

        Usa los camiones de IOT_FORZAR_RUPTURA que pertenezcan a este grupo; si no
        hay ninguno, el primer camión del grupo que no esté ya en ruptura.
        """
        objetivo = [c for c in self.cfg.forzar_ruptura if c in self.cfg.camiones]
        if not objetivo and not solo_configurados:
            libres = [c for c in self.cfg.camiones if not (c in self.camiones and self.camiones[c].en_episodio)]
            objetivo = libres[:1]
        self._rupturas_pedidas.update(objetivo)
        return objetivo

    def generar(self, umbrales: Mapping[str, Umbral]) -> tuple[list[Lectura], list[str]]:
        """Una lectura por cada camión del grupo que exista en la base."""
        lecturas: list[Lectura] = []
        sin_registrar: list[str] = []
        for camion_id in self.cfg.camiones:
            umbral = umbrales.get(camion_id)
            if umbral is None:
                sin_registrar.append(camion_id)
                continue
            rango = rango_aplicable(umbral.temp_min_c, umbral.temp_max_c, self.defecto)
            camion = self.camiones.get(camion_id)
            if camion is None:
                camion = self.camiones[camion_id] = crear_camion(camion_id, rango, self.rng)
            if camion_id in self._rupturas_pedidas:
                self._rupturas_pedidas.discard(camion_id)
                iniciar_ruptura(camion, rango, self.cfg.ruptura_ciclos_max, self.rng, inmediata=True)
            lecturas.append(
                avanzar(
                    camion,
                    rango,
                    umbral.activo,
                    km_por_ciclo=self.km_por_ciclo,
                    consumo_pct_por_km=self.cfg.consumo_pct_por_km,
                    prob_ruptura=self.cfg.prob_ruptura,
                    ciclos_ruptura=(self.cfg.ruptura_ciclos_min, self.cfg.ruptura_ciclos_max),
                    rng=self.rng,
                )
            )
        return lecturas, sin_registrar


def ejecutar_ciclo(flota: Flota, repo: Repositorio) -> ResultadoCiclo:
    """Lee umbrales, genera las lecturas y las inserta; abre una alerta al iniciar cada episodio.

    El llamador envuelve todo en una transacción: o entra el lote completo o nada.
    """
    umbrales = repo.umbrales(flota.cfg.camiones)
    lecturas, sin_registrar = flota.generar(umbrales)
    alertas: dict[str, int] = {}
    for lectura in lecturas:
        id_telemetria = repo.insertar_lectura(lectura)
        if debe_crear_alerta(lectura.alerta_ruptura_frio, umbrales[lectura.camion_id].alerta_ruptura_abierta):
            alertas[lectura.camion_id] = repo.insertar_alerta(id_telemetria)
    return ResultadoCiclo(lecturas, alertas, sin_registrar)
