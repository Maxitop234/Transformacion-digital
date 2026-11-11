# T-006 · Autenticación y control de acceso por rol

| Campo | Valor |
| --- | --- |
| Fase | 0 · Fundaciones |
| Dimensión de la pauta | 2 |
| Depende de | [T-003](../T-003-esquema-base-de-datos/README.md), [T-004](../T-004-datos-semilla/README.md) |
| Desbloquea | [T-007](../T-007-mantenedores-administracion/README.md), [T-008](../T-008-catalogo-y-precios/README.md), [T-009](../T-009-registro-de-clientes/README.md), [T-013](../T-013-panel-crm/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Permitir que cada persona entre con su usuario y vea solo lo que su rol permite.

## Referencias

Leer antes de empezar:

- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) §1 y RN-10
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §6
- [`docs/API.md`](../../docs/API.md) §1.1 y §1.2
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Inicio y cierre de sesión sobre la tabla `usuario` (`email`, `password_hash`), rechazando usuarios inactivos y actualizando `ultimo_acceso`.
- Middleware de rol y policies para los cinco roles; redirección tras el inicio de sesión al portal o al CRM según el rol.
- Alcance de cartera del vendedor como *scope* reutilizable sobre `cliente` y sus tablas relacionadas.
- Menú del CRM por rol.
- Límite de intentos en el inicio de sesión.

## Fuera de alcance

- Registro de clientes (T-009).
- Mantenedor de usuarios (T-007).

## Criterios de aceptación

- [ ] Un cliente que abre `/crm` recibe 403.
- [ ] Un usuario inactivo no puede iniciar sesión.
- [ ] Test: un vendedor no ve ni puede abrir clientes de otra cartera (responde 404); el administrador sí.
- [ ] Test: el despachador no accede a oportunidades.
- [ ] Las contraseñas se guardan con hash.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
