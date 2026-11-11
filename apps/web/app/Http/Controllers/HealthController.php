<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chequeo del balanceador (ADR-0005, docs/API.md).
 *
 * Responde 200 si la aplicación llega a la base y 503 si no. No expone
 * detalles del error ni datos sensibles.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            Log::warning('Health check sin conexión a la base', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'unavailable'], 503);
        }

        return response()->json(['status' => 'ok']);
    }
}
