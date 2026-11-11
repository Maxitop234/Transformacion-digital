# Registros de decisiones de arquitectura (ADR)

Cada archivo documenta una decisión con consecuencias duraderas: su contexto, lo que se decidió, las alternativas descartadas y lo que se acepta a cambio. Sirven para que quien llegue después, persona o agente, entienda por qué el sistema es como es antes de cambiarlo.

Los ADR 0001 a 0011 provienen de las fichas de decisión del informe de diseño (Hito 1). Los siguientes se registran durante el desarrollo.

## Índice

| N.º | Decisión | Estado | Fecha |
| --- | --- | --- | --- |
| [0001](0001-region-us-east-1.md) | Región us-east-1 | Aceptada | 2026-11-05 |
| [0002](0002-vpc-propia-dos-zonas.md) | VPC propia en dos zonas de disponibilidad | Aceptada | 2026-11-05 |
| [0003](0003-subredes-dos-capas-sin-nat.md) | Subredes en dos capas, sin NAT Gateway | Aceptada | 2026-11-05 |
| [0004](0004-security-groups-encadenados.md) | Security Groups encadenados | Aceptada | 2026-11-05 |
| [0005](0005-alb-dos-ec2-web.md) | Balanceador con dos EC2 web sin estado | Aceptada | 2026-11-05 |
| [0006](0006-nodo-iot-dedicado.md) | Nodo IoT dedicado con Docker, escribiendo directo en RDS | Aceptada | 2026-11-05 |
| [0007](0007-rds-postgresql-multi-az.md) | RDS for PostgreSQL 16 Multi-AZ como base única | Aceptada | 2026-11-05 |
| [0008](0008-seguridad-acceso-y-datos.md) | Cifrado, secretos y mínimo privilegio | Aceptada | 2026-11-05 |
| [0009](0009-disponibilidad-y-respaldo.md) | Objetivos de disponibilidad y respaldo | Aceptada | 2026-11-05 |
| [0010](0010-bi-grafana-e-ia-costo-cero.md) | Grafana OSS para BI e IA externa de capa gratuita | Aceptada | 2026-11-05 |
| [0011](0011-monitoreo-cloudwatch-sns.md) | Monitoreo con CloudWatch y SNS | Aceptada | 2026-11-05 |
| [0012](0012-monorepo.md) | Monorepo con documentación y tareas orientadas a agentes | Aceptada | 2026-11-11 |
| [0013](0013-monolito-laravel.md) | Portal y CRM como un monolito modular en Laravel | Aceptada | 2026-11-05 |
| [0014](0014-simulador-iot-python.md) | Simulador IoT en Python | Propuesta | 2026-11-11 |
| [0015](0015-proveedor-ia.md) | Proveedor de la API de IA | Aceptada | 2026-11-11 |
| [0016](0016-esquema-por-migraciones.md) | Esquema en español gestionado por migraciones de Laravel | Aceptada | 2026-11-11 |
| [0017](0017-telemetria-sin-particionar.md) | Telemetría sin particionar en el prototipo | Aceptada | 2026-11-10 |

## Estados

- **Propuesta:** opción por defecto, aún no confirmada por el equipo. Se puede seguir, pero no conviene construir mucho encima sin confirmarla.
- **Aceptada:** vigente. El código no la contradice.
- **Reemplazada por ADR-NNNN:** ya no rige; se conserva como historia.
- **Rechazada:** se evaluó y se descartó; se conserva para no repetir la discusión.

## Cuándo escribir un ADR

- Al elegir entre alternativas con efecto duradero: una dependencia importante, un proveedor externo, una forma de desplegar.
- Al cambiar el modelo de datos de un modo que afecte a más de un componente.
- Al desviarse del diseño por una restricción del entorno (por ejemplo, un límite del Learner Lab).
- Al agregar cualquier servicio de AWS con costo.

No hace falta un ADR para decisiones locales y fáciles de revertir, como el nombre de una clase o la estructura de una vista.

## Cómo escribir uno

1. Copia [`0000-plantilla.md`](0000-plantilla.md) con el siguiente número libre: `NNNN-titulo-en-minusculas.md`.
2. Escríbelo breve: una pantalla suele bastar.
3. Agrégalo al índice de este archivo.
4. Los ADR aceptados no se reescriben. Si la decisión cambia, crea uno nuevo y marca el anterior como «Reemplazada por ADR-NNNN».
