# ADR-0005 · Balanceador con dos EC2 web sin estado

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D1, D6 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 05 |

## Contexto

Hoy una falla del servidor detiene ventas y facturación. El enunciado exige el backend sobre EC2.

## Decisión

Application Load Balancer en las dos subredes públicas con dos EC2 t3.medium, una por zona, atendiendo al mismo tiempo. El ALB revisa `/health` cada 30 s y saca de rotación la instancia que falla. Las sesiones y la cola de Laravel se guardan en la base para que cualquier instancia atienda a cualquier usuario.

## Alternativas consideradas

- Una sola EC2: repetiría el punto único de falla.
- Elastic Beanstalk o Lambda: menos control sobre los contenedores exigidos.

## Consecuencias

- Se paga el doble de cómputo web más el balanceador.
- La caída de una instancia o de una zona no se nota.
- El DNS del ALB no cambia cuando el laboratorio se reinicia.
- La aplicación no puede guardar estado en el disco de la instancia (sesiones, caché, archivos subidos).
