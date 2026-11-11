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

- [x] Test: el mismo producto muestra precios distintos para un cliente B2C y uno B2B con lista institucional.
- [x] Test: una empresa pendiente de validación ve precios de «Público general».
- [x] Un producto sin disponible aparece como sin stock.
- [x] Los productos inactivos no se listan.
- [x] Los filtros se pueden combinar.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-11):** 94 tests en verde, 10 nuevos en `tests/Feature/Portal/CatalogoTest.php` (RN-01 B2C contra B2B institucional, RN-02 empresa pendiente, RN-04 sin stock con todo reservado, inactivos fuera del listado y del detalle, filtros combinados, lista vencida o inactiva, producto sin precio en la lista, comodines en la búsqueda). Recorrido en el navegador sobre el entorno Docker: como administrador se ve «Personal de Cofrial · Público general» con IVA (Arvejas CON-002 a $2.606); con `compras@clinica-araucaria.example` se ve «Empresa (B2B) · Hospitales y clínicas 2026» en neto (la misma a $1.880 + IVA).
- **Servicio único de precios:** `App\Domain\Catalogo\Precios`. `perfil($usuario)` devuelve un `PerfilPrecio` (lista, etiqueta, si es B2B, si puede comprar); `precio()` y `precios()` leen `detalle_lista_precio`; `conIva()` usa `negocio.iva_pct`; `Precios::clp()` da el formato de presentación. T-010 y T-011 deben pedir el precio aquí, no a `detalle_lista_precio` directamente.
- **Reglas de la lista:** se usa la del cliente solo si `estado_cliente = activo` y la lista está activa y vigente hoy (zona `app.timezone`). En cualquier otro caso, «Público general».
- **IVA:** los precios de lista son netos. B2B los ve en neto («+ IVA»); B2C, visitantes y personal, con IVA redondeado al peso. El detalle muestra también la otra cifra.
- **Producto sin precio en la lista:** se lista con «Precio no disponible en tu lista» y «Agregar» deshabilitado (no se recurre a la lista pública, para no vender a un B2B con un precio que no pactó).
- **Botón «Agregar»:** se deshabilita sin disponible para venta, sin precio, con cuenta que no puede comprar (empresa pendiente, personal). A un visitante se le ofrece «Inicia sesión para comprar». Es un `<button type="button" data-agregar="{sku}">` sin acción: T-010 lo conecta al carrito.
- **Disponible para venta:** `Producto::disponibleParaVenta()` (`stock_disponible − stock_reservado`, mínimo 0).
