# ADR-0006 · Nodo IoT dedicado con Docker, escribiendo directo en RDS

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D3, D7 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 06 |

## Contexto

El enunciado exige simular la telemetría de los camiones con Docker en un nodo dedicado.

## Decisión

EC2 t3.small con 5 contenedores de 10 camiones cada uno y una lectura cada 30 s. El simulador calcula `alerta_ruptura_frio` y escribe directo en RDS con el usuario `iot_writer`, que solo inserta telemetría y alertas.

## Alternativas consideradas

- Correr el simulador en las EC2 web: mezclaría la telemetría con las ventas.
- Enviar la telemetría por una API HTTP de la aplicación: agrega carga al portal y un punto más de falla para la demo.

## Consecuencias

- La ingesta no le quita CPU al portal.
- El nodo no se duplica: si falla, solo se pausa la simulación y los contenedores se reinician solos.
- Escribir directo exige credenciales de base en el contenedor; el daño se limita con un usuario de mínimo privilegio.
- El contrato de telemetría es el esquema de la tabla, no un endpoint (ver `docs/API.md`).
