# T-021 · Integración de IA a costo cero

| Campo | Valor |
| --- | --- |
| Fase | 4 · BI e IA |
| Dimensión de la pauta | 5 |
| Depende de | [T-018](../T-018-tickets/README.md) |
| Desbloquea | [T-027](../T-027-ensayo-de-defensa/README.md) |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Automatizar la clasificación de requerimientos y la atención de consultas con una API de IA gratuita, sin exponer datos personales.

## Referencias

Leer antes de empezar:

- [`docs/API.md`](../../docs/API.md) §1.5 y §3
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §9
- [`docs/BUSINESS_RULES.md`](../../docs/BUSINESS_RULES.md) RN-11
- [`docs/ADR/0015`](../../docs/ADR/README.md)
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Elegir el proveedor de capa gratuita, verificar sus límites vigentes y cerrar ADR-0015.
- Interfaz `ClienteIa` con una implementación para el proveedor elegido y otra falsa para tests.
- Filtro que enmascara RUT, correos y teléfonos del texto antes de enviarlo.
- Trabajo en cola que clasifica cada ticket nuevo en categoría y prioridad, valida la respuesta contra las listas permitidas y marca `clasificado_por_ia`.
- Degradación: ante error, tiempo de espera o respuesta inválida, el ticket queda para clasificación manual.
- Asistente del portal en `POST /asistente/mensajes`, con contexto armado en el servidor y derivación a ticket.
- Límite de uso por usuario.

## Fuera de alcance

- Entrenar o alojar modelos propios.
- Que la IA ejecute acciones sobre pedidos.

## Criterios de aceptación

- [ ] Un ticket creado en el portal queda clasificado por la IA en menos de un minuto, con una llamada real a la API.
- [ ] Test: el texto enviado no contiene RUT, correos ni teléfonos.
- [ ] Test: con la API caída, el ticket se crea igual y queda `sin_clasificar`.
- [ ] Test: una respuesta con una categoría fuera de lista se descarta.
- [ ] La clave de la API solo existe en `.env`.
- [ ] El asistente responde el estado de un pedido propio y no entrega datos de pedidos ajenos.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- Recortable: el asistente conversacional. La clasificación de tickets basta para la pauta.
