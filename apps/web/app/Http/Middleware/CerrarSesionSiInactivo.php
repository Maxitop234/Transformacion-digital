<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Desactivar un usuario revoca su acceso de inmediato (SECURITY.md §6): si la
 * cuenta se desactivó con la sesión abierta, se cierra en la siguiente petición.
 */
class CerrarSesionSiInactivo
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Usuario|null $usuario */
        $usuario = $request->user();

        if ($usuario && ! $usuario->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('auth.inactivo')]);
        }

        return $next($request);
    }
}
