<?php

namespace App\Domain\Ia;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Cliente para APIs compatibles con OpenAI (`POST {url_base}/chat/completions`):
 * Groq por defecto, Gemini como respaldo (ADR-0015). Salida estructurada con
 * `response_format: json_schema` estricto y temperatura 0.
 *
 * Nunca registra el contenido enviado ni la clave.
 */
final class ClienteIaCompatibleOpenAi implements ClienteIa
{
    public function completarJson(string $sistema, string $usuario, string $nombreEsquema, array $esquema): array
    {
        if (! config('ia.habilitada')) {
            throw new IaNoDisponible('La IA está deshabilitada (AI_ENABLED=false).');
        }

        $clave = config('ia.clave');

        if (! is_string($clave) || $clave === '') {
            throw new IaNoDisponible('No hay clave de la API de IA configurada (AI_API_KEY).');
        }

        try {
            $respuesta = Http::withToken($clave)
                ->acceptJson()
                ->timeout(config('ia.timeout_segundos'))
                ->post(config('ia.url_base').'/chat/completions', [
                    'model' => config('ia.modelo'),
                    'temperature' => 0,
                    'messages' => [
                        ['role' => 'system', 'content' => $sistema],
                        ['role' => 'user', 'content' => $usuario],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => ['name' => $nombreEsquema, 'strict' => true, 'schema' => $esquema],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new IaNoDisponible('La API de IA no respondió a tiempo.', previous: $e);
        }

        if ($respuesta->failed()) {
            throw new IaNoDisponible("La API de IA respondió HTTP {$respuesta->status()}.");
        }

        $contenido = $respuesta->json('choices.0.message.content');

        try {
            $datos = is_string($contenido) ? json_decode($contenido, true, 16, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException $e) {
            throw new IaNoDisponible('La API de IA no devolvió JSON válido.', previous: $e);
        }

        if (! is_array($datos)) {
            throw new IaNoDisponible('La API de IA no devolvió un objeto JSON.');
        }

        return $datos;
    }
}
