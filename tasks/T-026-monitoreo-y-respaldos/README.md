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

- [ ] Detener una instancia web dispara un correo de alarma.
- [ ] Las cuatro alarmas existen y están en estado OK en operación normal.
- [ ] Se probó al menos una restauración a un punto en el tiempo, o se documentó por qué no fue posible en el laboratorio.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Recortable si el tiempo es crítico: es la tarea de menor peso en la pauta.
