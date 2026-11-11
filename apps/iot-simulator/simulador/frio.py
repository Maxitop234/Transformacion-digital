"""Rango de conservación y cálculo de `alerta_ruptura_frio` (docs/API.md §2.1 y §2.3)."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class Rango:
    temp_min_c: float
    temp_max_c: float
    por_defecto: bool = False

    @property
    def consigna(self) -> float:
        """Punto de consigna del equipo de frío: el centro del rango."""
        return (self.temp_min_c + self.temp_max_c) / 2

    def __str__(self) -> str:
        return f"[{self.temp_min_c:g}, {self.temp_max_c:g}]"


def rango_aplicable(temp_min_c: float | None, temp_max_c: float | None, defecto: Rango) -> Rango:
    """Rango de la vista `iot_umbral_camion`; si es nulo (sin despacho en ruta), el de defecto.

    Un rango incoherente (mínimo >= máximo, por carga mezclada incompatible) también
    cae al de defecto, para no marcar ruptura en todas las lecturas.
    """
    if temp_min_c is None or temp_max_c is None or float(temp_min_c) >= float(temp_max_c):
        return Rango(defecto.temp_min_c, defecto.temp_max_c, por_defecto=True)
    return Rango(float(temp_min_c), float(temp_max_c))


def ruptura_de_frio(temp_camara: float, rango: Rango) -> bool:
    """`alerta_ruptura_frio`: verdadero si la temperatura sale del rango (los bordes son válidos)."""
    return temp_camara < rango.temp_min_c or temp_camara > rango.temp_max_c


def debe_crear_alerta(alerta_ruptura_frio: bool, alerta_abierta: bool) -> bool:
    """Una alerta por episodio: solo si la lectura está en ruptura y no hay una alerta abierta."""
    return alerta_ruptura_frio and not alerta_abierta
