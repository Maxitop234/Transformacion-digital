# ADR-0014 · Simulador IoT en Python

| Campo | Valor |
| --- | --- |
| Estado | Propuesta |
| Fecha | 2026-11-11 |
| Drivers | D3 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |

## Contexto

El enunciado exige contenedores Docker que inyecten telemetría, pero no fija el lenguaje. El simulador es un proceso pequeño, independiente de la aplicación web y con un usuario de base distinto.

## Decisión

Escribir el simulador en Python 3.12 con un driver de PostgreSQL (`psycopg`), empaquetado en una imagen ligera, configurado solo por variables de entorno.

## Alternativas consideradas

- Un comando Artisan de Laravel: reutiliza modelos, pero arrastra toda la aplicación y sus credenciales al nodo IoT, rompiendo el mínimo privilegio.
- Node.js: equivalente; se prefiere Python por simplicidad para generar series numéricas.

## Consecuencias

- Imagen pequeña y fácil de explicar en la defensa.
- El nodo IoT no contiene código ni credenciales de la aplicación.
- Se agrega un segundo lenguaje al repositorio, con su propio formateador y tests.
- El simulador depende del esquema de `telemetria` y `alerta`: un cambio en esas tablas debe actualizarlo.
