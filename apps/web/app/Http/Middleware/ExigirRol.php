<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `->middleware('rol:administrador,vendedor')`: responde 403 si el usuario
 * autenticado no tiene uno de los roles (API.md §1.1).
 */
class ExigirRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var Usuario|null $usuario */
        $usuario = $request->user();

        abort_unless($usuario && $usuario->tieneRol(...$roles), 403);

        return $next($request);
    }
}
