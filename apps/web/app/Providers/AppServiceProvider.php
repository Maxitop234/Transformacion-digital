<?php

namespace App\Providers;

use App\Domain\Clientes\Permisos;
use App\Models\Usuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Un gate por área de la aplicación según la matriz de roles.
        foreach (Permisos::AREAS as $area => $roles) {
            Gate::define($area, fn (Usuario $usuario) => $usuario->tieneRol(...$roles));
        }

        // Respaldo por IP para el formulario de inicio de sesión; el límite
        // por cuenta lo lleva LoginController.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
