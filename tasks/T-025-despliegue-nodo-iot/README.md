# T-025 · Despliegue del nodo IoT

| Campo | Valor |
| --- | --- |
| Fase | 5 · Despliegue en AWS |
| Dimensión de la pauta | 3 |
| Depende de | [T-019](../T-019-simulador-iot/README.md), [T-022](../T-022-red-aws/README.md), [T-023](../T-023-base-de-datos-rds/README.md) |
| Desbloquea | [T-026](../T-026-monitoreo-y-respaldos/README.md), [T-027](../T-027-ensayo-de-defensa/README.md) |
| Componentes | `infra/aws`, `infra/docker` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Correr los contenedores del simulador en un nodo EC2 dedicado, inyectando telemetría hacia RDS.

## Referencias

Leer antes de empezar:

- [`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) §3.3
- [`docs/ADR/0006`](../../docs/ADR/README.md)
- [`docs/API.md`](../../docs/API.md) §2
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- EC2 t3.small en `publica-a` con `sg-iot` y Docker.
- Imagen del simulador construida en el nodo o descargada de un registro.
- `compose.iot.yml` con cinco contenedores, conectados a RDS con TLS como `iot_writer`.
- Reinicio automático de los contenedores al arrancar la instancia.
- Comandos de verificación para la defensa documentados.

## Fuera de alcance

- Alta disponibilidad del nodo IoT.

## Criterios de aceptación

- [ ] `docker ps` muestra cinco contenedores del simulador en ejecución.
- [ ] En RDS aparecen unas 50 filas nuevas de telemetría cada 30 segundos.
- [ ] Tras reiniciar la instancia, los contenedores vuelven solos.
- [ ] El nodo no tiene la contraseña de `app_rw` ni la clave de la IA.
- [ ] La carga del simulador no afecta el tiempo de respuesta del portal.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
