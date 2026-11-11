"""Estado de cada camión y generación de su lectura por ciclo."""

from __future__ import annotations

import random
from dataclasses import dataclass

from .frio import Rango, ruptura_de_frio
from .rutas import BODEGA_TEMUCO, Ruta, ruta_del_camion

# Límites de la tabla `telemetria` (CHECK de la migración).
TEMP_MIN_TABLA = -60.0
TEMP_MAX_TABLA = 60.0

# Dinámica del equipo de frío.
RUIDO_TEMP_C = 0.25  # desviación de la lectura normal
RETORNO_CONSIGNA = 0.3  # fracción de la diferencia que corrige cada ciclo
SUBIDA_RUPTURA_C = (0.6, 1.5)  # cuánto sube por ciclo durante una ruptura
EXCESO_MAXIMO_C = (2.0, 8.0)  # cuánto llega a pasar el máximo del rango

COMBUSTIBLE_RECARGA_BAJO = 30.0  # al llegar a un extremo de la ruta con menos, recarga
RUIDO_GPS_GRADOS = 0.00005  # ~5 m


@dataclass(frozen=True)
class Lectura:
    camion_id: str
    temp_camara: float
    lat: float
    long: float
    combustible_pct: int
    alerta_ruptura_frio: bool
    rango: Rango
    en_episodio: bool


@dataclass
class Camion:
    camion_id: str
    ruta: Ruta
    km: float
    sentido: int  # +1 ida, -1 regreso
    combustible: float
    temp: float
    rango: Rango | None = None
    ciclos_ruptura: int = 0  # ciclos que le quedan al episodio en curso
    pico_ruptura: float = 0.0

    @property
    def en_episodio(self) -> bool:
        return self.ciclos_ruptura > 0


def crear_camion(camion_id: str, rango_inicial: Rango, rng: random.Random) -> Camion:
    """Posición, sentido y combustible iniciales al azar, para que la flota se vea repartida."""
    ruta = ruta_del_camion(camion_id)
    return Camion(
        camion_id=camion_id,
        ruta=ruta,
        km=rng.uniform(0, ruta.largo_km),
        sentido=rng.choice((1, -1)),
        combustible=rng.uniform(45, 100),
        temp=rango_inicial.consigna + rng.gauss(0, RUIDO_TEMP_C),
        rango=rango_inicial,
    )


def iniciar_ruptura(camion: Camion, rango: Rango, ciclos: int, rng: random.Random, *, inmediata: bool = False) -> None:
    """Abre un episodio de ruptura de frío que dura `ciclos` lecturas.

    Un episodio natural sube de a poco desde la consigna; uno forzado para la demo
    (`inmediata`) ya sale del rango en la lectura siguiente.
    """
    camion.ciclos_ruptura = max(1, ciclos)
    camion.pico_ruptura = rango.temp_max_c + rng.uniform(*EXCESO_MAXIMO_C)
    if inmediata:
        camion.temp = max(camion.temp, rango.temp_max_c)


def _avanzar_posicion(camion: Camion, km: float) -> None:
    largo = camion.ruta.largo_km
    camion.km += camion.sentido * km
    extremo = False
    if camion.km >= largo:
        camion.km, camion.sentido, extremo = largo - (camion.km - largo), -1, True
    elif camion.km <= 0:
        camion.km, camion.sentido, extremo = -camion.km, 1, True
    camion.km = min(max(camion.km, 0.0), largo)
    if extremo and camion.combustible < COMBUSTIBLE_RECARGA_BAJO:
        camion.combustible = 100.0


def _temperatura(camion: Camion, rango: Rango, rng: random.Random) -> float:
    if camion.en_episodio:
        # Falla del equipo o puerta abierta: sube hasta el pico y se mantiene.
        camion.temp = min(camion.pico_ruptura, camion.temp + rng.uniform(*SUBIDA_RUPTURA_C))
        camion.ciclos_ruptura -= 1
    else:
        # Control normal: vuelve hacia la consigna con un poco de ruido.
        camion.temp += (rango.consigna - camion.temp) * RETORNO_CONSIGNA + rng.gauss(0, RUIDO_TEMP_C)
    camion.temp = min(max(camion.temp, TEMP_MIN_TABLA), TEMP_MAX_TABLA)
    return round(camion.temp, 1)


def avanzar(
    camion: Camion,
    rango: Rango,
    activo: bool,
    *,
    km_por_ciclo: float,
    consumo_pct_por_km: float,
    prob_ruptura: float,
    ciclos_ruptura: tuple[int, int],
    rng: random.Random,
) -> Lectura:
    """Avanza un ciclo y devuelve la lectura. Un camión inactivo queda en bodega, sin moverse."""
    if camion.rango is not None and rango != camion.rango and not camion.en_episodio:
        # Cambió la carga (otro despacho en ruta): el equipo ya viene ajustado a la nueva consigna.
        camion.temp = rango.consigna
    camion.rango = rango

    if activo:
        if not camion.en_episodio and rng.random() < prob_ruptura:
            iniciar_ruptura(camion, rango, rng.randint(*ciclos_ruptura), rng)
        _avanzar_posicion(camion, km_por_ciclo)
        camion.combustible = max(0.0, camion.combustible - km_por_ciclo * consumo_pct_por_km)
        lat, long = camion.ruta.posicion(camion.km)
    else:
        lat, long = BODEGA_TEMUCO

    temp = _temperatura(camion, rango, rng)
    return Lectura(
        camion_id=camion.camion_id,
        temp_camara=temp,
        lat=round(lat + rng.gauss(0, RUIDO_GPS_GRADOS), 6),
        long=round(long + rng.gauss(0, RUIDO_GPS_GRADOS), 6),
        combustible_pct=min(100, max(0, round(camion.combustible))),
        alerta_ruptura_frio=ruptura_de_frio(temp, rango),
        rango=rango,
        en_episodio=camion.en_episodio,
    )
