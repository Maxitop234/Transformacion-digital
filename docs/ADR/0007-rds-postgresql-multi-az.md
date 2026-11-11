# ADR-0007 · RDS for PostgreSQL 16 Multi-AZ como base única

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D1, D2 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 07 |

## Contexto

Hay que reemplazar planillas y un servidor sin respaldo por una sola base para ventas, CRM y telemetría, con el MER en 3FN.

## Decisión

RDS for PostgreSQL 16, `db.t3.micro`, Multi-AZ: primaria en `datos-a` y réplica síncrona en `datos-b`, sin acceso público, cifrada y con TLS obligatorio. Se elige PostgreSQL por PostGIS (coordenadas de la flota) y por el particionamiento por fecha de la telemetría.

## Alternativas consideradas

- Base instalada en una EC2: respaldo manual y sin conmutación.
- DynamoDB: el BI necesita joins entre pedidos, despachos y telemetría, y los pedidos necesitan transacciones.
- RDS Single-AZ: una falla de zona dejaría todo sin datos.

## Consecuencias

- Multi-AZ duplica el costo de la base.
- Una sola base simplifica el cruce de datos comerciales y operativos.
- La telemetría comparte instancia con las ventas: su volumen (≈ 17 MB/día) debe vigilarse.
- El desarrollo local debe usar el mismo motor y extensiones.
