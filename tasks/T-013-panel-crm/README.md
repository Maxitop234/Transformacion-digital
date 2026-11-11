# T-013 · Panel del CRM

| Campo | Valor |
| --- | --- |
| Fase | 2 · CRM |
| Dimensión de la pauta | 2 |
| Depende de | [T-006](../T-006-autenticacion-y-roles/README.md) |
| Desbloquea | [T-014](../T-014-gestion-de-clientes/README.md), [T-016](../T-016-gestion-de-pedidos/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Dar al equipo de Cofrial una pantalla de inicio con lo que requiere su atención.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) §1, RN-10 y §7
- [`docs/API.md`](../../docs/API.md) §1.4
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Layout del CRM con menú por rol.
- Indicadores: oportunidades abiertas, conversión de los últimos 90 días, pedidos por canal y tickets abiertos.
- Lista de empresas pendientes de validación.
- Oportunidades sin interacciones en 15 días o más.
- Alertas de frío abiertas que afectan a la cartera, cruzando alerta, despacho, pedido y cliente.

## Fuera de alcance

- Las pantallas de detalle a las que enlaza (T-014 a T-018).

## Criterios de aceptación

- [x] El vendedor ve indicadores solo de su cartera; el administrador, de todo el equipo (con test).
- [x] El despachador no ve bloques comerciales.
- [x] Una alerta abierta sobre un camión en ruta aparece con los clientes afectados.
- [x] Los umbrales de 15 y 90 días se leen de configuración.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-11):** 141 tests en verde (1 omitido, `EsquemaCoincideConDataModelTest`, que dentro del contenedor no ve `docs/`), 7 nuevos en `tests/Feature/Crm/PanelCrmTest.php`: RN-10 en indicadores (vendedor contra administrador, con cifras exactas) y en las listas; el despachador sin bloques comerciales; alerta en ruta con clientes afectados (y filtrada por cartera); alertas resueltas o sin carga fuera de la lista; umbrales 15/90 cambiados por configuración; interacción ligada o del cliente que saca a la oportunidad de «sin actividad». Recorrido en el navegador con `vendedor1`, `admin` y `despacho@cofrial.example`.
- **Layout y menú por rol:** ya existían desde T-006 (`layouts/crm.blade.php` con gates). Esta tarea solo reemplazó el contenido de `/crm`.
- **Datos de demo:** la conversión sale 0 % (0 de 6) porque las 5 oportunidades ganadas del seeder se crearon en junio y julio, fuera de la ventana de 90 días; el cálculo es correcto. Si se quiere una cifra distinta de cero para la defensa, conviene sumar una oportunidad ganada reciente en `HistorialSeeder` (T-015 o T-027). Tampoco hay despachos `en_ruta` en la demo: las alertas del simulador quedan sin pedidos afectados hasta que T-017 cree despachos; el panel lo indica con el total de la flota.
- **Decisiones:** conversión por cohorte (creadas en la ventana que hoy están ganadas); pedidos por canal sin cancelados; «sin actividad» también cuenta interacciones del cliente que no se ligaron a una oportunidad (la mitad de las de demo no lo están). Las alertas usan el mismo cruce que `bi_pedido_afectado_alerta`, pero escrito con el query builder: las vistas `bi_*` se crean con `db/aplicar.sh`, no con migraciones, así que la aplicación no puede depender de ellas (no existen en `cofrial_test` ni tras un `migrate:fresh`). Si se cambia el criterio, hay que cambiarlo en los dos lugares.
- **Para T-014 a T-018:** los enlaces del panel apuntan a `crm.clientes.show` y `crm.alertas`; las fichas de oportunidad, pedido y alerta pueden enlazarse desde aquí cuando existan.
