# ADR-0015 · Proveedor de la API de IA

| Campo | Valor |
| --- | --- |
| Estado | Aceptada |
| Fecha | 2026-11-11 |
| Drivers | D8 (ver [`ARCHITECTURE.md`](../ARCHITECTURE.md#1-requisitos-que-guían-la-arquitectura)) |

## Contexto

ADR-0010 decide usar una API de IA de capa gratuita, pero no cuál. Las capas gratuitas cambian de límites y de modelos con frecuencia, y la demo no puede depender de una cuota agotada.

Criterios: costo cero sin tarjeta, cuota suficiente para la demo, salida JSON validable contra un esquema y latencia baja. Candidatos: la capa gratuita de Groq (modelos abiertos) y la de Google Gemini.

Lo verificado en T-021 (noviembre de 2026):

- **Groq**: cuenta gratuita sin tarjeta. Endpoint compatible con OpenAI (`https://api.groq.com/openai/v1/chat/completions`). Admite `response_format` con `json_schema` estricto (decodificación restringida) en `openai/gpt-oss-20b` y `openai/gpt-oss-120b`. Para esos modelos, la página oficial de límites indica del orden de 30 solicitudes por minuto y 1.000 por día. Los límites son por organización y cada cuenta los ve en su consola.
- **Gemini**: capa gratuita sin tarjeta, limitada a modelos Flash y Flash-Lite. Desde fines de 2025 los recortes de cuota fueron grandes y Google ya no publica cifras fijas por modelo. Ofrece un endpoint compatible con OpenAI (`https://generativelanguage.googleapis.com/v1beta/openai`).

## Decisión

- **Proveedor principal: Groq**, modelo `openai/gpt-oss-20b`, con `json_schema` estricto y temperatura 0.
- **Respaldo: Gemini** (`gemini-2.5-flash`) por su endpoint compatible con OpenAI.
- **Una sola implementación**, `ClienteIaCompatibleOpenAi`, detrás de la interfaz `ClienteIa`. Cambiar de proveedor es cambiar `AI_PROVIDER` (`groq` o `gemini`) y `AI_API_KEY` en el `.env`; `AI_MODEL` y `AI_URL_BASE` permiten afinar sin tocar código.
- La clasificación corre en el worker de colas, con dos reintentos. El asistente responde en línea, con un límite por usuario configurable (`AI_ASISTENTE_POR_MINUTO`, `AI_ASISTENTE_POR_DIA`).

## Alternativas consideradas

- **Gemini como principal**: cuota gratuita menos predecible y sin cifras publicadas. Queda como respaldo.
- **Un cliente distinto por proveedor**: más código sin beneficio, porque ambos aceptan el formato de OpenAI.
- **Alojar un modelo abierto en una EC2**: consume créditos y memoria que el laboratorio no tiene.
- **Reglas con palabras clave en vez de IA**: no cumple la exigencia de integrar un servicio de IA externo.

## Consecuencias

- Los tests usan `ClienteIaFalso` o `Http::fake` y no gastan cuota. `phpunit.xml` fuerza `AI_API_KEY` vacía.
- No se envían datos personales: el texto pasa por `FiltroDatosPersonales` y el contexto solo lleva identificadores internos y estados (RN-11, SECURITY.md §9). Aun así, conviene revisar las condiciones de uso de datos de la cuenta antes de la defensa.
- Sin clave, con `AI_ENABLED=false` o con la cuota agotada, los tickets quedan `sin_clasificar` y el asistente deriva a ticket. Nada se bloquea.
- Para la defensa conviene tener una clave de Gemini lista: cambiar `AI_PROVIDER=gemini` y reiniciar `web` y `worker`.
