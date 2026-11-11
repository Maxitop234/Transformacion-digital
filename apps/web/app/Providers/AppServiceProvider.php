<?php

namespace App\Providers;

use App\Domain\Clientes\Permisos;
use App\Domain\Ia\ClienteIa;
use App\Domain\Ia\ClienteIaCompatibleOpenAi;
use App\Events\TicketCreado;
use App\Jobs\ClasificarTicketConIa;
use App\Models\Usuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Proveedor de IA (ADR-0015): los tests lo reemplazan por un cliente falso.
        $this->app->bind(ClienteIa::class, ClienteIaCompatibleOpenAi::class);
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

        // Asistente IA (RN-11): cuota por usuario para proteger la capa gratuita.
        RateLimiter::for('asistente', fn (Request $request) => [
            Limit::perMinute(config('ia.asistente_por_minuto'))->by('asistente-min:'.$request->user()?->id_usuario),
            Limit::perDay(config('ia.asistente_por_dia'))->by('asistente-dia:'.$request->user()?->id_usuario),
        ]);

        // T-021: cada ticket nuevo se clasifica con IA en la cola (T-018 emite el evento).
        Event::listen(TicketCreado::class, fn (TicketCreado $evento) => ClasificarTicketConIa::dispatch($evento->idTicket));
    }
}
