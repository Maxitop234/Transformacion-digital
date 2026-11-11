<?php

/*
|--------------------------------------------------------------------------
| API de IA externa (ADR-0010, ADR-0015, docs/API.md §3)
|--------------------------------------------------------------------------
|
| Cualquier proveedor con API compatible con OpenAI (`/chat/completions` y
| `response_format` con `json_schema`). `AI_PROVIDER` elige uno de los
| proveedores conocidos (Groq por defecto, Gemini de respaldo); `AI_URL_BASE`
| y `AI_MODEL` permiten sobrescribirlos. La clave vive solo en el `.env`.
|
*/

$proveedores = [
    'groq' => ['url_base' => 'https://api.groq.com/openai/v1', 'modelo' => 'openai/gpt-oss-20b'],
    'gemini' => ['url_base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'modelo' => 'gemini-2.5-flash'],
];

$proveedor = $proveedores[env('AI_PROVIDER') ?: 'groq'] ?? $proveedores['groq'];

return [

    // false apaga la IA: los tickets quedan para clasificación manual y el asistente deriva a ticket.
    'habilitada' => filter_var(env('AI_ENABLED', true), FILTER_VALIDATE_BOOL),

    'url_base' => rtrim((string) (env('AI_URL_BASE') ?: $proveedor['url_base']), '/'),

    'clave' => env('AI_API_KEY'),

    'modelo' => env('AI_MODEL') ?: $proveedor['modelo'],

    // Segundos antes de abandonar una llamada (API.md §3.1).
    'timeout_segundos' => (int) (env('AI_TIMEOUT_SEGUNDOS') ?: 15),

    // Intentos del trabajo de clasificación en la cola: el primero y dos reintentos.
    'intentos_clasificacion' => 3,

    // Límite del asistente por usuario, para cuidar la cuota gratuita (429).
    'asistente_por_minuto' => (int) (env('AI_ASISTENTE_POR_MINUTO') ?: 5),
    'asistente_por_dia' => (int) (env('AI_ASISTENTE_POR_DIA') ?: 50),

];
