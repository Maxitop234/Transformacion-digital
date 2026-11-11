<?php

namespace App\Domain\Logistica;

use App\Domain\Ventas\TransicionesPedido;
use App\Models\Camion;
use App\Models\Conductor;
use App\Models\Despacho;
use App\Models\DespachoPedido;
use App\Models\DetallePedido;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\RangoConservacion;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Arma un despacho (T-017): camión, conductor, fecha y pedidos «Confirmado»
 * en el orden de sus paradas. Cada pedido pasa a «En preparación» por
 * `TransicionesPedido` (rol despachador o administrador).
 *
 * RN-07: el camión tiene una sola cámara, así que todos los productos del
 * despacho deben compartir el rango de conservación. Un pedido que mezcla
 * rangos no cabe en ningún despacho.
 */
final class ArmarDespacho
{
    public function __construct(private readonly TransicionesPedido $transiciones) {}

    /**
     * @param  list<int>  $idsPedido  en el orden de las paradas
     */
    public function __invoke(Usuario $actor, string $camionId, int $idConductor, string $fecha, array $idsPedido): Despacho
    {
        $idsPedido = array_values(array_unique(array_map('intval', $idsPedido)));

        if ($idsPedido === []) {
            throw ValidationException::withMessages(['pedidos' => 'Elige al menos un pedido.']);
        }

        if (CarbonImmutable::parse($fecha, config('app.timezone'))->lt(now(config('app.timezone'))->startOfDay())) {
            throw ValidationException::withMessages(['fecha_programada' => 'La fecha del despacho no puede estar en el pasado.']);
        }

        return DB::transaction(function () use ($actor, $camionId, $idConductor, $fecha, $idsPedido) {
            $camion = Camion::whereKey($camionId)->where('activo', true)->lockForUpdate()->first();
            $conductor = Conductor::whereKey($idConductor)->where('activo', true)->lockForUpdate()->first();

            if ($camion === null) {
                throw ValidationException::withMessages(['camion_id' => 'Elige un camión activo.']);
            }
            if ($conductor === null) {
                throw ValidationException::withMessages(['id_conductor' => 'Elige un conductor activo.']);
            }
            if (Despacho::where('camion_id', $camionId)->whereIn('estado', ['programado', 'en_ruta'])->exists()) {
                throw ValidationException::withMessages(['camion_id' => "El camión {$camionId} ya tiene un despacho programado o en ruta."]);
            }
            if (Despacho::where('id_conductor', $idConductor)->whereIn('estado', ['programado', 'en_ruta'])->exists()) {
                throw ValidationException::withMessages(['id_conductor' => 'El conductor ya tiene un despacho programado o en ruta.']);
            }

            $pedidos = Pedido::whereIn('id_pedido', $idsPedido)->with('estadoPedido')->lockForUpdate()->get()->keyBy('id_pedido');

            foreach ($idsPedido as $id) {
                $pedido = $pedidos->get($id);

                if ($pedido === null || $pedido->estadoPedido->nombre !== EstadoPedido::CONFIRMADO) {
                    throw ValidationException::withMessages(['pedidos' => "El pedido N.º {$id} no está «Confirmado»."]);
                }

                // Un pedido devuelto por entrega fallida ya no tiene paradas pendientes.
                if (DespachoPedido::where('id_pedido', $id)->where('estado_entrega', 'pendiente')->exists()) {
                    throw ValidationException::withMessages(['pedidos' => "El pedido N.º {$id} ya está en otro despacho."]);
                }
            }

            $this->exigirUnSoloRango($idsPedido);

            $despacho = Despacho::create([
                'camion_id' => $camionId,
                'id_conductor' => $idConductor,
                'estado' => 'programado',
                'fecha_programada' => CarbonImmutable::parse($fecha)->toDateString(),
            ]);

            foreach ($idsPedido as $orden => $id) {
                DespachoPedido::create([
                    'id_despacho' => $despacho->id_despacho,
                    'id_pedido' => $id,
                    'orden_parada' => $orden + 1,
                    'estado_entrega' => 'pendiente',
                ]);
                $this->transiciones->cambiar($pedidos[$id], EstadoPedido::EN_PREPARACION, $actor, "Asignado al despacho {$despacho->id_despacho}");
            }

            return $despacho;
        });
    }

    /** @param  list<int>  $idsPedido */
    private function exigirUnSoloRango(array $idsPedido): void
    {
        $rangos = Producto::query()
            ->whereIn('id_producto', DetallePedido::whereIn('id_pedido', $idsPedido)->select('id_producto'))
            ->distinct()
            ->pluck('id_rango');

        if ($rangos->count() > 1) {
            $nombres = RangoConservacion::whereIn('id_rango', $rangos)->orderBy('nombre')->pluck('nombre')->implode(', ');

            throw ValidationException::withMessages(['pedidos' => "RN-07: un despacho no puede mezclar rangos de conservación ({$nombres}). El camión tiene una sola cámara."]);
        }
    }
}
