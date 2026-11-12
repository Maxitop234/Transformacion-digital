# ADR-0018 · Infraestructura de AWS como plantillas de CloudFormation

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-12 |
| Drivers | D7 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |

## Contexto

La infraestructura de AWS (T-022 a T-026) debe ser reproducible y parametrizable con la IP del equipo. El Learner Lab tiene un presupuesto limitado: si se agota, la cuenta se desactiva y se pierde todo. Las sesiones caducan y conviene poder destruir y recrear recursos sin pasos manuales. La cuenta no permite crear usuarios ni roles de IAM.

## Decisión

Cada pieza de la infraestructura se describe en una plantilla de CloudFormation en `infra/aws` y se despliega como un stack propio con el prefijo `cofrial-` (`cofrial-red` primero). Los stacks posteriores leen los identificadores de la red con `Fn::ImportValue` sobre las exportaciones `cofrial-red-*`. Se despliega con `aws cloudformation deploy` usando las credenciales temporales del laboratorio, sin rol de servicio propio. Las comprobaciones que no son parte de la plantilla van en scripts que solo leen (`verificar-red.sh`).

## Alternativas consideradas

- Script de AWS CLI que crea recurso por recurso: borrar exige otro script que recorra los recursos en orden inverso, y un fallo a mitad de camino deja recursos sueltos que consumen presupuesto.
- Terraform: obliga a instalar otra herramienta y a guardar un archivo de estado fuera del repositorio; el laboratorio no ofrece dónde guardarlo de forma compartida sin configurar más servicios.
- Consola web: no es reproducible ni revisable.

## Consecuencias

- Crear, actualizar y borrar una pieza completa es un comando; un fallo revierte el stack entero.
- Las plantillas son revisables en el repositorio y documentan la infraestructura real.
- Las exportaciones impiden borrar la red mientras otro stack la use: hay que destruir en orden inverso.
- Cambiar ciertos atributos (por ejemplo, el nombre de un Security Group) reemplaza el recurso; los cambios se revisan antes con un change set si afectan a la base.
