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

- [x] `docker ps` muestra cinco contenedores del simulador en ejecución.
- [x] En RDS aparecen unas 50 filas nuevas de telemetría cada 30 segundos.
- [x] Tras reiniciar la instancia, los contenedores vuelven solos.
- [x] El nodo no tiene la contraseña de `app_rw` ni la clave de la IA.
- [x] La carga del simulador no afecta el tiempo de respuesta del portal.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Stack `cofrial-iot` ([`infra/aws/iot.yaml`](../../infra/aws/iot.yaml)): EC2 `cofrial-iot` t3.small en `publica-a` con `sg-iot`, EBS cifrado. El user data clona el repositorio y llama a [`infra/aws/arranque/iot.sh`](../../infra/aws/arranque/iot.sh), que escribe `/etc/cofrial/iot.env` (solo las seis variables `IOT_DB_*`, con `sslmode=require`) y construye la imagen en el nodo.
- Verificado: cinco contenedores `cofrial-iot-iot-N-1` en ejecución; en RDS, 50 filas de 50 camiones distintos en cada ventana de 30 s; tras `reboot-instances` los cinco contenedores volvieron solos. El nodo no tiene en disco la contraseña de `app_rw` ni la clave de la IA (pero ver el riesgo de `LabRole` en `SECURITY.md` §12).
- Tiempo de respuesta del portal medido desde Chile (10 peticiones): `/` 0,64 s con los simuladores detenidos y 0,58 s con ellos; `/catalogo` 0,71 s y 0,74 s. La diferencia está dentro de la variación de red; el tiempo lo domina la distancia a us-east-1.
- Comandos para la defensa (contenedores, lecturas, ruptura de frío) en [`infra/aws/README.md`](../../infra/aws/README.md#comandos-de-verificación-útiles-en-la-defensa).
