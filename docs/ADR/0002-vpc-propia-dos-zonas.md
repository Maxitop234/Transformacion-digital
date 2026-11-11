# ADR-0002 · VPC propia en dos zonas de disponibilidad

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D1 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 02 |

## Contexto

Hoy todo vive en una red local plana con un solo servidor.

## Decisión

VPC propia `10.20.0.0/16` en dos zonas de disponibilidad, con cuatro subredes `/24`. El rango evita chocar con la VPC por defecto (`172.31.0.0/16`) y con la red local de la bodega.

## Alternativas consideradas

- VPC por defecto: todas sus subredes son públicas y dejarían la base expuesta.
- Una sola zona: repetiría el punto único de falla.

## Consecuencias

- Más configuración que con la VPC por defecto.
- Se gana aislamiento y tolerancia a la caída de una zona completa.
