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

- [x] `php artisan migrate:fresh --seed` termina sin errores.
- [x] Correr los seeders de catálogo dos veces no duplica filas.
- [x] Hay 50 camiones y los 7 estados de pedido y 7 etapas del pipeline.
- [x] Todos los RUT de prueba tienen dígito verificador válido y ningún dato corresponde a personas o empresas reales.
- [x] Las credenciales de los usuarios de demo quedan documentadas en `apps/web/README.md` y solo sirven en local o demo.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-10):** `migrate:fresh --seed` en el host y dentro del contenedor (~9 s). `CatalogoSeeder` dos veces deja los mismos conteos; `db:seed` repetido no duplica la demo. 42 tests en verde, incluidos `SeedersTest` (cobertura de la tarea, idempotencia, RUT válidos y ficticios, contactos ficticios, coherencia del historial con RN-03, RN-07 y RN-09, inventario cuadrado con la bitácora) y `RutTest`. Las credenciales de demo validan con `Auth::validate`; `sistema` no.
- **Separación:** `CatalogoSeeder` (idempotente, también en producción) y `DemoSeeder` con sus partes en `database/seeders/Demo/` (`EquipoSeeder`, `ProductosSeeder`, `FlotaSeeder`, `ClientesSeeder`, `HistorialSeeder`). `DatabaseSeeder` corre la demo solo fuera de producción y `DemoSeeder` se niega a correr con `APP_ENV=production`.
- **Datos ficticios:** RUT en 45.000.000–45.999.999 (convención del proyecto; todos con DV válido, guardados como `12345678-5`), correos `.example`, teléfonos `+56 9 5555 ····`, empresas con «Demo» en el nombre, calles «Pasaje Demo ###» y patentes con prefijo `ZZ`. Las comunas y regiones son reales (geografía pública).
- **`App\Domain\Clientes\Rut`:** validación y normalización de RUT (módulo 11); la reutilizará T-009 para el registro.
- **`config/negocio.php`:** pedido mínimo, minutos de reserva, horas de abandono del carrito e IVA desde el `.env`, como pide AGENTS.md. El historial sintético lo usa para cumplir RN-03 y calcular los montos de pago.
- **Supuestos a confirmar con el equipo:**
  - Los **precios de lista son netos** (sin IVA) y los pagos de la demo se calculan con IVA (`IVA_PCT`). T-008/T-011 deben confirmar cómo se muestran al cliente B2C.
  - Rangos de conservación de referencia: Congelado −25 a −18 °C, Refrigerado 0 a 5 °C, Cárnico fresco −1 a 4 °C.
  - Las listas institucionales cuestan 14 % y 10 % menos que la pública.
- **Convención de `movimiento_inventario.cantidad`** documentada en `DATA_MODEL.md` (reserva/liberación sobre `stock_reservado`; ingreso/salida/merma/ajuste sobre `stock_disponible`). T-010/T-011 deben seguirla.
- **Historial:** las fechas son relativas al momento de sembrar (8 semanas hacia atrás). Los pedidos de hoy quedan abiertos (pendientes o confirmados) y la entrega empieza al día hábil siguiente; para ver pedidos en preparación o en ruta durante una demo, conviene volver a sembrar esa mañana. Cada pedido del portal nace de un carrito `convertido`; cada rechazo por temperatura abre un ticket `reclamo`.
- **Para T-019/T-020:** no se siembra telemetría (la genera el simulador). Dos camiones (`CAM-017`, `CAM-042`) quedan inactivos «en mantención».

