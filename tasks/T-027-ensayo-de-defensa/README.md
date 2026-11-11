# T-027 · Ensayo integral y preparación de la defensa

| Campo | Valor |
| --- | --- |
| Fase | 6 · Defensa |
| Dimensión de la pauta | 5 |
| Depende de | [T-020](../T-020-dashboard-bi/README.md), [T-021](../T-021-integracion-ia/README.md), [T-024](../T-024-despliegue-web-y-balanceador/README.md), [T-025](../T-025-despliegue-nodo-iot/README.md) |
| Desbloquea | Ninguna |
| Componentes | `docs` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Comprobar que el prototipo funciona de punta a punta en AWS y dejar lista la demostración en vivo.

## Referencias

Leer antes de empezar:

- [`docs/ROADMAP.md`](../../docs/ROADMAP.md) (guion y relación con la pauta)
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §13
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Recorrido completo del guion de `ROADMAP.md` en el entorno de AWS, anotando cada falla como tarea o corrección.
- Datos de demostración cargados en producción y cuentas de demo por rol.
- Prueba de arranque en frío: iniciar el laboratorio y verificar que todo queda operativo sin pasos manuales.
- Prueba de falla: detener una instancia web y, si el laboratorio lo permite, forzar la conmutación de RDS.
- Lista de verificación de seguridad de `SECURITY.md` completada.
- `docs/DEMO.md` con el guion, las URL, los comandos de evidencia y el plan B por si falla la IA o el laboratorio.

## Fuera de alcance

- Funcionalidad nueva.

## Criterios de aceptación

- [ ] Las cinco dimensiones de la pauta se demuestran en un solo recorrido sin errores.
- [ ] El recorrido se completó al menos dos veces seguidas.
- [ ] Cada integrante puede explicar una decisión de arquitectura a partir de los ADR.
- [ ] Todas las tareas de la ruta crítica figuran como terminadas en el índice.
- [ ] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

_Sin notas todavía. Registrar aquí decisiones, comandos útiles y pendientes al trabajar la tarea._
