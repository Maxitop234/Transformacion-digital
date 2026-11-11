# ADR-0004 · Security Groups encadenados

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D5 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 04 |

## Contexto

El caso es un ejemplo de datos sin control de acceso.

## Decisión

`sg-web` solo acepta el puerto 80 desde `sg-alb`; `sg-db` solo acepta 5432 desde `sg-web` y `sg-iot`; SSH (22) solo desde la IP del equipo (`/32`). Las NACL se dejan por defecto.

## Alternativas consideradas

- Abrir puertos a `0.0.0.0/0`: más simple de depurar, pero expone SSH y la base a cualquier escáner.
- Reglas en NACL: un error en ellas corta el servicio.

## Consecuencias

- Las reglas entre grupos cuestan más de depurar.
- Ningún escáner de internet llega a SSH ni a la base.
- La IP del equipo cambia: hay que actualizar la regla de SSH en cada sesión de trabajo.
