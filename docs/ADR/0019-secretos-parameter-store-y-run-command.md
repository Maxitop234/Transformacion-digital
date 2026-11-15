# ADR-0019 · Secretos en Parameter Store y acceso a las EC2 por Run Command

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-15 |
| Drivers | D5, D7 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |

## Contexto

Las EC2 se crean desde plantillas (ADR-0018) y deben quedar listas sin pasos manuales, pero el `.env` de producción no puede estar en el repositorio ni en la plantilla (ADR-0008). Hay tres instancias que necesitan credenciales distintas y una base que solo es alcanzable desde la VPC, así que las migraciones, la carga de datos y las verificaciones deben ejecutarse dentro de ella. El laboratorio no permite crear roles de IAM; las instancias solo pueden usar `LabRole`, que incluye `AmazonSSMManagedInstanceCore`.

## Decisión

Los secretos de producción se generan al azar con `infra/aws/secretos.sh` y se guardan como `SecureString` en SSM Parameter Store bajo `/cofrial/prod/`, en la capa estándar. Cada EC2 los lee al arrancar con su rol y escribe en `/etc/cofrial` (permisos `600`) solo los `.env` de sus servicios; el `.env` fuera del repositorio de ADR-0008 se mantiene, pero se genera en el servidor. La contraseña maestra de RDS se entrega a CloudFormation con una referencia dinámica `{{resolve:ssm-secure:…}}`. Los comandos dentro de las instancias (despliegues, migraciones, verificación) se ejecutan con Run Command de Systems Manager (`desplegar.sh`, `ejecutar.sh`), sin SSH.

## Alternativas consideradas

- AWS Secrets Manager: rotación automática, pero cobra por secreto y por mes; para un prototipo con presupuesto limitado no aporta lo suficiente.
- Secretos en el user data de la plantilla: quedan visibles para cualquiera que lea la configuración de la instancia y en el historial de CloudFormation.
- Copiar el `.env` por SSH: depende de la IP pública de cada EC2, que cambia en cada sesión, y de la llave `vockey` en el equipo de quien despliega.

## Consecuencias

- Ninguna credencial de producción está en el repositorio, en las plantillas ni en el equipo de quien despliega; recrear una instancia no exige pasos manuales.
- Parameter Store en capa estándar y Run Command no tienen costo.
- Como todas las instancias comparten `LabRole`, no se puede limitar qué parámetros lee cada una: el nodo IoT solo escribe en disco la clave de `iot_writer`, pero su rol podría leer las demás. Se acepta como riesgo del laboratorio (`SECURITY.md` §12); con una cuenta propia, un rol por componente.
- Cambiar un secreto exige actualizar el parámetro y redesplegar (`desplegar.sh`); las contraseñas de los roles de base además se reaplican en RDS al migrar.
