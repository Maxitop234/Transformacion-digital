# T-024 · Despliegue web y balanceador

| Campo | Valor |
| --- | --- |
| Fase | 5 · Despliegue en AWS |
| Dimensión de la pauta | 1, 2 |
| Depende de | [T-002](../T-002-entorno-local-docker/README.md), [T-022](../T-022-red-aws/README.md), [T-023](../T-023-base-de-datos-rds/README.md) |
| Desbloquea | [T-026](../T-026-monitoreo-y-respaldos/README.md), [T-027](../T-027-ensayo-de-defensa/README.md) |
| Componentes | `infra/aws`, `infra/docker` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Publicar el portal y el CRM en dos instancias detrás de un balanceador, corriendo de forma persistente.

## Referencias

Leer antes de empezar:

- [`docs/ARCHITECTURE.md`](../../docs/ARCHITECTURE.md) §3.3 y §6
- [`docs/ADR/0005`](../../docs/ADR/README.md)
- [`docs/ADR/0008`](../../docs/ADR/README.md)
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §13
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- `infra/docker/compose.prod.yml` con Nginx, aplicación, worker, planificador y Grafana, todos con reinicio automático.
- Dos EC2 t3.medium, una por zona, con Docker y volúmenes cifrados.
- Procedimiento de despliegue repetible (script o *user data*) que deja la instancia lista desde cero.
- Target group con health check `/health` y Application Load Balancer en las dos subredes públicas.
- Listener HTTPS con certificado autofirmado importado a ACM y redirección desde HTTP.
- `.env` de producción creado en el servidor, fuera del repositorio.

## Fuera de alcance

- Nodo IoT (T-025).
- Dominio propio.

## Criterios de aceptación

- [ ] El DNS del balanceador sirve el portal por HTTPS.
- [ ] Ambas instancias aparecen saludables en el target group.
- [ ] Al detener una instancia el sitio sigue respondiendo y la sesión iniciada no se pierde.
- [ ] Tras reiniciar una instancia, la aplicación vuelve sola sin intervención.
- [ ] `APP_DEBUG` está en falso y una página de error no muestra trazas.
- [ ] Una compra hecha por el balanceador queda guardada en RDS.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
