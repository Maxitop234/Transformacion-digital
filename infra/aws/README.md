# infra/aws · Despliegue en AWS Academy

Plantillas de CloudFormation para crear la infraestructura del [diagrama de arquitectura](../../docs/ARCHITECTURE.md#3-infraestructura-en-aws) y los procedimientos para operarla (ADR-0018). Cada pieza es un stack propio; los siguientes leen los identificadores de los anteriores con `Fn::ImportValue`.

| Pieza | Stack | Plantilla | Tarea |
| --- | --- | --- | --- |
| VPC, subredes, rutas y Security Groups | `cofrial-red` | [`red.yaml`](red.yaml) | [T-022](../../tasks/T-022-red-aws/README.md) |
| RDS PostgreSQL 16 Multi-AZ | `cofrial-datos` | [`datos.yaml`](datos.yaml) | [T-023](../../tasks/T-023-base-de-datos-rds/README.md) |
| Dos EC2 web y Application Load Balancer | `cofrial-web` | [`web.yaml`](web.yaml) | [T-024](../../tasks/T-024-despliegue-web-y-balanceador/README.md) |
| Nodo IoT | `cofrial-iot` | [`iot.yaml`](iot.yaml) | [T-025](../../tasks/T-025-despliegue-nodo-iot/README.md) |
| SNS y alarmas de CloudWatch | `cofrial-monitoreo` | [`monitoreo.yaml`](monitoreo.yaml) | [T-026](../../tasks/T-026-monitoreo-y-respaldos/README.md) |

| Script | Qué hace |
| --- | --- |
| [`secretos.sh`](secretos.sh) | Crea los secretos de producción en Parameter Store (solo los que faltan) |
| [`certificado.sh`](certificado.sh) | Genera el certificado autofirmado y lo importa a ACM |
| [`verificar-red.sh`](verificar-red.sh) | Comprueba en la cuenta las reglas de seguridad de la red |
| [`desplegar.sh`](desplegar.sh) | Actualiza las EC2 con la última versión de GitHub, sin cortar el servicio |
| [`ejecutar.sh`](ejecutar.sh) | Ejecuta un comando dentro de una EC2 (sin SSH) |
| [`cargar-demo.sh`](cargar-demo.sh) | Carga los datos de demostración y les pone la contraseña de demo de AWS |
| [`arranque/`](arranque) | Lo que corre dentro de cada EC2: instala Docker, escribe los `.env` y levanta los contenedores |

Todos los comandos se ejecutan desde la raíz del repositorio.

## Cómo funciona

- **Código:** cada EC2 clona este repositorio (público) desde GitHub en `/opt/cofrial` y construye sus imágenes ahí ([`Dockerfile.prod`](../docker/web/Dockerfile.prod), [`compose.prod.yml`](../docker/compose.prod.yml), [`compose.iot.yml`](../docker/compose.iot.yml)). No hay registro de imágenes.
- **Secretos:** viven en SSM Parameter Store como `SecureString` bajo `/cofrial/prod/` (capa estándar, sin costo). Las instancias los leen con el rol `LabRole` y escriben los `.env` en `/etc/cofrial` (permisos `600`, fuera del repositorio). Cada servicio recibe solo su archivo: `app.env`, `grafana.env`, `migracion.env` (solo en `cofrial-web-1`) e `iot.env` (solo en el nodo IoT).
- **Migraciones:** solo `cofrial-web-1` migra (`COFRIAL_MIGRA=1`), con el usuario maestro, y reaplica [`db/aplicar.sh`](../../db/aplicar.sh). Las migraciones corren cada vez que arranca su contenedor `web` y son idempotentes.
- **Acceso a las instancias:** por Run Command de Systems Manager (`ejecutar.sh`), sin SSH. El puerto 22 sigue abierto solo a la IP del equipo para emergencias, con la llave `vockey` del laboratorio.
- **Reinicios:** Docker arranca con la instancia y todos los contenedores usan `restart: unless-stopped`; cuando el laboratorio vuelve a encender las EC2, todo queda operativo sin pasos manuales.

## Antes de empezar (cada sesión)

1. En AWS Academy abre el curso → **Learner Lab** → **Start Lab** y espera el punto verde.
2. En **AWS Details → AWS CLI → Show** copia el bloque de credenciales a `~/.aws/credentials`, **fuera del repositorio**. El archivo debe empezar con la línea `[default]`. Las credenciales cambian en cada sesión.
3. La primera vez, fija la región:

   ```bash
   aws configure set region us-east-1
   ```

4. Comprueba el acceso:

   ```bash
   aws sts get-caller-identity
   ```

## Crear todo desde cero

El orden importa: cada stack usa las exportaciones del anterior. Tiempo total aproximado: 35 minutos (RDS Multi-AZ tarda unos 15).

1. Red (un minuto):

   ```bash
   aws cloudformation deploy --stack-name cofrial-red --template-file infra/aws/red.yaml --parameter-overrides IpEquipo="$(curl -s https://checkip.amazonaws.com)/32" --tags proyecto=cofrial
   ```

2. Secretos y certificado:

   ```bash
   infra/aws/secretos.sh
   ```

   ```bash
   infra/aws/certificado.sh
   ```

3. Base de datos:

   ```bash
   aws cloudformation deploy --stack-name cofrial-datos --template-file infra/aws/datos.yaml --tags proyecto=cofrial
   ```

4. EC2 web y balanceador. Cada instancia tarda unos 10 minutos más en construir la aplicación después de que el stack termina:

   ```bash
   aws cloudformation deploy --stack-name cofrial-web --template-file infra/aws/web.yaml --tags proyecto=cofrial
   ```

5. Nodo IoT:

   ```bash
   aws cloudformation deploy --stack-name cofrial-iot --template-file infra/aws/iot.yaml --tags proyecto=cofrial
   ```

6. Monitoreo. AWS envía un correo de confirmación: hay que pulsar **Confirm subscription**.

   ```bash
   aws cloudformation deploy --stack-name cofrial-monitoreo --template-file infra/aws/monitoreo.yaml --parameter-overrides CorreoAlertas=correo@del-equipo --tags proyecto=cofrial
   ```

7. Catálogos y datos de demostración (una vez; los catálogos también se pueden sembrar solos con `php artisan db:seed --class=CatalogoSeeder --force`):

   ```bash
   infra/aws/ejecutar.sh cofrial-web-1 /opt/cofrial/infra/aws/cargar-demo.sh
   ```

Si una EC2 web quedó a medio arrancar (por ejemplo, porque terminó la sesión del laboratorio durante el primer arranque), basta con `infra/aws/desplegar.sh web`.

### Clave de la IA

`secretos.sh` deja `AI_API_KEY` vacía (`-`): el asistente responde «no disponible» y los tickets quedan para clasificación manual. Para activarla, quien tenga la clave la carga sin que quede en el historial de la terminal y luego redespliega:

```bash
read -rs CLAVE && aws ssm put-parameter --name /cofrial/prod/AI_API_KEY --type SecureString --overwrite --value "$CLAVE"; unset CLAVE
```

```bash
infra/aws/desplegar.sh web
```

### Cuentas de demo en AWS

Son las mismas de [`apps/web/README.md`](../../apps/web/README.md#usuarios-de-demo), pero con otra contraseña: el sitio está abierto a internet y `cofrial-demo` está publicada en el repositorio. Para verla:

```bash
aws ssm get-parameter --name /cofrial/prod/DEMO_PASSWORD --with-decryption --query Parameter.Value --output text
```

La contraseña de Grafana (`admin`) está en `/cofrial/prod/GF_SECURITY_ADMIN_PASSWORD`.

## Operar

### Direcciones

```bash
aws cloudformation describe-stacks --stack-name cofrial-web --query "Stacks[0].Outputs[?OutputKey=='Url'].OutputValue" --output text
```

El portal está en esa URL, el CRM en `/crm`, Grafana en `/bi` y la salud en `/health`. El certificado es autofirmado: el navegador mostrará un aviso que hay que aceptar.

### Actualizar a la última versión de `main`

```bash
infra/aws/desplegar.sh web
```

```bash
infra/aws/desplegar.sh iot
```

Las EC2 web se actualizan de a una: cada instancia sale del balanceador, se reconstruye y vuelve solo cuando está sana, así que el sitio no se corta. `cofrial-web-1` va primero porque migra.

### Comandos de verificación (útiles en la defensa)

Contenedores del simulador:

```bash
infra/aws/ejecutar.sh cofrial-iot 'docker ps --format "{{.Names}} {{.Status}}"'
```

Últimas lecturas de un grupo de camiones:

```bash
infra/aws/ejecutar.sh cofrial-iot 'docker logs --tail 10 cofrial-iot-iot-1-1'
```

Estado de los contenedores de una EC2 web:

```bash
infra/aws/ejecutar.sh cofrial-web-1 'cd /opt/cofrial && docker compose -f infra/docker/compose.prod.yml ps'
```

Salud de las instancias en el balanceador:

```bash
aws elbv2 describe-target-health --target-group-arn "$(aws elbv2 describe-target-groups --names cofrial-web --query 'TargetGroups[0].TargetGroupArn' --output text)" --query 'TargetHealthDescriptions[].[Target.Id,TargetHealth.State]' --output text
```

Estado de las alarmas:

```bash
aws cloudwatch describe-alarms --alarm-name-prefix cofrial- --query 'MetricAlarms[].[AlarmName,StateValue]' --output text
```

Provocar una ruptura de frío en el grupo 2 (demo):

```bash
infra/aws/ejecutar.sh cofrial-iot 'docker kill -s USR1 cofrial-iot-iot-2-1'
```

### Al terminar cada sesión

Las EC2 se detienen solas al terminar la sesión y vuelven a arrancar al iniciar la siguiente. **RDS no se detiene sola**: para no gastar presupuesto entre sesiones, detenla a mano (AWS la vuelve a encender sola si pasa 7 días detenida):

```bash
aws rds stop-db-instance --db-instance-identifier cofrial-db
```

Y al volver, antes de usar el sitio (tarda unos 5 minutos):

```bash
aws rds start-db-instance --db-instance-identifier cofrial-db
```

### Borrar todo

En orden inverso al de creación. Al borrar `cofrial-datos` queda un snapshot final de la base (`DeletionPolicy: Snapshot`).

```bash
for s in cofrial-monitoreo cofrial-iot cofrial-web cofrial-datos cofrial-red; do aws cloudformation delete-stack --stack-name $s && aws cloudformation wait stack-delete-complete --stack-name $s; done
```

## Runbook de alarmas

Las cuatro alarmas avisan por correo (tópico `cofrial-alarmas`) al entrar en `ALARM` y al volver a `OK`.

| Alarma | Qué significa | Qué hacer |
| --- | --- | --- |
| `cofrial-web-no-saludable` | Hay menos de dos EC2 web sanas en el balanceador. El sitio sigue en línea con la otra. | 1. Ver qué instancia falla con el comando de salud del balanceador. 2. Si está detenida, encenderla con `aws ec2 start-instances`; vuelve sola en ~1 minuto. 3. Si está encendida, revisar sus contenedores con `ejecutar.sh … compose ps` y los registros con `… compose logs --tail 50 web`. 4. Si no se recupera, `desplegar.sh web`. |
| `cofrial-ec2-chequeo-fallido` | AWS detectó una falla de hardware o del sistema operativo en una EC2. | Detener y volver a encender la instancia (no basta reiniciar: así cambia de host físico). Los contenedores vuelven solos. |
| `cofrial-ec2-cpu-alta` | Una EC2 pasó 10 minutos sobre 80 % de CPU. | Revisar `docker stats` con `ejecutar.sh`. Si es el worker de colas o un build, esperar; si es tráfico, considerar un tipo de instancia mayor (dentro de los límites del laboratorio). |
| `cofrial-rds-poco-espacio` | Quedan menos de 2 GB libres en la base. | Revisar el tamaño de `telemetria` (≈ 17 MB/día). Purgar lecturas antiguas o aumentar `AllocatedStorage` en `datos.yaml` (máximo 100 GB en el laboratorio) y redesplegar el stack. |

### Restaurar la base a un punto en el tiempo

Los respaldos automáticos se conservan 7 días y permiten restaurar a cualquier segundo hasta unos 5 minutos antes del momento actual (`LatestRestorableTime`). La restauración crea **otra** instancia; la original no se toca.

1. Elegir el momento (UTC) y restaurar:

   ```bash
   aws rds restore-db-instance-to-point-in-time --source-db-instance-identifier cofrial-db --target-db-instance-identifier cofrial-db-restaurada --restore-time 2026-11-15T18:27:00Z --db-instance-class db.t3.micro --multi-az --db-subnet-group-name cofrial-datos --vpc-security-group-ids "$(aws cloudformation list-exports --query "Exports[?Name=='cofrial-red-sg-db'].Value" --output text)" --db-parameter-group-name cofrial-postgres16 --no-publicly-accessible --storage-type gp2
   ```

   ```bash
   aws rds wait db-instance-available --db-instance-identifier cofrial-db-restaurada
   ```

2. Comprobar los datos desde una EC2 web, conectándose al endpoint nuevo (`aws rds describe-db-instances --db-instance-identifier cofrial-db-restaurada --query 'DBInstances[0].Endpoint.Address'`).
3. Apuntar la aplicación a la base restaurada: cambiar `COFRIAL_DB_HOST` en `/etc/cofrial/despliegue.conf` de las tres EC2 y ejecutar `desplegar.sh web` y `desplegar.sh iot`. Para dejarlo permanente, la base restaurada debe pasar a ser la del stack: importarla a `cofrial-datos` o renombrar instancias (`modify-db-instance --new-db-instance-identifier`).

Para volver a un snapshot manual se usa `restore-db-instance-from-db-snapshot` con los mismos parámetros. Snapshot manual antes de la defensa:

```bash
aws rds create-db-snapshot --db-instance-identifier cofrial-db --db-snapshot-identifier cofrial-db-manual-$(date +%Y-%m-%d)
```

## Restricciones del Learner Lab

Revisadas el 2026-11-12 en las instrucciones del laboratorio y comprobadas en la cuenta hasta el 2026-11-15.

| Restricción | Efecto en el diseño |
| --- | --- |
| Solo `us-east-1` y `us-west-2` | Se usa `us-east-1` (ADR-0001); ahí existe el par de claves `vockey`. |
| EC2 de tamaño nano a large, máximo 9 instancias y 32 vCPU en ejecución; 20 o más instancias desactivan la cuenta | El diseño usa 3 instancias y 6 vCPU. |
| RDS de clase micro a medium, almacenamiento `gp2` hasta 100 GB, sin monitoreo mejorado | `db.t3.micro`, `gp2`, monitoreo mejorado desactivado. **Multi-AZ sí está permitido** (comprobado: primaria en us-east-1b, réplica en us-east-1a). |
| No se pueden crear usuarios ni roles de IAM | Las EC2 usan el perfil existente `LabInstanceProfile` (rol `LabRole`). Ese rol es el mismo para todas, así que no se puede limitar qué secretos lee cada instancia (ver `docs/SECURITY.md` §12). |
| Las EC2 se detienen al terminar la sesión y vuelven con otra IP pública | Nada depende de la IP de una EC2, solo del DNS del balanceador. Un primer arranque interrumpido por el fin de sesión se completa con `desplegar.sh`. |
| RDS **no** se detiene sola al terminar la sesión, y AWS la vuelve a encender si pasa 7 días detenida | Detenerla a mano al terminar cada día (ver «Al terminar cada sesión»). |
| Si se agota el presupuesto, la cuenta se desactiva y se pierde todo | Infraestructura en stacks que se borran y recrean con un comando; nada de NAT Gateway. |
| CloudFormation, Parameter Store, Run Command y ACM (importar) están permitidos | Son las herramientas elegidas (ADR-0018). La cuenta trae un stack propio del laboratorio (`c…`) que no se toca. |
| No hay `jq` en todos los equipos | Los scripts usan solo `aws --query` y Python 3. |
