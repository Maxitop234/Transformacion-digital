# T-022 · Red en AWS

| Campo | Valor |
| --- | --- |
| Fase | 5 · Despliegue en AWS |
| Dimensión de la pauta | 1 |
| Depende de | Ninguna |
| Desbloquea | [T-023](../T-023-base-de-datos-rds/README.md), [T-024](../T-024-despliegue-web-y-balanceador/README.md), [T-025](../T-025-despliegue-nodo-iot/README.md) |
| Componentes | `infra/aws` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Crear la red aislada donde vivirá el sistema en AWS Academy.

## Referencias

Leer antes de empezar:

- [`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) §3.1 y §3.2
- [`docs/ADR/0001`](../../docs/ADR/README.md) a 0004
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §3
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- VPC `10.20.0.0/16` con las cuatro subredes en dos zonas, Internet Gateway y las tablas de rutas `rt-publica` y `rt-datos`.
- Security Groups `sg-alb`, `sg-web`, `sg-iot` y `sg-db` encadenados.
- Infraestructura reproducible: script de AWS CLI o plantilla en `infra/aws`, parametrizado con la IP del equipo.
- `infra/aws/README.md` con el procedimiento y con las restricciones del Learner Lab encontradas.

## Fuera de alcance

- Instancias y base de datos (T-023 a T-025).

## Criterios de aceptación

- [ ] La tabla de rutas de las subredes de datos no tiene ruta a internet.
- [ ] Ningún Security Group expone 22 ni 5432 a `0.0.0.0/0`.
- [ ] `sg-db` solo acepta 5432 desde `sg-web` y `sg-iot`.
- [ ] La red se puede destruir y volver a crear siguiendo el README.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Confirmar primero qué permite la cuenta del Learner Lab; cualquier desviación del diseño se registra en un ADR.
