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

- [x] Solo el administrador accede a `/crm/admin/*`.
- [x] Un ingreso de stock aumenta `stock_disponible` y deja su movimiento con el usuario responsable.
- [x] Cambiar un precio de lista no altera el `precio_unitario_pactado` de pedidos existentes (RN-01, con test).
- [x] Desactivar un usuario le impide iniciar sesión.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Tarea recortable: si falta tiempo, los datos pueden mantenerse con seeders.
- **Verificación (2026-11-11):** 84 tests en verde, 18 nuevos en `tests/Feature/Admin/MantenedoresTest.php`: acceso solo del administrador a cada ruta y acción, alta y edición de productos, listas y precios, RN-01 (el precio pactado no cambia al cambiar la lista), ingreso y ajuste con su movimiento y responsable, desactivación que impide iniciar sesión, cambio de rol y RN-10 al reasignar cartera. Recorrido en el navegador con `admin@cofrial.example` sobre el entorno Docker: un ingreso de 5 unidades de CON-002 subió el disponible de 322 a 327 y apareció en «Últimos movimientos» con el correo del administrador (ese movimiento quedó en la base local de desarrollo).
- **Diseño:** la lógica vive en `App\Domain\Catalogo\{GuardarProducto, ActualizarPreciosLista, RegistrarMovimientoInventario}` y `App\Domain\Clientes\AdministrarUsuarios`; los controladores están en `App\Http\Controllers\Crm\Admin` y las rutas bajo `can:administracion`.
- **Inventario:** solo `ingreso` (positivo) y `ajuste` (con signo). Un ajuste no puede dejar el disponible bajo lo reservado. Se bloquea la fila de `inventario` (`lockForUpdate`) dentro de la transacción. Las cantidades se operan en centésimas enteras porque la imagen Docker no trae `bcmath`.
- **Usuarios:** el alta es solo para personal; las cuentas de cliente las crea el registro (T-009) y aquí solo se activan o desactivan. Al crear un vendedor se crea su fila en `vendedor`; `vendedor.activo` sigue a `usuario.activo`. Un vendedor con clientes no cambia de rol hasta reasignar su cartera. El administrador no puede desactivarse ni quitarse el rol. El usuario técnico `sistema` no aparece ni se puede editar (404).
- **Reasignación:** mueve todos los clientes del vendedor a otro vendedor activo. Las oportunidades conservan su `id_vendedor`, pero la visibilidad (RN-10) se resuelve por el cliente. La reasignación de un solo cliente (`PATCH /crm/clientes/{id}/vendedor`) queda para T-014.
- **Contraseñas** del personal: mínimo 10 caracteres, con confirmación.
