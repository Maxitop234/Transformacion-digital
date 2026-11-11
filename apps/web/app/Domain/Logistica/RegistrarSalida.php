<?php

namespace App\Domain\Logistica;

use App\Domain\Ventas\TransicionesPedido;
use App\Models\Despacho;
use App\Models\DespachoPedido;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * El camión sale de bodega (T-017): el despacho pasa a `en_ruta` con su
 * `salida_real` y cada pedido a «En ruta». Desde aquí el simulador IoT toma
 * el rango de frío de la carga (`iot_umbral_camion`).
 */
final class RegistrarSalida
{
    public function __construct(private readonly TransicionesPedido $transiciones) {}

    public function __invoke(Despacho $despacho, Usuario $actor): Despacho
    {
        return DB::transaction(function () use ($despacho, $actor) {
            $despacho = Despacho::whereKey($despacho->id_despacho)->lockForUpdate()->firstOrFail();

            if ($despacho->estado !== 'programado') {
                throw new ConflictoLogistica('Solo sale un despacho programado.');
            }

            $despacho->update(['estado' => 'en_ruta', 'salida_real' => now()]);

            $paradas = DespachoPedido::where('id_despacho', $despacho->id_despacho)->orderBy('orden_parada')->get();

            foreach ($paradas as $parada) {
                $this->transiciones->cambiar(Pedido::findOrFail($parada->id_pedido), EstadoPedido::EN_RUTA, $actor, "Salida del camión {$despacho->camion_id}");
            }

            return $despacho;
        });
    }
}
