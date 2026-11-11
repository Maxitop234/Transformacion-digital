# T-004 · Catálogos y datos de demostración

| Campo | Valor |
| --- | --- |
| Fase | 0 · Fundaciones |
| Dimensión de la pauta | 2, 4 |
| Depende de | [T-003](../T-003-esquema-base-de-datos/README.md) |
| Desbloquea | [T-006](../T-006-autenticacion-y-roles/README.md), [T-008](../T-008-catalogo-y-precios/README.md), [T-020](../T-020-dashboard-bi/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Poblar la base con los catálogos obligatorios y un conjunto de datos ficticios suficiente para recorrer todo el sistema en la defensa.

## Referencias

Leer antes de empezar:

- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md)
- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) §2
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §13
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Seeders de catálogos (idempotentes): regiones y comunas de la macrozona sur, roles, tipos de cliente, estados de pedido, etapas del pipeline, categorías y rangos de conservación.
- Usuario técnico `sistema` y un usuario por rol para la demo.
- Lista «Público general» y al menos dos listas institucionales con precios distintos.
- Al menos 30 productos refrigerados y congelados con inventario inicial y su movimiento de ingreso.
- 50 camiones `CAM-001` a `CAM-050` y sus conductores.
- Clientes B2C y B2B de ejemplo (incluida una empresa pendiente de validación), con contactos y puntos de entrega.
- Historial sintético de pedidos, oportunidades, interacciones y tickets de las últimas semanas para que el BI tenga datos.
- Separar seeders de catálogo (también corren en producción) de los de demostración.

## Fuera de alcance

- Telemetría histórica: la genera el simulador (T-019).

## Criterios de aceptación

- [ ] `php artisan migrate:fresh --seed` termina sin errores.
- [ ] Correr los seeders de catálogo dos veces no duplica filas.
- [ ] Hay 50 camiones y los 7 estados de pedido y 7 etapas del pipeline.
- [ ] Todos los RUT de prueba tienen dígito verificador válido y ningún dato corresponde a personas o empresas reales.
- [ ] Las credenciales de los usuarios de demo quedan documentadas en `apps/web/README.md` y solo sirven en local o demo.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
