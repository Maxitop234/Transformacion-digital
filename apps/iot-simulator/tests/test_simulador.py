"""Tests de la generación de lecturas y del cálculo de la alerta (T-019)."""

from __future__ import annotations

import random

import pytest

from simulador.camion import avanzar, crear_camion, iniciar_ruptura
from simulador.config import ConfiguracionInvalida, camiones_del_grupo, cargar
from simulador.flota import Flota, Umbral, ejecutar_ciclo
from simulador.frio import Rango, debe_crear_alerta, rango_aplicable, ruptura_de_frio
from simulador.rutas import RUTAS, distancia_km, ruta_del_camion

ENV_BASE = {"IOT_DB_USERNAME": "iot_writer", "IOT_DB_PASSWORD": "x"}
REFRIGERADO = Rango(0.0, 5.0)
CONGELADO = Rango(-25.0, -18.0)
PARAMS = dict(km_por_ciclo=0.5, consumo_pct_por_km=0.1, prob_ruptura=0.0, ciclos_ruptura=(4, 10))


def config(**extra):
    return cargar({**ENV_BASE, **extra})


# --- Configuración y reparto de la flota ---------------------------------------------


def test_cinco_contenedores_cubren_cam_001_a_cam_050_sin_solaparse():
    grupos = [camiones_del_grupo(g, 10) for g in range(1, 6)]
    todos = [c for grupo in grupos for c in grupo]
    assert len(todos) == len(set(todos)) == 50
    assert todos == [f"CAM-{n:03d}" for n in range(1, 51)]
    assert grupos[2][0] == "CAM-021" and grupos[2][-1] == "CAM-030"


def test_configuracion_exige_credenciales_iot_y_valida_el_grupo():
    with pytest.raises(ConfiguracionInvalida, match="IOT_DB_USERNAME"):
        cargar({})
    with pytest.raises(ConfiguracionInvalida, match="IOT_GRUPO"):
        config(IOT_GRUPO="6", IOT_CONTENEDORES="5")
    cfg = config(IOT_GRUPO="5", DB_HOST="db")
    assert cfg.db_usuario == "iot_writer"
    assert cfg.db_host == "db"
    assert cfg.camiones[-1] == "CAM-050"


def test_configuracion_ignora_credenciales_de_otros_componentes():
    cfg = config(DB_USERNAME="app_rw", DB_PASSWORD="otra", DB_ADMIN_USERNAME="postgres")
    assert (cfg.db_usuario, cfg.db_clave) == ("iot_writer", "x")


def test_forzar_ruptura_se_lee_como_lista():
    assert config(IOT_FORZAR_RUPTURA=" cam-003, CAM-007 ,").forzar_ruptura == ("CAM-003", "CAM-007")


# --- Rango y cálculo de alerta_ruptura_frio ------------------------------------------


def test_rango_nulo_usa_el_de_defecto():
    defecto = Rango(0.0, 5.0, por_defecto=True)
    assert rango_aplicable(None, None, defecto) == defecto
    assert rango_aplicable(-25, -18, defecto) == CONGELADO
    # Carga incompatible (mínimo >= máximo): no se marca ruptura en todo; se usa el de defecto.
    assert rango_aplicable(2, 1, defecto) == defecto


@pytest.mark.parametrize(
    ("temp", "esperado"),
    [(2.5, False), (0.0, False), (5.0, False), (5.1, True), (-0.1, True), (9.4, True)],
)
def test_alerta_ruptura_frio_contra_el_rango(temp, esperado):
    assert ruptura_de_frio(temp, REFRIGERADO) is esperado


def test_solo_se_crea_alerta_si_no_hay_una_abierta():
    assert debe_crear_alerta(True, False)
    assert not debe_crear_alerta(True, True)
    assert not debe_crear_alerta(False, False)


# --- Generación de lecturas ----------------------------------------------------------


def test_lecturas_respetan_tipos_y_rangos_del_contrato():
    rng = random.Random(1)
    for numero in range(1, 51):
        camion = crear_camion(f"CAM-{numero:03d}", REFRIGERADO, rng)
        for _ in range(200):
            lectura = avanzar(camion, REFRIGERADO, True, **{**PARAMS, "prob_ruptura": 0.05}, rng=rng)
            assert isinstance(lectura.camion_id, str)
            assert isinstance(lectura.temp_camara, float) and -60 <= lectura.temp_camara <= 60
            assert isinstance(lectura.lat, float) and -90 <= lectura.lat <= 90
            assert isinstance(lectura.long, float) and -180 <= lectura.long <= 180
            assert isinstance(lectura.combustible_pct, int) and 0 <= lectura.combustible_pct <= 100
            assert isinstance(lectura.alerta_ruptura_frio, bool)
            assert lectura.alerta_ruptura_frio == ruptura_de_frio(lectura.temp_camara, REFRIGERADO)


def test_sin_ruptura_la_temperatura_queda_cerca_de_la_consigna():
    rng = random.Random(2)
    for rango in (REFRIGERADO, CONGELADO):
        camion = crear_camion("CAM-001", rango, rng)
        lecturas = [avanzar(camion, rango, True, **PARAMS, rng=rng) for _ in range(500)]
        assert not any(lectura.alerta_ruptura_frio for lectura in lecturas)
        promedio = sum(lectura.temp_camara for lectura in lecturas) / len(lecturas)
        assert abs(promedio - rango.consigna) < 0.3


def test_el_camion_avanza_sobre_su_ruta_de_la_macrozona_sur_y_consume_combustible():
    rng = random.Random(3)
    camion = crear_camion("CAM-002", REFRIGERADO, rng)
    camion.combustible = 80.0
    inicio = avanzar(camion, REFRIGERADO, True, **PARAMS, rng=rng)
    fin = None
    for _ in range(20):
        fin = avanzar(camion, REFRIGERADO, True, **PARAMS, rng=rng)
    assert distancia_km((inicio.lat, inicio.long), (fin.lat, fin.long)) > 5
    assert fin.combustible_pct < inicio.combustible_pct
    # Macrozona sur: entre el Biobío y Los Lagos.
    assert -41.5 < fin.lat < -37.0 and -74.0 < fin.long < -71.0


def test_camion_inactivo_queda_en_bodega_sin_consumir():
    rng = random.Random(4)
    camion = crear_camion("CAM-010", REFRIGERADO, rng)
    antes = camion.combustible
    lectura = avanzar(camion, REFRIGERADO, False, **PARAMS, rng=rng)
    assert distancia_km((lectura.lat, lectura.long), (-38.7359, -72.5904)) < 0.1
    assert camion.combustible == antes


def test_episodio_de_ruptura_dura_varios_ciclos_y_se_recupera():
    rng = random.Random(5)
    camion = crear_camion("CAM-003", REFRIGERADO, rng)
    iniciar_ruptura(camion, REFRIGERADO, 6, rng)
    lecturas = [avanzar(camion, REFRIGERADO, True, **PARAMS, rng=rng) for _ in range(30)]
    fuera = [lectura.alerta_ruptura_frio for lectura in lecturas]
    assert sum(fuera[:8]) >= 3  # sube por encima del máximo durante el episodio
    assert not any(fuera[-10:])  # y vuelve a la consigna
    assert max(lectura.temp_camara for lectura in lecturas) > REFRIGERADO.temp_max_c


def test_rutas_definidas_y_asignadas_a_todos_los_camiones():
    assert all(ruta.largo_km > 20 for ruta in RUTAS)
    assert {ruta_del_camion(f"CAM-{n:03d}") for n in range(1, 51)} == set(RUTAS)


# --- Ciclo completo con un repositorio en memoria ------------------------------------


class RepositorioFalso:
    """Imita la base: la vista marca `alerta_ruptura_abierta` cuando ya se insertó una."""

    def __init__(self, camiones, activo=True, rango=(None, None)):
        self.filas = {c: Umbral(c, activo, *rango, alerta_ruptura_abierta=False) for c in camiones}
        self.lecturas = []
        self.alertas = []

    def umbrales(self, camiones):
        return {c: self.filas[c] for c in camiones if c in self.filas}

    def insertar_lectura(self, lectura):
        self.lecturas.append(lectura)
        return len(self.lecturas)

    def insertar_alerta(self, id_telemetria):
        camion_id = self.lecturas[id_telemetria - 1].camion_id
        fila = self.filas[camion_id]
        self.filas[camion_id] = Umbral(
            fila.camion_id, fila.activo, fila.temp_min_c, fila.temp_max_c, alerta_ruptura_abierta=True
        )
        self.alertas.append(id_telemetria)
        return len(self.alertas)


def test_ciclo_inserta_una_lectura_por_camion_del_grupo():
    cfg = config(IOT_GRUPO="2", IOT_PROB_RUPTURA_FRIO="0")
    repo = RepositorioFalso(cfg.camiones)
    resultado = ejecutar_ciclo(Flota(cfg, random.Random(6)), repo)
    assert [lectura.camion_id for lectura in resultado.lecturas] == [f"CAM-{n:03d}" for n in range(11, 21)]
    assert resultado.alertas_nuevas == {}


def test_ruptura_forzada_crea_una_sola_alerta_por_episodio():
    cfg = config(IOT_FORZAR_RUPTURA="CAM-004", IOT_PROB_RUPTURA_FRIO="0", IOT_RUPTURA_CICLOS_MAX="6")
    repo = RepositorioFalso(cfg.camiones)
    flota = Flota(cfg, random.Random(7))
    resultados = [ejecutar_ciclo(flota, repo) for _ in range(8)]
    en_ruptura = [r for r in resultados if any(x.alerta_ruptura_frio for x in r.lecturas if x.camion_id == "CAM-004")]
    assert len(en_ruptura) >= 3
    assert len(repo.alertas) == 1
    assert repo.lecturas[repo.alertas[0] - 1].alerta_ruptura_frio


def test_senal_de_demo_fuerza_ruptura_en_el_primer_camion_libre():
    cfg = config(IOT_PROB_RUPTURA_FRIO="0")
    flota = Flota(cfg, random.Random(8))
    assert flota.pedir_ruptura() == ["CAM-001"]
    resultado = ejecutar_ciclo(flota, RepositorioFalso(cfg.camiones))
    assert flota.camiones["CAM-001"].en_episodio
    assert resultado.lecturas[0].alerta_ruptura_frio  # la ruptura forzada se ve en la lectura siguiente
    assert not any(flota.camiones[c].en_episodio for c in cfg.camiones[1:])
    assert resultado.lecturas[0].camion_id == "CAM-001"


def test_camiones_que_no_existen_en_la_base_se_omiten():
    cfg = config()
    repo = RepositorioFalso(cfg.camiones[:7])
    resultado = ejecutar_ciclo(Flota(cfg, random.Random(9)), repo)
    assert len(resultado.lecturas) == 7
    assert resultado.sin_registrar == ["CAM-008", "CAM-009", "CAM-010"]
