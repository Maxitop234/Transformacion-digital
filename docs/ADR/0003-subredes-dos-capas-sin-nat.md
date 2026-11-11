# ADR-0003 · Subredes en dos capas, sin NAT Gateway

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D5, D6, D7 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |
| Origen | Informe de diseño, Ficha 03 |

## Contexto

Solo el punto de entrada debe recibir tráfico de internet y la base no debe tener ruta a internet.

## Decisión

Capa pública (`10.20.1.0/24`, `10.20.2.0/24`) con ALB, EC2 web y nodo IoT, con ruta al Internet Gateway. Capa de datos privada (`10.20.21.0/24`, `10.20.22.0/24`) con RDS y solo ruta local.

## Alternativas consideradas

- Tercera capa privada para las EC2 con NAT Gateway: duplicaría el costo por hora.

## Consecuencias

- Las EC2 quedan con IP pública para salir a internet; se compensa con un Security Group que solo acepta tráfico del ALB.
- La base queda sin ninguna ruta a internet.
- Agregar un NAT Gateway más adelante exige un ADR nuevo, por su costo.
