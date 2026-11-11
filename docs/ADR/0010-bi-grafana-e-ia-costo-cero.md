# ADR-0010 · Grafana OSS para BI e IA externa de capa gratuita

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D8 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 10 |

## Contexto

El dashboard debe cruzar ventas con alertas de frío en tiempo real y la IA debe ser gratuita.

## Decisión

Grafana OSS en cada EC2 web, usando la misma RDS como base interna para que el dashboard sea igual en ambas, y leyendo los datos con `bi_reader`. API de IA con capa gratuita, llamada desde el worker de colas para clasificar tickets y atender un asistente, sin enviarle RUT ni nombres.

## Alternativas consideradas

- Power BI Desktop: exigiría exponer la base a internet.
- Amazon QuickSight: se cobra por usuario.

## Consecuencias

- Los dashboards se versionan como archivos en el repositorio.
- La API gratuita tiene límites de cuota: si no responde, el ticket queda para clasificación manual sin bloquear la venta.
- El proveedor concreto se decide en ADR-0015.
