# T-007 · Mantenedores de administración

| Campo | Valor |
| --- | --- |
| Fase | 0 · Fundaciones |
| Dimensión de la pauta | 2 |
| Depende de | [T-006](../T-006-autenticacion-y-roles/README.md) |
| Desbloquea | Ninguna |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Dar al administrador las pantallas para mantener catálogo, listas de precios, inventario y usuarios sin tocar la base a mano.

## Referencias

Leer antes de empezar:

- [`docs/API.md`](../../docs/API.md) §1.4 (rutas `/crm/admin`)
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) §2
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Productos: crear, editar, activar y desactivar, con categoría y rango de conservación.
- Listas de precios y precio por producto.
- Inventario: ingresos y ajustes que siempre generan una fila en `movimiento_inventario`.
- Usuarios: crear, asignar rol, activar y desactivar; reasignar la cartera de un vendedor.

## Fuera de alcance

- Carga masiva por archivo.
- Mantenedor de camiones y conductores (se siembran en T-004).

## Criterios de aceptación

- [ ] Solo el administrador accede a `/crm/admin/*`.
- [ ] Un ingreso de stock aumenta `stock_disponible` y deja su movimiento con el usuario responsable.
- [ ] Cambiar un precio de lista no altera el `precio_unitario_pactado` de pedidos existentes (RN-01, con test).
- [ ] Desactivar un usuario le impide iniciar sesión.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Tarea recortable: si falta tiempo, los datos pueden mantenerse con seeders.
