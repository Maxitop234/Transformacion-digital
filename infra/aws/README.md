# infra/aws · Despliegue en AWS Academy

Plantillas de CloudFormation para crear la infraestructura del [diagrama de arquitectura](../../docs/ARCHITECTURE.md#3-infraestructura-en-aws) y los procedimientos para operarla (ADR-0018). Cada pieza es un stack propio; los siguientes leen los identificadores de la red con `Fn::ImportValue`.

| Pieza | Stack | Plantilla | Tarea |
| --- | --- | --- | --- |
| VPC, subredes, rutas y Security Groups | `cofrial-red` | [`red.yaml`](red.yaml) | [T-022](../../tasks/T-022-red-aws/README.md) |
| RDS PostgreSQL Multi-AZ | — | — | [T-023](../../tasks/T-023-base-de-datos-rds/README.md) |
| EC2 web y Application Load Balancer | — | — | [T-024](../../tasks/T-024-despliegue-web-y-balanceador/README.md) |
| Nodo IoT | — | — | [T-025](../../tasks/T-025-despliegue-nodo-iot/README.md) |
| CloudWatch, SNS y respaldos | — | — | [T-026](../../tasks/T-026-monitoreo-y-respaldos/README.md) |

## Antes de empezar

1. En AWS Academy abre el curso → **Learner Lab** → **Start Lab** y espera el punto verde.
2. En **AWS Details → AWS CLI → Show** copia el bloque de credenciales a `~/.aws/credentials`, **fuera del repositorio**. El archivo debe empezar con la línea `[default]`. Las credenciales cambian en cada sesión del laboratorio: vuelve a copiarlas cada vez.
3. Fija la región una sola vez:

   ```bash
   aws configure set region us-east-1
   ```

4. Comprueba el acceso:

   ```bash
   aws sts get-caller-identity
   ```

Los recursos se nombran con el prefijo `cofrial-`. Nada de esta carpeta contiene secretos.

## Red (`cofrial-red`)

Crea la VPC `10.20.0.0/16`, las subredes `publica-a/b` y `datos-a/b`, el Internet Gateway, las tablas `cofrial-rt-publica` y `cofrial-rt-datos` (esta última solo con la ruta local) y los grupos `cofrial-sg-alb`, `cofrial-sg-web`, `cofrial-sg-iot` y `cofrial-sg-db`. Su único parámetro es la IP del equipo, el único origen admitido para SSH. La red no tiene costo por hora: no hay NAT Gateway (ADR-0003).

### Crear o actualizar

```bash
aws cloudformation deploy --stack-name cofrial-red --template-file infra/aws/red.yaml --parameter-overrides IpEquipo="$(curl -s https://checkip.amazonaws.com)/32" --tags proyecto=cofrial
```

Se ejecuta desde la raíz del repositorio y tarda alrededor de un minuto. El mismo comando sirve para actualizar: si la IP del equipo cambió (ADR-0004), solo reemplaza la regla de SSH de `sg-web` y `sg-iot`.

### Verificar

```bash
infra/aws/verificar-red.sh
```

Solo lee. Muestra las subredes y rutas, y revisa que `rt-datos` no tenga ruta a internet, que ningún grupo exponga 22 ni 5432 a `0.0.0.0/0` y que `sg-db` solo admita 5432 desde `sg-web` y `sg-iot`. Sale con código 1 si algo falla.

### Destruir

```bash
aws cloudformation delete-stack --stack-name cofrial-red
```

```bash
aws cloudformation wait stack-delete-complete --stack-name cofrial-red
```

CloudFormation no deja borrar la red mientras otro stack use sus exportaciones (`cofrial-red-*`): primero se borran la base, las EC2 y el balanceador, en orden inverso al de creación.

## Restricciones del Learner Lab

Revisadas el 2026-11-12 en las instrucciones del laboratorio y en la cuenta.

| Restricción | Efecto en el diseño |
| --- | --- |
| Solo `us-east-1` y `us-west-2` | Se usa `us-east-1` (ADR-0001); ahí existe el par de claves `vockey`. |
| EC2 de tamaño nano a large, máximo 9 instancias y 32 vCPU en ejecución; 20 o más instancias desactivan la cuenta | El diseño usa 3 instancias y 6 vCPU. |
| RDS de clase micro a medium, almacenamiento `gp2` hasta 100 GB, sin monitoreo mejorado | `db.t3.micro` cabe. El monitoreo mejorado debe quedar desactivado. Multi-AZ no aparece ni permitido ni prohibido: se comprueba en T-023. |
| No se pueden crear usuarios ni roles de IAM | Las EC2 usan el perfil existente `LabInstanceProfile` (rol `LabRole`), que además permite entrar por Session Manager sin SSH. |
| Las EC2 se detienen al terminar la sesión y vuelven con otra IP pública | Nada depende de la IP de una EC2, solo del DNS del balanceador. |
| RDS **no** se detiene sola al terminar la sesión, y AWS la vuelve a encender si pasa 7 días detenida | Detenerla a mano al terminar cada día (se documentará en T-023). |
| Si se agota el presupuesto, la cuenta se desactiva y se pierde todo | Infraestructura en stacks que se borran y recrean con un comando; nada de NAT Gateway. |
| CloudFormation está permitido | Es la herramienta elegida (ADR-0018). La cuenta trae un stack propio del laboratorio (`c…`) que no se toca. |
| No hay `jq` en todos los equipos | Los scripts usan solo `aws --query` y Python 3. |

## Al terminar cada sesión

- Detén las EC2 y la RDS cuando existan (T-023 a T-025 agregan los comandos).
- La red puede quedar creada: no consume presupuesto.
