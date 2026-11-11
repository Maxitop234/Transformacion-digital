# T-019 · Simulador de telemetría IoT

| Campo | Valor |
| --- | --- |
| Fase | 3 · IoT |
| Dimensión de la pauta | 3 |
| Depende de | [T-003](../T-003-esquema-base-de-datos/README.md), [T-005](../T-005-roles-base-de-datos/README.md) |
| Desbloquea | [T-020](../T-020-dashboard-bi/README.md), [T-025](../T-025-despliegue-nodo-iot/README.md) |
| Componentes | `apps/iot-simulator`, `infra/docker` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Simular la telemetría en ruta de los 50 camiones con contenedores Docker que escriben en la base de forma automática.

## Referencias

Leer antes de empezar:

- [`docs/API.md`](../../docs/API.md) §2
- [`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) §4.2
- [`docs/ADR/0006`](../../docs/ADR/README.md)
- [`docs/ADR/0014`](../../docs/ADR/README.md)
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) puntos abiertos 2 y 3
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Programa Python en `apps/iot-simulator` con su `Dockerfile`, configurado solo por variables de entorno.
- Cada contenedor simula un grupo de camiones; cinco contenedores cubren `CAM-001` a `CAM-050` sin solaparse.
- Por camión y por ciclo: avance sobre una ruta de la macrozona sur, temperatura cercana a la consigna, consumo de combustible y episodios ocasionales de ruptura de frío que duran varios ciclos.
- `alerta_ruptura_frio` calculada contra el rango de `iot_umbral_camion`.
- Inserta en `telemetria` y, al iniciar un episodio, en `alerta`; no repite la alerta mientras siga abierta.
- Reintentos con espera ante errores de conexión y registro legible en la salida estándar.
- Servicio del simulador en `compose.dev.yml` y `infra/docker/compose.iot.yml` con cinco réplicas y reinicio automático.
- Tests unitarios de la generación de lecturas y del cálculo de la alerta.
- `apps/iot-simulator/README.md` con variables y comandos.

## Fuera de alcance

- Despliegue en EC2 (T-025).
- Sensores reales.

## Criterios de aceptación

- [x] Con el simulador arriba, `telemetria` recibe 50 filas nuevas cada 30 segundos.
- [x] Cada fila tiene los seis campos exigidos con sus tipos y rangos.
- [x] Forzando una temperatura fuera de rango se obtiene `alerta_ruptura_frio = true` y una fila en `alerta`.
- [x] Al detener la base y volver a levantarla, el simulador se recupera solo.
- [x] El simulador se conecta como `iot_writer` y no usa ninguna otra credencial.
- [x] `docker logs` muestra las lecturas de forma comprensible para la defensa.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Incluir una forma de provocar una ruptura a pedido (variable de entorno o señal) para la demo. **Hecho:** `docker kill -s USR1 cofrial-iot-<g>-1` (primer camión libre del grupo, o los de `IOT_FORZAR_RUPTURA`) y `IOT_FORZAR_RUPTURA=CAM-011,…` al arrancar. La ruptura forzada sale del rango en la lectura siguiente; las naturales suben de a poco.
- **Verificación (2026-11-11)** sobre el entorno Docker local:
  - 22 tests unitarios en verde y `ruff format --check` / `ruff check` limpios (`docker build --target test …` + `docker run --rm cofrial/iot-simulator:test`).
  - Cinco réplicas arriba: cuatro ventanas seguidas de 30 s con 50 filas y 50 camiones distintos cada una en `telemetria`. Mínimos y máximos dentro de los `CHECK` (temp 1,5 a 6,3 °C, lat -40,5 a -37,8, long -73,4 a -71,2, combustible 46 a 99).
  - `SIGUSR1` a `iot-2`: CAM-011 marcó `alerta_ruptura_frio = true` y creó una sola alerta (`ruptura_frio`, `alta`, `abierta`) aunque siguió fuera de rango los ciclos siguientes. Con `IOT_FORZAR_RUPTURA=CAM-005` la alerta se creó en la primera lectura.
  - Base detenida 46 s (`docker compose stop db` / `start db`): el simulador registró `AdminShutdown` y luego `failed to resolve host 'db'` con esperas 1, 2, 4, 8, 16 s, y reconectó 3 s después de que la base volvió, sin reiniciar el contenedor.
  - `pg_stat_activity`: las cinco conexiones son `iot_writer` (`application_name` `iot-simulator-grupo-N`).
- **Diseño:** las réplicas son cinco servicios (`iot-1` … `iot-5`) con `IOT_GRUPO` distinto, porque `deploy.replicas` daría el mismo entorno a todas y se solaparían. Cada lote (10 lecturas + alertas) va en una transacción; las lecturas usan `now()` de la base (UTC). `compose.iot.yml` solo pasa variables `IOT_*`; en local los contenedores reciben el `.env` completo, como los demás servicios, pero el código solo lee las `IOT_*` y `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_SSLMODE`.
- **Supuestos:** rango por defecto 0 a 5 °C (refrigerado) cuando el camión no tiene despacho en ruta, que hoy es el caso de todos los camiones sembrados. Los dos camiones inactivos del seeder siguen transmitiendo desde la bodega de Temuco, para tener 50 filas por ciclo. Con `IOT_PROB_RUPTURA_FRIO=0.02` por camión y ciclo hay en promedio un episodio nuevo por ciclo en toda la flota: útil para la demo, pero genera muchas alertas; bájalo si molesta en el BI.
- **Pendiente fuera de alcance:** no hay workflow de CI para `apps/iot-simulator` (lo natural es correr la imagen `test` en `.github/workflows/`). `compose.iot.yml` se validó con `docker compose config`, pero no se ejecutó contra una base remota: eso es T-025.
- Las pruebas dejaron filas de telemetría y alertas abiertas en la base local de desarrollo.
