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
- Al registrar una empresa se crea una oportunidad «Prospecto nuevo» con origen `web` asignada a un vendedor.
- Aceptación explícita del tratamiento de datos personales.

## Fuera de alcance

- La validación de la empresa por el ejecutivo (T-014).

## Criterios de aceptación

- [ ] Test: el registro B2C crea `usuario`, `cliente` y `direccion_despacho` en una sola transacción.
- [ ] Test: el registro B2B crea además `contacto_cliente` principal y la oportunidad.
- [ ] Un RUT con dígito verificador incorrecto o ya registrado se rechaza.
- [ ] Una empresa recién registrada puede ver el catálogo pero no confirmar un pedido.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
