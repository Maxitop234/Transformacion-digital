# ADR-0017 · Telemetría sin particionar en el prototipo

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-10 |
| Drivers | D3 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |

## Contexto

ADR-0007 eligió PostgreSQL, entre otras razones, por el particionamiento por fecha de `telemetria`. Al escribir las migraciones (T-003) apareció el conflicto que señalaba el punto abierto 1 de `DATA_MODEL.md`: PostgreSQL exige que la clave primaria de una tabla particionada incluya la columna de partición, así que `telemetria` tendría PK `(id_telemetria, fecha_hora)` y la FK simple `alerta.id_telemetria` dejaría de ser posible.

El volumen del prototipo es acotado: 50 camiones con una lectura cada 30 s son unas 144 000 filas al día (~17 MB).

## Decisión

`telemetria` es una tabla normal, sin particiones, con PK `id_telemetria` (`BIGINT` identity) y el índice `(camion_id, fecha_hora DESC)` para las consultas del CRM y del BI («última lectura de cada camión», «lecturas del viaje»). `alerta.id_telemetria` conserva su FK simple.

El particionamiento por fecha queda como evolución si el volumen o la retención lo exigen (punto abierto 5 de `DATA_MODEL.md`).

## Alternativas consideradas

- Particionar por mes con PK `(id_telemetria, fecha_hora)` y agregar `fecha_hora` a `alerta` para la FK compuesta: duplica un dato en `alerta` y complica el modelo del Anexo A sin beneficio medible con este volumen.
- Particionar y eliminar la FK de `alerta`: se pierde integridad declarativa, que es un criterio de diseño del modelo.

## Consecuencias

- El modelo queda igual que el Anexo A y la integridad `alerta → telemetria` la garantiza la base.
- Una purga por fecha (retención) será un `DELETE` por rango en vez de soltar una partición; con el volumen del prototipo es aceptable.
- Si se adopta el particionamiento más adelante, hará falta un ADR nuevo y una migración que reconstruya la tabla.
