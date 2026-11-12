<?php

use App\Http\Middleware\CerrarSesionSiInactivo;
use App\Http\Middleware\ExigirRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            Route::group([], base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El modo mantenimiento se guarda en la base (caché `database`). /health
        // queda fuera para que una base caída responda 503 y no un error 500.
        $middleware->preventRequestsDuringMaintenance(except: ['health']);

        // En AWS el HTTPS termina en el balanceador (T-024): se confía en sus
        // cabeceras X-Forwarded-* para generar URLs https y reconocer la IP del
        // cliente. Es seguro porque sg-web solo admite tráfico desde el ALB.
        $middleware->trustProxies(at: '*');

        $middleware->alias(['rol' => ExigirRol::class]);
        $middleware->web(append: [CerrarSesionSiInactivo::class]);

        // Quien ya inició sesión y abre /login vuelve a su inicio según el rol.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->rutaInicio());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
