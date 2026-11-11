"""Rutas de reparto de la macrozona sur, con salida y regreso a la bodega de Temuco.

Cada ruta es una polilínea de puntos (lat, long) de localidades reales; el camión
avanza sobre ella a velocidad constante, ida y vuelta.
"""

from __future__ import annotations

import math
from dataclasses import dataclass

Punto = tuple[float, float]

BODEGA_TEMUCO: Punto = (-38.7359, -72.5904)


@dataclass(frozen=True)
class Ruta:
    nombre: str
    puntos: tuple[Punto, ...]

    @property
    def largo_km(self) -> float:
        return sum(distancia_km(a, b) for a, b in zip(self.puntos, self.puntos[1:], strict=False))

    def posicion(self, km: float) -> Punto:
        """Punto a `km` kilómetros desde el origen, interpolado sobre la polilínea."""
        km = min(max(km, 0.0), self.largo_km)
        for a, b in zip(self.puntos, self.puntos[1:], strict=False):
            tramo = distancia_km(a, b)
            if km <= tramo:
                f = km / tramo if tramo else 0.0
                return (a[0] + (b[0] - a[0]) * f, a[1] + (b[1] - a[1]) * f)
            km -= tramo
        return self.puntos[-1]


RUTAS: tuple[Ruta, ...] = (
    Ruta(
        "Temuco - Valdivia",
        (
            BODEGA_TEMUCO,
            (-38.9530, -72.6220),  # Freire
            (-38.9860, -72.6370),  # Pitrufquén
            (-39.1000, -72.6730),  # Gorbea
            (-39.3670, -72.6310),  # Loncoche
            (-39.4520, -72.7750),  # Lanco
            (-39.8142, -73.2459),  # Valdivia
        ),
    ),
    Ruta(
        "Temuco - Pucón",
        (
            BODEGA_TEMUCO,
            (-38.9530, -72.6220),  # Freire
            (-39.2820, -72.2270),  # Villarrica
            (-39.2720, -71.9770),  # Pucón
        ),
    ),
    Ruta(
        "Temuco - Angol",
        (
            BODEGA_TEMUCO,
            (-38.5300, -72.4350),  # Lautaro
            (-38.2320, -72.3330),  # Victoria
            (-37.9550, -72.4330),  # Collipulli
            (-37.7950, -72.7160),  # Angol
        ),
    ),
    Ruta(
        "Temuco - Puerto Saavedra",
        (
            BODEGA_TEMUCO,
            (-38.7450, -72.9500),  # Nueva Imperial
            (-38.7110, -73.1640),  # Carahue
            (-38.7840, -73.3960),  # Puerto Saavedra
        ),
    ),
    Ruta(
        "Temuco - Melipeuco",
        (
            BODEGA_TEMUCO,
            (-38.9300, -72.0260),  # Cunco
            (-38.8500, -71.6930),  # Melipeuco
        ),
    ),
    Ruta(
        "Temuco - Osorno",
        (
            BODEGA_TEMUCO,
            (-39.3670, -72.6310),  # Loncoche
            (-39.8500, -72.8330),  # Los Lagos
            (-40.0700, -72.8700),  # Paillaco
            (-40.2930, -73.0820),  # La Unión
            (-40.5740, -73.1330),  # Osorno
        ),
    ),
    Ruta(
        "Temuco - Lonquimay",
        (
            BODEGA_TEMUCO,
            (-38.5300, -72.4350),  # Lautaro
            (-38.4410, -71.8890),  # Curacautín
            (-38.4500, -71.2330),  # Lonquimay
        ),
    ),
)


def distancia_km(a: Punto, b: Punto) -> float:
    """Distancia por la superficie terrestre (haversine)."""
    lat1, lon1, lat2, lon2 = map(math.radians, (a[0], a[1], b[0], b[1]))
    h = math.sin((lat2 - lat1) / 2) ** 2 + math.cos(lat1) * math.cos(lat2) * math.sin((lon2 - lon1) / 2) ** 2
    return 2 * 6371.0 * math.asin(math.sqrt(h))


def ruta_del_camion(camion_id: str) -> Ruta:
    """Asignación fija y repartida: CAM-001 a la ruta 1, CAM-002 a la 2, etc."""
    numero = int(camion_id.rsplit("-", 1)[-1])
    return RUTAS[(numero - 1) % len(RUTAS)]
