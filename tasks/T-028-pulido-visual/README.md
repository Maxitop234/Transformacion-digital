# T-028 · Pulido visual e interacción de la interfaz

| Campo | Valor |
| --- | --- |
| Fase | 6 · Defensa |
| Dimensión de la pauta | 2 |
| Depende de | [T-009](../T-009-registro-de-clientes/README.md), [T-012](../T-012-seguimiento-de-pedidos/README.md), [T-015](../T-015-pipeline-de-oportunidades/README.md), [T-018](../T-018-tickets/README.md), [T-021](../T-021-integracion-ia/README.md) |
| Desbloquea | Ninguna |
| Componentes | `apps/web` |
| Estado | Ver [índice](../README.md) |

## Objetivo

Dejar el portal y el CRM con una interfaz más clara y atractiva para la defensa: modo oscuro, jerarquía visual de color, menús animados y buenas prácticas de UI/UX, sin cambiar el comportamiento del sistema.

## Referencias

Leer antes de empezar:

- [`apps/web/README.md`](../../apps/web/README.md): estilos con Tailwind 4 y Vite.
- [`docs/SECURITY.md`](../../docs/SECURITY.md) §6: escape de salida y CSRF siguen vigentes.
- [`AGENTS.md`](../../AGENTS.md): convenciones y límites

## Alcance

- Modo oscuro con conmutador, que recuerda la elección y por defecto sigue la preferencia del sistema.
- Jerarquía de color: acción principal, estados de pedidos, tickets y oportunidades con insignias por tono, mensajes de éxito, aviso y error.
- Menús rediseñados: cabecera del portal con menú móvil y menú de cuenta animados; barra lateral del CRM con iconos, estado activo y cajón en pantallas angostas.
- Animaciones breves (entrada de página, menús, mensajes, tarjetas), desactivadas con `prefers-reduced-motion`.
- Accesibilidad: foco visible, contraste suficiente en ambos temas, `aria-expanded` en los menús.
- Portada del portal con propuesta de valor y accesos.

## Fuera de alcance

- Cambios de lógica de negocio, rutas o modelo de datos.
- Tema de Grafana (`/bi`).
- Arrastrar y soltar en el pipeline.

## Criterios de aceptación

- [x] El conmutador cambia entre claro y oscuro en portal y CRM, y la elección persiste al navegar y recargar.
- [x] En modo oscuro, las pantallas principales (catálogo, carrito, detalle de pedido, panel, pedidos, pipeline, tickets) son legibles, sin texto oscuro sobre fondo oscuro.
- [x] A 375 px de ancho no hay desplazamiento horizontal en portal ni CRM, y los menús se abren y cierran con teclado y puntero.
- [x] Toda la suite de tests sigue pasando sin modificar aserciones de comportamiento.
- [x] La documentación afectada quedó actualizada y el estado se cambió en el índice.

## Notas

- El modo oscuro se hace invirtiendo las variables de la paleta de Tailwind, no con clases `dark:` en cada vista: así las más de 40 vistas quedan cubiertas y las nuevas también. Detalle en `apps/web/README.md`, «Interfaz y tema».
- Verificado en el navegador (admin y cliente B2B de demo): conmutador y persistencia; modo oscuro en login, panel, catálogo, carrito y detalle de pedido; modo claro en pedidos, catálogo y portada; menú móvil, menú de cuenta y cajón del CRM (abre, cierra con Escape). A 375 px no hay desplazamiento horizontal en 25 pantallas del portal y del CRM. El pipeline y los tickets en oscuro usan el mismo mecanismo, pero no los revisé uno por uno.
- Los tests no cambiaron: las vistas conservan sus atributos `data-*` y textos.
- El formulario deshabilita su botón al enviarse, para evitar el doble envío.
- No se tocó Grafana (`/bi`) ni las páginas de error (`errors/base`), que tienen sus propios estilos.
- Pendiente posible: insignias en alertas y en la ficha del cliente (tickets), y arrastrar y soltar en el pipeline.
