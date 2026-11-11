# ADR-0012 · Monorepo con documentación y tareas orientadas a agentes

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-11 |
| Drivers | — (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |

## Contexto

El sistema tiene una aplicación web, un simulador, SQL de base de datos, dashboards e infraestructura que cambian juntos y que desarrolla un equipo pequeño apoyado por agentes de IA.

## Decisión

Un solo repositorio con `apps/`, `db/`, `infra/`, `docs/` y `tasks/`. `AGENTS.md` concentra las convenciones, `docs/ADR` las decisiones y cada tarea vive en su propia carpeta con criterios de aceptación verificables.

## Alternativas consideradas

- Un repositorio por componente: más aislamiento, pero cambios que cruzan componentes (por ejemplo, una columna nueva de telemetría) quedarían repartidos en varios lugares.
- Gestionar las tareas solo en un tablero externo: los agentes no tendrían el contexto en el repositorio.

## Consecuencias

- Un cambio que toca esquema, aplicación y dashboard cabe en un solo commit.
- Los agentes encuentran contexto, reglas y trabajo pendiente sin salir del repositorio.
- El índice de tareas debe mantenerse al día a mano.
- El CI debe filtrar por carpeta para no ejecutar todo en cada cambio.
