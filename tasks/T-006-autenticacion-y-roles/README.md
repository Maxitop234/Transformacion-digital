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

- [x] Un cliente que abre `/crm` recibe 403.
- [x] Un usuario inactivo no puede iniciar sesión.
- [x] Test: un vendedor no ve ni puede abrir clientes de otra cartera (responde 404); el administrador sí.
- [x] Test: el despachador no accede a oportunidades.
- [x] Las contraseñas se guardan con hash.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- **Verificación (2026-11-11):** 66 tests en verde (`tests/Feature/Auth`: inicio de sesión, inactivos, revocación con sesión abierta, límite de intentos, destino por rol, hash; acceso por rol, menú, RN-10 con 404 para cartera ajena, scope en tablas relacionadas). Recorrido en el navegador con los usuarios de demo sobre el entorno Docker: `vendedor1` entra a `/crm`, ve en su menú Panel, Clientes, Oportunidades, Pedidos, Alertas y Tickets, su cartera tiene solo sus 3 clientes, `/crm/clientes/8` (de otra cartera) da 404 y `/crm/despachos` 403; un cliente B2C recibe 403 en `/crm`.
- **Hallazgo en el recorrido:** un cliente que había abierto `/crm` sin sesión volvía a esa URL al entrar y veía un 403. Ahora `LoginController` descarta un destino del CRM si quien entra no es personal (test incluido).
- **Diseño:** la matriz de acceso vive en `App\Domain\Clientes\Permisos` (un gate por área) y se aplica con `can:<área>` en las rutas y `@can` en el menú; el grupo `/crm` exige además `rol:administrador,vendedor,despachador`. El alcance de cartera es `AlcanceCartera` + el scope `visiblePara()` (trait `EnCartera`); `Pedido` lo resuelve por `direccion_despacho` (3FN). El despachador ve todos los clientes a nivel de datos (necesita los puntos de entrega) pero los gates le cierran la gestión comercial.
- **Secciones pendientes:** `/crm/oportunidades`, `/pedidos`, `/despachos`, `/flota`, `/alertas`, `/tickets` y `/admin` existen con su gate y muestran a qué tarea corresponden; T-007 y T-013 a T-018 las reemplazan. `/crm/clientes` y `/crm/clientes/{id}` son mínimos (T-014 completa la ficha).
- **Sin «recordarme» ni restablecimiento de contraseña:** el modelo no tiene `remember_token` ni tabla de tokens; el broker de `config/auth.php` queda sin uso. Si se necesitan, es un cambio al modelo de datos (ADR).
- **Páginas de error en español** (`resources/views/errors/`: 403, 404, 419, 429, 500, 503).
- **`SESSION_SECURE_COOKIE`** agregada a ambos `.env.example`: `true` en AWS (T-024).
- **Observación para T-024:** el entorno Docker no compila los assets de Vite (`npm run build`), así que las páginas se ven sin estilos de Tailwind; el layout ya los carga si existe `public/build/manifest.json`.

