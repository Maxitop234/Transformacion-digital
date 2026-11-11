<?php

namespace App\Domain\Logistica;

use App\Models\Alerta;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Vendedor;
use App\Notifications\AlertaFrioNueva;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa de cada alerta de ruptura de frío nueva (T-017): las crea el
 * simulador directo en la base, así que un trabajo programado
 * (`alertas:notificar`, cada minuto) revisa las que aparecieron desde la
 * última pasada.
 *
 * Destinatarios: los despachadores activos y los vendedores de los clientes
 * afectados (RN-10: cada vendedor solo recibe las de su cartera). El canal es
 * correo (`MAIL_MAILER`; en local va al log). La marca de la última alerta
 * avisada vive en la caché (en la base, compartida por las dos instancias),
 * sin cambiar el modelo de datos. La primera pasada solo fija la marca, para
 * no avisar de golpe todo el historial.
 */
final class NotificarAlertasNuevas
{
    public const CLAVE = 'alertas.ultima_notificada';

    public function __construct(private readonly AfectadosPorAlerta $afectados) {}

    /** @return int alertas avisadas */
    public function __invoke(): int
    {
        $ultima = Cache::get(self::CLAVE);

        if ($ultima === null) {
            Cache::forever(self::CLAVE, (int) Alerta::max('id_alerta'));

            return 0;
        }

        $nuevas = Alerta::where('id_alerta', '>', $ultima)
            ->where('tipo', 'ruptura_frio')
            ->where('estado', '<>', 'resuelta')
            ->with('telemetria')
            ->orderBy('id_alerta')
            ->get();

        $marca = (int) (Alerta::where('id_alerta', '>', $ultima)->max('id_alerta') ?? $ultima);

        if ($nuevas->isEmpty()) {
            Cache::forever(self::CLAVE, $marca);

            return 0;
        }

        $afectados = $this->afectados->porAlerta(null, $nuevas->pluck('id_alerta'));
        $despachadores = Usuario::where('activo', true)
            ->whereHas('rol', fn ($q) => $q->where('nombre', Rol::DESPACHADOR))
            ->pluck('email');

        foreach ($nuevas as $alerta) {
            $filas = $afectados->get($alerta->id_alerta, collect())->values();
            $vendedores = Vendedor::whereIn('id_vendedor', $filas->pluck('id_vendedor')->filter()->unique())
                ->where('activo', true)
                ->with('usuario')
                ->get()
                ->filter(fn (Vendedor $v) => $v->usuario?->activo)
                ->map(fn (Vendedor $v) => $v->usuario->email);

            $destinatarios = $despachadores->merge($vendedores)->unique()->values()->all();

            if ($destinatarios !== []) {
                Notification::route('mail', $destinatarios)->notify(new AlertaFrioNueva($alerta, $filas));
            }
        }

        Cache::forever(self::CLAVE, $marca);

        return $nuevas->count();
    }
}
