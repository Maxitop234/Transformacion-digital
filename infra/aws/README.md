# infra/aws · Despliegue en AWS Academy

Scripts o plantillas para crear la infraestructura del [diagrama de arquitectura](../../docs/ARCHITECTURE.md#3-infraestructura-en-aws) y los procedimientos para operarla. Todavía vacío.

| Pieza | Tarea |
| --- | --- |
| VPC, subredes, rutas y Security Groups | [T-022](../../tasks/T-022-red-aws/README.md) |
| RDS PostgreSQL Multi-AZ | [T-023](../../tasks/T-023-base-de-datos-rds/README.md) |
| EC2 web y Application Load Balancer | [T-024](../../tasks/T-024-despliegue-web-y-balanceador/README.md) |
| Nodo IoT | [T-025](../../tasks/T-025-despliegue-nodo-iot/README.md) |
| CloudWatch, SNS y respaldos | [T-026](../../tasks/T-026-monitoreo-y-respaldos/README.md) |

## Antes de empezar

- Región `us-east-1`. Las credenciales del Learner Lab son temporales: cárgalas en la terminal, no en archivos del repositorio.
- Confirma qué permite la cuenta (Multi-AZ, tipos de instancia, IAM). Cualquier desviación del diseño se registra como ADR y se anota aquí.
- Detén el laboratorio al terminar cada sesión para no gastar créditos.
- Los recursos se nombran con el prefijo `cofrial-`.
