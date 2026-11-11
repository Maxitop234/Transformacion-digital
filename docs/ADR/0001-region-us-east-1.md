# ADR-0001 · Región us-east-1

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D7 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 01 |

## Contexto

El despliegue se evalúa en AWS Academy, que solo habilita algunas regiones.

## Decisión

Desplegar en `us-east-1` (N. Virginia), usando las zonas `us-east-1a` y `us-east-1b`.

## Alternativas consideradas

- São Paulo (`sa-east-1`): menor latencia y datos en Sudamérica, pero no está disponible en el Learner Lab.

## Consecuencias

- Se aceptan unos 100 ms más por ida y vuelta (estimado), irrelevantes para un portal web y lecturas cada 30 s.
- El diseño no depende de la región: se puede replicar en São Paulo o en una futura región en Chile.
