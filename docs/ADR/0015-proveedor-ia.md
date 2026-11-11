# ADR-0015 · Proveedor de la API de IA

| Campo | Valor |
| --- | --- |
| Estado | Propuesta |
| Fecha | 2026-11-11 |
| Drivers | D8 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |

## Contexto

ADR-0010 decide usar una API de IA de capa gratuita, pero no cuál. Las capas gratuitas cambian de límites y de modelos con frecuencia, y la demo no puede depender de una cuota agotada.

## Decisión

Aislar la integración detrás de la interfaz `ClienteIa` y elegir el proveedor en T-021 después de verificar sus condiciones vigentes. Candidatos: la capa gratuita de la API de Google Gemini y la de Groq (modelos abiertos). Criterios: costo cero sin tarjeta, cuota suficiente para la demo, soporte de salida JSON y latencia baja.

## Alternativas consideradas

- Alojar un modelo abierto en una EC2: consume créditos y memoria que el laboratorio no tiene.
- Reglas con palabras clave en vez de IA: no cumple la exigencia de integrar un servicio de IA externo.

## Consecuencias

- Cambiar de proveedor es cambiar una clase y dos variables de entorno.
- Los tests usan una implementación falsa: no gastan cuota.
- Hay que revisar las condiciones de uso de datos del proveedor; en cualquier caso no se le envían datos personales.
- Conviene tener configurado un segundo proveedor como respaldo para la defensa.
