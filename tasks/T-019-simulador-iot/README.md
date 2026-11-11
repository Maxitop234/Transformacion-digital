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

- [ ] Con el simulador arriba, `telemetria` recibe 50 filas nuevas cada 30 segundos.
- [ ] Cada fila tiene los seis campos exigidos con sus tipos y rangos.
- [ ] Forzando una temperatura fuera de rango se obtiene `alerta_ruptura_frio = true` y una fila en `alerta`.
- [ ] Al detener la base y volver a levantarla, el simulador se recupera solo.
- [ ] El simulador se conecta como `iot_writer` y no usa ninguna otra credencial.
- [ ] `docker logs` muestra las lecturas de forma comprensible para la defensa.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Incluir una forma de provocar una ruptura a pedido (variable de entorno o señal) para la demo.
