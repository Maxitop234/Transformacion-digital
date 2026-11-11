# ADR-0016 · Esquema en español gestionado por migraciones de Laravel

| Campo | Valor |
| --- | --- |
| Estado | Propuesta |
| Fecha | 2026-11-11 |
| Drivers | D2 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |

## Contexto

El modelo de datos se diseñó con nombres en español, en singular y con claves `id_<tabla>`, lo que no coincide con las convenciones por defecto de Eloquent (inglés, plural, `id`). Además, tres componentes dependen del mismo esquema: la aplicación, el simulador y el BI.

## Decisión

Respetar el MER tal como está documentado. El esquema de negocio se crea solo con migraciones de Laravel en `apps/web`; cada modelo declara explícitamente su tabla y su clave. Lo que las migraciones no deben manejar (roles de base, permisos y vistas `bi_*` e `iot_*`) vive como SQL en `db/`.

## Alternativas consideradas

- Renombrar el modelo a las convenciones de Eloquent: menos configuración, pero rompe la trazabilidad con el informe, el diccionario de datos y los campos exigidos por el enunciado.
- SQL puro como fuente del esquema: independiente del framework, pero duplica el trabajo de las migraciones y de los tests.

## Consecuencias

- El código y la base hablan el mismo idioma que el negocio y que el informe.
- Más configuración explícita en cada modelo (tabla, clave primaria, claves compuestas, marcas de tiempo).
- Las tablas con clave compuesta (`detalle_pedido`, `item_carrito`, `despacho_pedido`, `detalle_lista_precio`) necesitan cuidado especial en Eloquent.
- El simulador y Grafana quedan acoplados a las migraciones de `apps/web`: `docs/DATA_MODEL.md` es el contrato compartido.
