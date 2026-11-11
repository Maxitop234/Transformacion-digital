# ADR-0011 · Monitoreo con CloudWatch y SNS

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D1, D4 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 11 |

## Contexto

Con el servidor actual nadie se entera de una falla hasta que algo se detiene.

## Decisión

Alarmas de CloudWatch con aviso por SNS al correo del equipo: instancia no saludable en el ALB, chequeo de estado fallido en una EC2, CPU sobre 80 % y poco espacio libre en RDS. Las alertas de frío las emite la aplicación al despachador.

## Alternativas consideradas

- Logs detallados en CloudWatch Logs: tienen costo por GB.

## Consecuencias

- Las métricas básicas son gratuitas.
- Los logs detallados quedan fuera del despliegue; el diagnóstico se hace con `docker logs` en la instancia.
