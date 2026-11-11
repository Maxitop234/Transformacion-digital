<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Ia\AsistentePortal;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Asistente IA del portal (T-021, API.md §1.5). El navegador nunca habla
 * con la IA: este controlador recibe el mensaje y `AsistentePortal` arma el
 * contexto con los datos del propio cliente. Límite por usuario: 429.
 */
class AsistenteController extends Controller
{
    public function show(): View
    {
        return view('portal.asistente');
    }

    public function store(Request $request, AsistentePortal $asistente): JsonResponse
    {
        $datos = $request->validate(['mensaje' => ['required', 'string', 'max:500']]);

        return response()->json($asistente($request->user(), $datos['mensaje']));
    }
}
