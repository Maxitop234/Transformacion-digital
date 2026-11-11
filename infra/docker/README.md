# infra/docker · Contenedores

Dockerfiles y archivos Compose. Todavía vacío.

| Archivo (por crear) | Uso | Tarea |
| --- | --- | --- |
| `web/Dockerfile`, `nginx/` | Imagen de la aplicación y configuración de Nginx | [T-002](../../tasks/T-002-entorno-local-docker/README.md) |
| `compose.dev.yml` | Entorno local completo: base, web, worker, Grafana y simulador | T-002, T-019 |
| `compose.prod.yml` | EC2 web: Nginx, aplicación, worker, planificador y Grafana | [T-024](../../tasks/T-024-despliegue-web-y-balanceador/README.md) |
| `compose.iot.yml` | Nodo IoT: cinco contenedores del simulador | [T-025](../../tasks/T-025-despliegue-nodo-iot/README.md) |

Todos los servicios de producción usan reinicio automático, porque las instancias del laboratorio se detienen y vuelven a arrancar.
