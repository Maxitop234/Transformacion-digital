"""Configuración del simulador: solo variables de entorno (docs/API.md §2)."""

from __future__ import annotations

import os
from collections.abc import Mapping
from dataclasses import dataclass


class ConfiguracionInvalida(ValueError):
    """Una variable de entorno falta o tiene un valor que no se puede usar."""


@dataclass(frozen=True)
class Configuracion:
    # Conexión: solo el usuario iot_writer (ADR-0006).
    db_host: str
    db_port: int
    db_nombre: str
    db_usuario: str
    db_clave: str
    db_sslmode: str
    db_timeout_conexion: int

    # Flota y ritmo.
    grupo: int
    contenedores: int
    camiones_por_contenedor: int
    intervalo_segundos: float

    # Comportamiento del frío.
    temp_min_defecto: float
    temp_max_defecto: float
    prob_ruptura: float
    ruptura_ciclos_min: int
    ruptura_ciclos_max: int

    # Movimiento y combustible.
    velocidad_kmh: float
    consumo_pct_por_km: float

    # Reintentos y demo.
    reintento_max_segundos: float
    semilla: int | None
    forzar_ruptura: tuple[str, ...]

    @property
    def camiones(self) -> list[str]:
        """Camiones de este contenedor: un bloque contiguo que no se solapa con otro grupo."""
        return camiones_del_grupo(self.grupo, self.camiones_por_contenedor)


def camiones_del_grupo(grupo: int, por_contenedor: int) -> list[str]:
    inicio = (grupo - 1) * por_contenedor + 1
    return [f"CAM-{n:03d}" for n in range(inicio, inicio + por_contenedor)]


def _texto(env: Mapping[str, str], nombre: str, defecto: str | None = None) -> str:
    valor = env.get(nombre, "").strip()
    if valor:
        return valor
    if defecto is None:
        raise ConfiguracionInvalida(f"Falta la variable de entorno {nombre}")
    return defecto


def _primero(env: Mapping[str, str], nombres: tuple[str, ...], defecto: str | None = None) -> str:
    for nombre in nombres:
        valor = env.get(nombre, "").strip()
        if valor:
            return valor
    if defecto is None:
        raise ConfiguracionInvalida(f"Falta la variable de entorno {nombres[0]}")
    return defecto


def _entero(env: Mapping[str, str], nombre: str, defecto: int, minimo: int | None = None) -> int:
    crudo = _texto(env, nombre, str(defecto))
    try:
        valor = int(crudo)
    except ValueError as error:
        raise ConfiguracionInvalida(f"{nombre} debe ser un entero (recibido «{crudo}»)") from error
    if minimo is not None and valor < minimo:
        raise ConfiguracionInvalida(f"{nombre} debe ser mayor o igual a {minimo}")
    return valor


def _decimal(env: Mapping[str, str], nombre: str, defecto: float) -> float:
    crudo = _texto(env, nombre, str(defecto))
    try:
        return float(crudo)
    except ValueError as error:
        raise ConfiguracionInvalida(f"{nombre} debe ser un número (recibido «{crudo}»)") from error


def cargar(env: Mapping[str, str] | None = None) -> Configuracion:
    """Lee y valida la configuración. Lanza ConfiguracionInvalida con un mensaje legible."""
    env = os.environ if env is None else env

    contenedores = _entero(env, "IOT_CONTENEDORES", 5, minimo=1)
    grupo = _entero(env, "IOT_GRUPO", 1, minimo=1)
    if grupo > contenedores:
        raise ConfiguracionInvalida(f"IOT_GRUPO={grupo} supera IOT_CONTENEDORES={contenedores}")

    temp_min = _decimal(env, "IOT_TEMP_MIN_DEFECTO", 0.0)
    temp_max = _decimal(env, "IOT_TEMP_MAX_DEFECTO", 5.0)
    if temp_min >= temp_max:
        raise ConfiguracionInvalida("IOT_TEMP_MIN_DEFECTO debe ser menor que IOT_TEMP_MAX_DEFECTO")

    prob = _decimal(env, "IOT_PROB_RUPTURA_FRIO", 0.02)
    if not 0 <= prob <= 1:
        raise ConfiguracionInvalida("IOT_PROB_RUPTURA_FRIO debe estar entre 0 y 1")

    ciclos_min = _entero(env, "IOT_RUPTURA_CICLOS_MIN", 4, minimo=1)
    ciclos_max = _entero(env, "IOT_RUPTURA_CICLOS_MAX", 10, minimo=1)
    if ciclos_min > ciclos_max:
        raise ConfiguracionInvalida("IOT_RUPTURA_CICLOS_MIN no puede superar IOT_RUPTURA_CICLOS_MAX")

    intervalo = _decimal(env, "IOT_INTERVALO_SEGUNDOS", 30.0)
    if intervalo <= 0:
        raise ConfiguracionInvalida("IOT_INTERVALO_SEGUNDOS debe ser mayor que 0")

    semilla_cruda = env.get("IOT_SEMILLA", "").strip()
    try:
        semilla = int(semilla_cruda) if semilla_cruda else None
    except ValueError as error:
        raise ConfiguracionInvalida("IOT_SEMILLA debe ser un entero o quedar vacía") from error

    forzar = tuple(c.strip().upper() for c in env.get("IOT_FORZAR_RUPTURA", "").split(",") if c.strip())

    return Configuracion(
        # IOT_DB_* permite apuntar el nodo IoT a RDS; en local se reutiliza DB_*.
        db_host=_primero(env, ("IOT_DB_HOST", "DB_HOST"), "localhost"),
        db_port=int(_primero(env, ("IOT_DB_PORT", "DB_PORT"), "5432")),
        db_nombre=_primero(env, ("IOT_DB_DATABASE", "DB_DATABASE"), "cofrial"),
        db_usuario=_texto(env, "IOT_DB_USERNAME"),
        db_clave=_texto(env, "IOT_DB_PASSWORD"),
        db_sslmode=_primero(env, ("IOT_DB_SSLMODE", "DB_SSLMODE"), "prefer"),
        db_timeout_conexion=int(_primero(env, ("IOT_DB_CONNECT_TIMEOUT", "DB_CONNECT_TIMEOUT"), "5")),
        grupo=grupo,
        contenedores=contenedores,
        camiones_por_contenedor=_entero(env, "IOT_CAMIONES_POR_CONTENEDOR", 10, minimo=1),
        intervalo_segundos=intervalo,
        temp_min_defecto=temp_min,
        temp_max_defecto=temp_max,
        prob_ruptura=prob,
        ruptura_ciclos_min=ciclos_min,
        ruptura_ciclos_max=ciclos_max,
        velocidad_kmh=_decimal(env, "IOT_VELOCIDAD_KMH", 60.0),
        consumo_pct_por_km=_decimal(env, "IOT_CONSUMO_PCT_POR_KM", 0.1),
        reintento_max_segundos=_decimal(env, "IOT_REINTENTO_MAX_SEGUNDOS", 30.0),
        semilla=semilla,
        forzar_ruptura=forzar,
    )
