# T-008 · Catálogo con precios por lista

| Campo | Valor |
| --- | --- |
| Fase | 1 · Portal de ventas |
| Dimensión de la pauta | 2 |
| Depende de | [T-004](../T-004-datos-semilla/README.md), [T-006](../T-006-autenticacion-y-roles/README.md) |
| Desbloquea | [T-010](../T-010-carrito/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Mostrar el catálogo de productos refrigerados con el precio que corresponde a cada perfil.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) RN-01, RN-02, RN-04
- [`docs/API.md`](../../docs/API.md) §1.2
- [`docs/DATA_MODEL.md`](../../docs/DATA_MODEL.md) §2
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- `/catalogo` con filtros por categoría, rango de conservación, disponibilidad y búsqueda por nombre.
- Servicio único de precios que resuelve el precio de un producto para un cliente según su lista; sin sesión o con cuenta pendiente usa «Público general».
- Indicador del perfil activo y de la lista de precios en uso.
- Disponible para venta = `stock_disponible − stock_reservado`; con 0 el botón «Agregar» queda deshabilitado.
- Detalle de producto en `/productos/{sku}`.

## Fuera de alcance

- Carrito (T-010).

## Criterios de aceptación

- [ ] Test: el mismo producto muestra precios distintos para un cliente B2C y uno B2B con lista institucional.
- [ ] Test: una empresa pendiente de validación ve precios de «Público general».
- [ ] Un producto sin disponible aparece como sin stock.
- [ ] Los productos inactivos no se listan.
- [ ] Los filtros se pueden combinar.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
