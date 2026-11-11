# infra · Infraestructura

| Carpeta | Contenido | Tareas |
| --- | --- | --- |
| [`aws/`](aws/README.md) | Red, RDS, EC2, balanceador, monitoreo y runbooks | T-022 a T-026 |
| [`docker/`](docker/README.md) | Dockerfiles y archivos Compose de desarrollo y producción | T-002, T-019, T-024, T-025 |
| [`grafana/`](grafana/README.md) | Datasource y dashboards versionados | T-020 |

El diseño está en [`docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md). Nada en esta carpeta contiene secretos: las credenciales se entregan por variables de entorno al ejecutar.
