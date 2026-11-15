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

- [x] El DNS del balanceador sirve el portal por HTTPS.
- [x] Ambas instancias aparecen saludables en el target group.
- [x] Al detener una instancia el sitio sigue respondiendo y la sesión iniciada no se pierde.
- [x] Tras reiniciar una instancia, la aplicación vuelve sola sin intervención.
- [x] `APP_DEBUG` está en falso y una página de error no muestra trazas.
- [ ] Una compra hecha por el balanceador queda guardada en RDS.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Stack `cofrial-web` ([`infra/aws/web.yaml`](../../infra/aws/web.yaml)): ALB `cofrial-alb` con HTTPS (TLS 1.2/1.3, certificado autofirmado de `certificado.sh` en ACM) y redirección 301 desde HTTP; target group `cofrial-web` con `/health` cada 10 s; EC2 `cofrial-web-1` (zona a, migra) y `cofrial-web-2` (zona b), t3.medium, Amazon Linux 2023, EBS gp3 cifrado, IMDSv2.
- Despliegue repetible: el user data clona el repositorio y llama a [`infra/aws/arranque/web.sh`](../../infra/aws/arranque/web.sh), que también usa [`desplegar.sh`](../../infra/aws/desplegar.sh) para actualizar. Imagen de producción [`Dockerfile.prod`](../../infra/docker/web/Dockerfile.prod) y [`compose.prod.yml`](../../infra/docker/compose.prod.yml). Los `.env` se generan en `/etc/cofrial` desde Parameter Store (ADR-0019).
- Laravel confía en las cabeceras `X-Forwarded-*` del balanceador (`trustProxies`, test `BalanceadorTest`) para generar enlaces `https`.
- Verificado: portal 200 por HTTPS, `/health` ok, Grafana en `/bi`, una ruta inexistente muestra la página de error sin trazas, cookie de sesión `secure` y `httponly`.
- Caída: al detener `cofrial-web-2` el sitio siguió respondiendo (36 de 40 peticiones 200; 4 errores 502/504 en los segundos en que el balanceador aún no la retiraba) y un token CSRF de la sesión abierta antes siguió siendo válido (302, no 419): las sesiones viven en la base. Con el chequeo bajado a 10 s, un reinicio de `cofrial-web-1` dio 4 de 60 errores. Al volver a encenderla, `cofrial-web-2` quedó sana sola en 45 s; `cofrial-web-1` también tras reiniciar.
- Un despliegue con `desplegar.sh web` saca cada instancia del balanceador antes de reconstruirla: 51 de 51 peticiones 200 durante el despliegue completo.
- El primer arranque quedó a medio camino porque terminó la sesión del laboratorio; se completó con `desplegar.sh web`. Ese script despliega primero `cofrial-web-1` porque crea los usuarios de base que necesita la otra.
- **Pendiente:** comprobar en RDS una compra hecha por el balanceador (la hace una persona del equipo en el navegador; las cuentas de demo de AWS usan `/cofrial/prod/DEMO_PASSWORD`).
