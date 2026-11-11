# apps/iot-simulator · Simulador de telemetría

Proceso Python 3.12 ([ADR-0014](../../docs/ADR/0014-simulador-iot-python.md)) que simula los sensores de los 50 camiones frigoríficos y escribe sus lecturas en PostgreSQL con el usuario `iot_writer`. Cinco contenedores de 10 camiones cada uno cubren `CAM-001` a `CAM-050` sin solaparse ([ADR-0006](../../docs/ADR/0006-nodo-iot-dedicado.md)). Construido en [T-019](../../tasks/T-019-simulador-iot/README.md).

## Qué hace

Cada 30 segundos, por cada camión de su grupo, inserta una fila en `telemetria` con los campos exigidos por el enunciado:

| Campo | Tipo | Cómo se genera |
| --- | --- | --- |
| `camion_id` | String | `CAM-001` … `CAM-050`; el grupo `g` simula `CAM-(10g-9)` a `CAM-10g` |
| `temp_camara` | Float | Vuelve hacia la consigna (centro del rango) con un ruido de ±0,25 °C; durante una ruptura sube hasta 2-8 °C sobre el máximo |
| `lat` / `long` | Float | Avance a 60 km/h, ida y vuelta, sobre una de 7 rutas que salen de la bodega de Temuco (Valdivia, Pucón, Angol, Puerto Saavedra, Melipeuco, Osorno, Lonquimay) |
| `combustible_pct` | Integer | Baja 0,1 % por km; recarga a 100 % al llegar a un extremo de la ruta con menos de 30 % |
| `alerta_ruptura_frio` | Boolean | `true` si `temp_camara` está fuera del rango (los bordes son válidos) |

- **Rango aplicable:** el de la vista `iot_umbral_camion` (carga del despacho en ruta). Si es nulo, el de defecto `IOT_TEMP_MIN_DEFECTO`..`IOT_TEMP_MAX_DEFECTO` (0 a 5 °C, refrigerado); en el registro aparece como `(defecto)`. Si cambia la carga, el equipo arranca ya en la nueva consigna.
- **Episodios de ruptura:** en cada ciclo, cada camión tiene probabilidad `IOT_PROB_RUPTURA_FRIO` de iniciar uno, que dura entre `IOT_RUPTURA_CICLOS_MIN` y `IOT_RUPTURA_CICLOS_MAX` lecturas; después la temperatura vuelve sola a la consigna.
- **Alerta:** si la lectura está en ruptura y la vista dice que el camión no tiene una alerta de ruptura abierta, inserta una fila en `alerta` (`ruptura_frio`, `alta`, `abierta`) que apunta a esa lectura. No se repite mientras siga abierta; la resuelve el despachador desde el CRM.
- **Camiones inactivos** (`camion.activo = false`): siguen transmitiendo desde la bodega de Temuco, sin moverse ni consumir combustible. Un camión que todavía no existe en la base se omite y se avisa en el registro.
- **Un lote, una transacción:** las 10 lecturas y sus alertas entran juntas o no entra ninguna.
- **Reintentos:** ante cualquier error de base registra el motivo, espera 1, 2, 4… hasta `IOT_REINTENTO_MAX_SEGUNDOS` y reconecta; el proceso no termina. Las lecturas del tiempo sin base se pierden (no se acumulan).

El contrato completo está en [`docs/API.md`](../../docs/API.md#2-contrato-de-telemetría-iot).

## Variables de entorno

Solo variables de entorno, sin archivos de configuración. Los valores por defecto están en [`.env.example`](../../.env.example).

| Variable | Defecto | Uso |
| --- | --- | --- |
| `IOT_DB_USERNAME` / `IOT_DB_PASSWORD` | — (obligatorias) | Credenciales de `iot_writer`. Es la única credencial que lee |
| `IOT_DB_HOST`, `IOT_DB_PORT`, `IOT_DB_DATABASE`, `IOT_DB_SSLMODE`, `IOT_DB_CONNECT_TIMEOUT` | Las `DB_*` equivalentes, si existen; si no, `localhost`, `5432`, `cofrial`, `prefer`, `5` | Dónde está la base. En el nodo IoT apuntan a RDS con `require` |
| `IOT_GRUPO` | `1` | Grupo de camiones de este contenedor (1 a `IOT_CONTENEDORES`) |
| `IOT_CONTENEDORES` | `5` | Cantidad de grupos |
| `IOT_CAMIONES_POR_CONTENEDOR` | `10` | Camiones por grupo |
| `IOT_INTERVALO_SEGUNDOS` | `30` | Ritmo de las lecturas |
| `IOT_PROB_RUPTURA_FRIO` | `0.02` | Probabilidad por camión y por ciclo de iniciar un episodio |
| `IOT_RUPTURA_CICLOS_MIN` / `IOT_RUPTURA_CICLOS_MAX` | `4` / `10` | Duración de un episodio, en lecturas |
| `IOT_TEMP_MIN_DEFECTO` / `IOT_TEMP_MAX_DEFECTO` | `0` / `5` | Rango cuando el camión no tiene despacho en ruta |
| `IOT_VELOCIDAD_KMH` | `60` | Velocidad en ruta |
| `IOT_CONSUMO_PCT_POR_KM` | `0.1` | Consumo de combustible |
| `IOT_REINTENTO_MAX_SEGUNDOS` | `30` | Tope de la espera entre reintentos |
| `IOT_SEMILLA` | vacía | Entero para repetir la misma simulación (cada grupo deriva la suya) |
| `IOT_FORZAR_RUPTURA` | vacía | Lista de camiones (`CAM-003,CAM-027`) que entran en ruptura al arrancar y con cada `SIGUSR1` |

En el entorno local los contenedores reciben el `.env` completo, como el resto de los servicios, pero el simulador solo lee las variables de esta tabla y `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_SSLMODE`/`DB_CONNECT_TIMEOUT`. En el nodo IoT ([`compose.iot.yml`](../../infra/docker/compose.iot.yml)) cada contenedor recibe solo las `IOT_*`.

## Comandos

Desde la raíz del repositorio.

### Entorno local

El simulador forma parte de [`compose.dev.yml`](../../infra/docker/compose.dev.yml) (servicios `iot-1` a `iot-5`) y arranca con el resto. Necesita las migraciones y la vista `iot_umbral_camion` (las aplica `web` al arrancar) y la flota sembrada (`php artisan db:seed`); mientras falten, registra el error y reintenta.

```bash
docker compose -f infra/docker/compose.dev.yml up -d --build iot-1 iot-2 iot-3 iot-4 iot-5
```

```bash
docker compose -f infra/docker/compose.dev.yml logs -f iot-1
```

Cada lote se ve así en `docker logs`:

```text
2026-11-11T06:05:17Z INFO  grupo 2 · lote 4: 10 lecturas insertadas en telemetria · 1 fuera de rango · 1 alertas nuevas
  CAM-011  temp    5.8 °C  rango [0, 5] (defecto)  pos -38.73215, -73.23162  comb  78%  RUPTURA DE FRÍO · alerta #4 creada
  CAM-012  temp    2.4 °C  rango [0, 5] (defecto)  pos -39.27310, -72.09864  comb  64%  ok
  …
```

Comprobar que llegan 50 filas cada 30 s:

```bash
docker compose -f infra/docker/compose.dev.yml exec db psql -U postgres -d cofrial -c "SELECT date_trunc('minute', fecha_hora) + floor(extract(second FROM fecha_hora) / 30) * interval '30 s' AS ventana, count(*) FROM telemetria GROUP BY 1 ORDER BY 1 DESC LIMIT 5"
```

### Provocar una ruptura de frío (demo)

Con el simulador corriendo, la señal `SIGUSR1` hace que el primer camión libre del grupo (o los de `IOT_FORZAR_RUPTURA` que pertenezcan a él) salga del rango en la lectura siguiente y abra una alerta:

```bash
docker kill -s USR1 cofrial-iot-2-1
```

En el nodo IoT el contenedor se llama `cofrial-iot-iot-2-1`. Para que un camión concreto arranque en ruptura, define `IOT_FORZAR_RUPTURA=CAM-011` en el `.env` y recrea su contenedor (`up -d iot-2`).

### Tests y formato

Tests unitarios (pytest) de la generación de lecturas, el reparto de camiones y el cálculo de la alerta, más el formateador y el linter (ruff), dentro de la imagen de pruebas:

```bash
docker build --target test -t cofrial/iot-simulator:test apps/iot-simulator
```

```bash
docker run --rm cofrial/iot-simulator:test
```

Para aplicar el formato sobre el código montado:

```bash
docker run --rm -u "$(id -u):$(id -g)" -e RUFF_CACHE_DIR=/tmp/ruff -v "$PWD/apps/iot-simulator:/app" cofrial/iot-simulator:test sh -c "ruff format . && ruff check --fix . && pytest -p no:cacheprovider"
```

Sin Docker, con Python 3.12: `python -m venv .venv && .venv/bin/pip install -r requirements-dev.txt && .venv/bin/pytest` dentro de `apps/iot-simulator`.

### Nodo IoT

```bash
docker compose -f infra/docker/compose.iot.yml --env-file .env.iot up -d --build
```

`.env.iot` (ignorado por git) define al menos `IOT_DB_HOST` (endpoint de RDS) e `IOT_DB_PASSWORD`. El despliegue en EC2 es [T-025](../../tasks/T-025-despliegue-nodo-iot/README.md).

## Estructura

| Archivo | Qué contiene |
| --- | --- |
| `simulador/__main__.py` | Bucle de ciclos a ritmo fijo, reintentos, señales y registro legible |
| `simulador/config.py` | Lectura y validación de las variables de entorno |
| `simulador/flota.py` | Los camiones del grupo y el ciclo «leer umbrales → generar → insertar → alertar» |
| `simulador/camion.py` | Estado de un camión y generación de su lectura |
| `simulador/frio.py` | Rango aplicable, `alerta_ruptura_frio` y cuándo crear la alerta |
| `simulador/rutas.py` | Rutas de la macrozona sur e interpolación de la posición |
| `simulador/base.py` | Consultas a PostgreSQL con `psycopg` |
| `tests/` | Tests unitarios (sin base) |

## Reglas propias de este componente

- Se configura solo con variables de entorno.
- Se conecta como `iot_writer`; no conoce ninguna otra credencial.
- No depende del código de `apps/web`, solo del esquema de `telemetria`, `alerta` y la vista `iot_umbral_camion`. Un cambio en esas tablas debe reflejarse en `simulador/base.py`.
- Ante errores de conexión reintenta; no termina.
- Corre sin privilegios dentro del contenedor (usuario `simulador`).

## Referencias

[ADR-0006](../../docs/ADR/0006-nodo-iot-dedicado.md) · [ADR-0014](../../docs/ADR/0014-simulador-iot-python.md) · [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md#5-logística-e-iot)
