# ADR-0009 · Objetivos de disponibilidad y respaldo

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D1 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 09 |

## Contexto

Hoy una falla detiene la facturación y puede perder hasta 7 días de datos.

## Decisión

Dos EC2 web tras el ALB, RDS Multi-AZ, respaldos automáticos de 7 días con recuperación a un punto en el tiempo y un snapshot manual antes de la defensa. Meta: RPO ≤ 5 min y RTO ≤ 15 min.

## Alternativas consideradas

- Una sola EC2 y base Single-AZ: cerca de la mitad del costo por hora, sin tolerancia a fallas.

## Consecuencias

- La alta disponibilidad casi duplica el costo por hora.
- Se justifica porque Cofrial abastece a hospitales y hoy su recuperación tarda más de 24 h.
- Los objetivos deben comprobarse con una prueba de falla antes de la defensa.
