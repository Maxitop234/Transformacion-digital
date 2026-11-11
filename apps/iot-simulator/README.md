# apps/iot-simulator · Simulador de telemetría

Proceso que simula los sensores de los 50 camiones frigoríficos y escribe sus lecturas en PostgreSQL. Corre en contenedores Docker sobre un nodo EC2 dedicado. Todavía no está creado: se construye en [T-019](../../tasks/T-019-simulador-iot/README.md).

## Qué hace

Cada 30 segundos, por cada camión, inserta una fila en `telemetria` con los campos exigidos por el enunciado:

| Campo | Tipo |
| --- | --- |
| `camion_id` | String |
| `temp_camara` | Float |
| `lat` / `long` | Float |
| `combustible_pct` | Integer |
| `alerta_ruptura_frio` | Boolean |

Cuando una lectura inicia una ruptura de frío, crea además un registro en `alerta`. El contrato completo está en [`docs/API.md`](../../docs/API.md#2-contrato-de-telemetría-iot).

## Reglas propias de este componente

- Se configura solo con variables de entorno (`IOT_*` en [`.env.example`](../../.env.example)).
- Se conecta como `iot_writer`; no conoce ninguna otra credencial.
- No depende del código de `apps/web`, solo del esquema de `telemetria`, `alerta` y la vista `iot_umbral_camion`.
- Ante errores de conexión reintenta; no termina.

## Comandos

Se documentan aquí al cerrar T-019: construir la imagen, ejecutar, tests y cómo provocar una ruptura de frío para la demo.

## Referencias

[ADR-0006](../../docs/ADR/0006-nodo-iot-dedicado.md) · [ADR-0014](../../docs/ADR/0014-simulador-iot-python.md) · [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md#5-logística-e-iot)
