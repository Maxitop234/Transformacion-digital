# ADR-0013 · Portal y CRM como un monolito modular en Laravel

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-05 |
| Drivers | D2, D6 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |

## Contexto

El portal y el CRM comparten clientes, precios, pedidos y reglas. El informe de diseño los define como módulos de la misma aplicación, detrás del mismo balanceador y sobre la misma base.

## Decisión

Una sola aplicación Laravel en `apps/web`, organizada por dominios (`Clientes`, `Catalogo`, `Ventas`, `Crm`, `Logistica`, `Ia`), con dos superficies: portal en `/` y CRM en `/crm`. La interfaz se renderiza en el servidor con Blade, y Livewire para las partes interactivas (carrito, pipeline). *Confirmado en T-001 (2026-11-10): Laravel 13 y Livewire 4; versiones exactas en [`apps/web/README.md`](../../apps/web/README.md).*

## Alternativas consideradas

- Dos aplicaciones separadas: duplicaría modelos y reglas (precio, stock, estados) y complicaría el despliegue.
- SPA con API REST separada: más piezas que construir y desplegar para el mismo resultado evaluado.
- Otro framework: Laravel es el que el equipo declaró en el diseño y trae autenticación, colas y migraciones integradas.

## Consecuencias

- Un solo despliegue y un solo lugar para cada regla de negocio.
- El pedido telefónico del CRM reutiliza exactamente la misma lógica que el checkout del portal.
- Hay que cuidar los límites entre módulos para que no se enreden.
- Si más adelante se necesita una app móvil, habrá que exponer una API.
