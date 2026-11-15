# T-026 · Monitoreo y respaldos

| Campo | Valor |
| --- | --- |
| Fase | 5 · Despliegue en AWS |
| Dimensión de la pauta | 1 |
| Depende de | [T-024](../T-024-despliegue-web-y-balanceador/README.md), [T-025](../T-025-despliegue-nodo-iot/README.md) |
| Desbloquea | Ninguna |
| Componentes | `infra/aws` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Enterarse de una falla de infraestructura antes que los usuarios y poder recuperar los datos.

## Referencias

Leer antes de empezar:

- [`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) §3.5 y §6
- [`docs/ADR/0009`](../../docs/ADR/README.md)
- [`docs/ADR/0011`](../../docs/ADR/README.md)
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Tópico SNS con el correo del equipo suscrito y confirmado.
- Alarmas de CloudWatch: instancia no saludable en el balanceador, chequeo de estado fallido en EC2, CPU sobre 80 % y poco espacio libre en RDS.
- Respaldos automáticos verificados y un snapshot manual previo a la defensa.
- Runbook breve en `infra/aws`: qué hacer ante cada alarma y cómo restaurar a un punto en el tiempo.

## Fuera de alcance

- Logs centralizados (tienen costo).

## Criterios de aceptación

- [x] Detener una instancia web dispara un correo de alarma.
- [x] Las cuatro alarmas existen y están en estado OK en operación normal.
- [x] Se probó al menos una restauración a un punto en el tiempo, o se documentó por qué no fue posible en el laboratorio.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Recortable si el tiempo es crítico: es la tarea de menor peso en la pauta.
- **Hecho (2026-11-15).** Stack `cofrial-monitoreo` ([`infra/aws/monitoreo.yaml`](../../infra/aws/monitoreo.yaml)): tópico `cofrial-alarmas` con el correo del equipo (suscripción confirmada) y las cuatro alarmas `cofrial-web-no-saludable`, `cofrial-ec2-chequeo-fallido`, `cofrial-ec2-cpu-alta` y `cofrial-rds-poco-espacio`, todas en `OK` en operación normal. Avisan al entrar en `ALARM` y al volver a `OK`.
- La alarma del balanceador mide `HealthyHostCount < 2` y no `UnHealthyHostCount`: en la primera prueba una instancia detenida quedó `unused` en el target group y la alarma no saltó. Con el cambio, detener `cofrial-web-2` la llevó a `ALARM` y SNS envió el correo; al encenderla volvió a `OK`.
- Las de chequeo de estado y CPU cubren las tres EC2 con una expresión `MAX` sobre sus métricas.
- Respaldos automáticos diarios (06:04 UTC) desde el 2026-11-12, retención de 7 días; snapshot manual `cofrial-db-manual-2026-11-15`.
- Restauración a un punto en el tiempo probada: se restauró a las 18:27:17 UTC en una instancia aparte (`cofrial-db-prueba-pitr`, Single-AZ), que tenía las 34 tablas, los 50 camiones y la última lectura de telemetría a las 18:27:14; luego se borró.
- Runbook de cada alarma y de la restauración en [`infra/aws/README.md`](../../infra/aws/README.md#runbook-de-alarmas).
