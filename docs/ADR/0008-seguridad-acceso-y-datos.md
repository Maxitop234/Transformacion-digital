# ADR-0008 · Cifrado, secretos y mínimo privilegio

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D5 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 08 |

## Contexto

Clientes, precios y ubicación de conductores son datos sensibles o personales.

## Decisión

Cifrado KMS en RDS y EBS, TLS hacia la base y HTTPS en el ALB con certificado autofirmado importado a ACM. Credenciales en un archivo `.env` fuera del repositorio. Tres usuarios de base (`app_rw`, `iot_writer`, `bi_reader`) y cinco roles de aplicación: administrador, vendedor, despachador, cliente B2B y cliente B2C.

## Alternativas consideradas

- Un solo usuario de base para todo: menos trabajo, pero una credencial filtrada lo compromete todo.

## Consecuencias

- Mantener varios usuarios y permisos da más trabajo.
- Si se filtra una credencial, el daño queda contenido.
- Sin dominio propio, el navegador mostrará un aviso de certificado en la demo.
