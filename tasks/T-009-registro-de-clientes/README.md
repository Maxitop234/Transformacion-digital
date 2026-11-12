# T-009 · Registro de clientes B2C y B2B

| Campo | Valor |
| --- | --- |
| Fase | 1 · Portal de ventas |
| Dimensión de la pauta | 2 |
| Depende de | [T-006](../T-006-autenticacion-y-roles/README.md) |
| Desbloquea | Ninguna |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Permitir que personas y empresas creen su cuenta, con el flujo de validación propio de cada perfil.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) §2, RN-02 y §6
- [`docs/API.md`](../../docs/API.md) §1.2
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §1
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Registro B2C: correo, nombre, contraseña y una dirección; cuenta activa de inmediato con lista «Público general».
- Registro B2B: RUT, razón social, contacto principal y punto de entrega con ventana de recepción; el cliente queda pendiente de validación.
- Validación de RUT con dígito verificador y unicidad.
- Al registrar una empresa se crea una oportunidad «Prospecto nuevo» con origen `portal` asignada a un vendedor.
- Aceptación explícita del tratamiento de datos personales.

## Fuera de alcance

- La validación de la empresa por el ejecutivo (T-014).

## Criterios de aceptación

- [x] Test: el registro B2C crea `usuario`, `cliente` y `direccion_despacho` en una sola transacción.
- [x] Test: el registro B2B crea además `contacto_cliente` principal y la oportunidad.
- [x] Un RUT con dígito verificador incorrecto o ya registrado se rechaza.
- [x] Una empresa recién registrada puede ver el catálogo pero no confirmar un pedido.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Tests en `apps/web/tests/Feature/Portal/RegistroTest.php` (`php artisan test --filter=RegistroTest`). También lo recorrí en el navegador: una empresa ficticia, un RUT con dígito verificador malo (rechazado) y luego el correcto.
- **Consentimiento guardado** (aprobado por el equipo el 2026-11-11): la columna nueva `cliente.consentimiento_datos_en TIMESTAMPTZ?`, creada con la migración `2026_11_11_000100_add_consentimiento_datos_en_to_cliente_table`, guarda cuándo se aceptó. Queda nula en prospectos del CRM y en clientes anteriores al cambio. No guarda la versión del texto aceptado: si algún día cambia la redacción, convendría una tabla de consentimientos con versión.
- **Asignación de vendedor:** el activo con menos clientes en cartera (empata el más antiguo). Si no hay ninguno, la empresa queda sin vendedor ni oportunidad y la ve el administrador. El administrador puede reasignar desde la ficha.
- El contacto principal B2B usa el mismo correo que la cuenta de acceso. El teléfono y el cargo son opcionales.
- En la oportunidad, el `historial_etapa` inicial queda a nombre del usuario de la empresa, que es quien hizo el registro.
- Una empresa pendiente ve el catálogo con «Público general» en neto (como toda empresa, T-008) y el botón «Agregar» deshabilitado.
