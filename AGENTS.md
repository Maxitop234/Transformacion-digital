# AGENTS.md

Instrucciones para agentes de IA y personas que trabajen en este repositorio. Es la fuente única de convenciones; `CLAUDE.md` solo la importa.

## Qué es este proyecto

Prototipo de transformación digital para Cofrial SpA (distribución refrigerada, La Araucanía): portal de ventas B2B/B2C, CRM, simulación IoT de 50 camiones frigoríficos, dashboard de BI e integración de IA, desplegado en AWS Academy sobre una única base PostgreSQL. El objetivo y la arquitectura están resumidos en [`README.md`](README.md).

## Dónde está cada cosa

| Necesitas saber… | Lee |
| --- | --- |
| Qué hay que construir y en qué orden | [`tasks/README.md`](tasks/README.md), [`docs/ROADMAP.md`](docs/ROADMAP.md) |
| Tablas, columnas, claves y valores permitidos | [`docs/DATA_MODEL.md`](docs/DATA_MODEL.md) |
| Reglas de negocio (RN-xx), roles y estados | [`docs/BUSINESS_RULES.md`](docs/BUSINESS_RULES.md) |
| Rutas HTTP, contrato de telemetría y de IA | [`docs/API.md`](docs/API.md) |
| Red, cómputo, despliegue | [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) |
| Qué no se puede hacer por seguridad | [`docs/SECURITY.md`](docs/SECURITY.md) |
| Por qué algo es como es | [`docs/ADR/`](docs/ADR/README.md) |

Lee solo lo que la tarea necesite: cada tarea enumera sus referencias.

## Flujo de trabajo por tarea

1. **Elige una tarea** de `tasks/README.md` cuyo estado sea `Pendiente` y cuyas dependencias estén `Terminada`. Trabaja una tarea a la vez.
2. **Lee su `README.md`** completo y las referencias que cita. El alcance y los criterios de aceptación mandan.
3. **Márcala `En curso`** en el índice.
4. **Implementa** dentro del alcance. Si descubres trabajo fuera de alcance, no lo hagas: anótalo en la sección «Notas» de la tarea o crea una tarea nueva con la plantilla.
5. **Verifica** cada criterio de aceptación ejecutándolo de verdad (tests, comando o recorrido en el navegador). No marques un criterio como cumplido sin haberlo comprobado.
6. **Actualiza la documentación** que tu cambio deje desactualizada (`API.md`, `DATA_MODEL.md`, etc.) en el mismo cambio.
7. **Márcala `Terminada`** en el índice y deja en «Notas» lo que el siguiente deba saber (comandos, decisiones, pendientes).

Si una tarea no se puede completar (dependencia faltante, ambigüedad, límite de AWS Academy), márcala `Bloqueada`, explica el motivo en «Notas» y detente. No inventes un atajo que contradiga un ADR.

## Decisiones de arquitectura

- Las decisiones vigentes están en `docs/ADR/`. Una decisión `Aceptada` no se contradice en el código: si crees que debe cambiar, escribe un ADR nuevo que la reemplace y espera aprobación humana.
- Los ADR en estado `Propuesta` son la opción por defecto; puedes seguirlos, pero confirma con el equipo antes de construir mucho encima.
- Registra un ADR nuevo cuando elijas entre alternativas con consecuencias duraderas: una dependencia importante, un cambio al modelo de datos, una forma distinta de desplegar, un proveedor externo. Usa `docs/ADR/0000-plantilla.md` y el siguiente número libre.

## Convenciones

### Idioma y nombres

- **Dominio en español.** Tablas y columnas en `snake_case`, minúscula y singular, exactamente como en `DATA_MODEL.md` (`pedido`, `detalle_pedido`, `id_cliente`). Modelos Eloquent con el mismo sustantivo en `PascalCase` (`Pedido`, `DetallePedido`).
- **Código de infraestructura y framework en su idioma natural** (clases base de Laravel, nombres de librerías). No traduzcas el framework.
- Documentación, mensajes de commit, textos de interfaz y comentarios: español.
- Los campos de telemetría exigidos por el enunciado conservan su nombre literal: `camion_id`, `temp_camara`, `lat`, `long`, `combustible_pct`, `alerta_ruptura_frio`.

### Base de datos

- El esquema de negocio se crea y cambia **solo con migraciones de Laravel** en `apps/web/database/migrations`. Nada de cambios manuales en RDS.
- Como el modelo no sigue las convenciones por defecto de Eloquent, cada modelo declara explícitamente `$table`, `$primaryKey` y, si corresponde, `$timestamps = false` o los nombres de sus columnas de fecha.
- Dinero en `NUMERIC` (pesos chilenos), nunca `float`. Fechas en `TIMESTAMPTZ`. Estados como catálogo o `CHECK`, nunca texto libre.
- Los historiales (`historial_estado_pedido`, `historial_etapa`, `movimiento_inventario`) son de solo inserción: no se actualizan ni se borran.
- Toda operación que toque stock o cree un pedido va dentro de una transacción.
- Un cambio al modelo de datos exige actualizar `docs/DATA_MODEL.md` y, si altera una decisión, un ADR.

### Aplicación web (`apps/web`)

- Portal y CRM son módulos de la misma aplicación Laravel. La lógica de negocio vive en servicios o acciones (por ejemplo `App\Domain\Ventas\CrearPedido`), no en controladores ni en vistas.
- Las transiciones de estado de pedidos y oportunidades pasan por un único servicio que valida la transición, el rol y escribe el historial (ver `BUSINESS_RULES.md`).
- Autorización con policies/gates por rol. El filtro de cartera del vendedor (RN-10) se aplica en el servidor, nunca solo en la vista.
- Los parámetros de negocio (pedido mínimo, minutos de reserva, IVA) se leen de configuración, no se escriben como literales.
- La aplicación no guarda estado en disco local: sesiones, caché y colas van a la base, porque hay dos instancias detrás del balanceador.

### Simulador IoT (`apps/iot-simulator`)

- Se configura únicamente por variables de entorno y se conecta con el usuario `iot_writer`.
- Cada lectura respeta el contrato de `docs/API.md` (sección telemetría). Si no puede escribir, reintenta con espera; no se cae.

### Calidad

- Cada regla de negocio (RN-xx) que implementes lleva al menos un test que la nombre.
- Antes de dar una tarea por terminada, ejecuta el formateador y los tests del componente que tocaste. Los comandos exactos quedan documentados en el `README.md` de cada componente a partir de T-001 y T-019.
- No desactives ni borres tests para que pasen.

### Git

- Ramas cortas por tarea: `tarea/T-010-carrito`.
- Commits en español, en imperativo, con el identificador de la tarea: `T-010: agrega actualización de cantidades al carrito`.
- Un commit no mezcla tareas distintas.

## Límites: no hagas esto

- **No subas secretos.** Ni `.env`, ni claves `.pem`, ni credenciales del Learner Lab, ni claves de API. Si necesitas una variable nueva, agrégala a `.env.example` con un marcador de posición y documéntala.
- **No expongas la base de datos.** RDS no lleva acceso público ni reglas `0.0.0.0/0`; SSH solo desde la IP del equipo.
- **No envíes datos personales a la IA externa.** Nunca RUT, nombres, correos, teléfonos ni direcciones (RN-11). Los prompts usan identificadores internos y texto del requerimiento ya depurado.
- **No muestres datos del conductor al cliente.** El portal informa camión, parada y estado de frío, no quién conduce.
- **No uses un único usuario de base para todo.** Cada componente usa el suyo (`app_rw`, `iot_writer`, `bi_reader`).
- **No reemplaces piezas exigidas por el enunciado**: backend en EC2, base en RDS, simulación IoT con Docker en un nodo dedicado, IA externa gratuita.
- **No agregues servicios de AWS con costo** fuera de los decididos (por ejemplo NAT Gateway) sin un ADR aprobado; los créditos del laboratorio son limitados.
- **No uses datos reales** de personas o empresas en seeders ni ejemplos. Los RUT de prueba deben ser ficticios pero con dígito verificador válido.

## Entorno

- Desarrollo local con Docker Compose (`infra/docker/compose.dev.yml`, se crea en T-002): PostgreSQL con PostGIS, aplicación web, worker, Grafana y simulador.
- Producción en AWS Academy Learner Lab, región `us-east-1`. Las sesiones del laboratorio caducan y las credenciales son temporales: nada del despliegue debe depender de una IP pública de EC2, solo del DNS del balanceador.
- Zona horaria de negocio: `America/Santiago`. En la base, todo en UTC con `TIMESTAMPTZ`.

## Cuando tengas dudas

Prefiere preguntar antes que suponer en: montos y plazos marcados como «supuesto», cambios al modelo de datos, cualquier cosa con costo en AWS y todo lo que toque datos personales. Para lo demás, toma la opción más simple que cumpla los criterios de aceptación y déjala anotada.
