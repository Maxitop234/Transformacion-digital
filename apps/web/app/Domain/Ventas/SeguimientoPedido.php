<?php

namespace App\Domain\Ventas;

use App\Domain\Logistica\AfectadosPorAlerta;
use App\Models\DespachoPedido;
use App\Models\EstadoPedido;
use App\Models\HistorialEstadoPedido;
use App\Models\Pedido;
use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Lo que el cliente ve de su pedido en el portal (T-012, SECURITY.md §6):
 * línea de tiempo desde `historial_estado_pedido` (RN-09) y, por cada
 * despacho, camión, parada, resultado y rupturas de frío del trayecto.
 *
 * Nunca carga el conductor: solo se leen columnas de `despacho` sin
 * `id_conductor`, el camión y las lecturas que generaron alertas.
 */
final class SeguimientoPedido
{
    public function __construct(
        private readonly AfectadosPorAlerta $afectados,
        private readonly TransicionesPedido $transiciones,
    ) {}

    /**
     * @return Collection<int, array{estado: string, fecha_hora: CarbonInterface, comentario: ?string}>
     */
    public function lineaDeTiempo(Pedido $pedido): Collection
    {
        return $pedido->historialesEstadoPedido()
            ->with('estadoPedido:id_estado_pedido,nombre')
            ->orderBy('fecha_hora')->orderBy('id_historial')
            ->get()
            ->map(fn (HistorialEstadoPedido $h) => [
                'estado' => $h->estadoPedido->nombre,
                'fecha_hora' => $h->fecha_hora,
                'comentario' => $h->comentario,
            ]);
    }

    /**
     * Paradas del pedido, de la más reciente a la más antigua (un pedido con
     * entrega fallida se reprograma en otro despacho).
     *
     * @return Collection<int, array{camion: string, patente: string, estado_despacho: string, fecha_programada: ?CarbonInterface, salida: ?CarbonInterface, llegada: ?CarbonInterface, orden_parada: int, estado_entrega: string, fecha_entrega: ?CarbonInterface, rupturas: Collection<int, object>}>
     */
    public function despachos(Pedido $pedido): Collection
    {
        $alertas = $this->afectados->delPedido($pedido->id_pedido);

        return DespachoPedido::where('id_pedido', $pedido->id_pedido)
            ->with([
                'despacho:id_despacho,camion_id,estado,fecha_programada,salida_real,llegada_real',
                'despacho.camion:camion_id,patente',
            ])
            ->orderByDesc('id_despacho')
            ->get()
            ->map(fn (DespachoPedido $parada) => [
                'camion' => $parada->despacho->camion_id,
                'patente' => $parada->despacho->camion->patente,
                'estado_despacho' => $parada->despacho->estado,
                'fecha_programada' => $parada->despacho->fecha_programada,
                'salida' => $parada->despacho->salida_real,
                'llegada' => $parada->despacho->llegada_real,
                'orden_parada' => $parada->orden_parada,
                'estado_entrega' => $parada->estado_entrega,
                'fecha_entrega' => $parada->fecha_entrega,
                'rupturas' => $alertas->get($parada->id_despacho, collect()),
            ]);
    }

    /** El cliente puede cancelar si el ciclo de vida lo permite (antes de «En preparación»). */
    public function puedeCancelar(Pedido $pedido, Usuario $cliente): bool
    {
        return in_array(EstadoPedido::CANCELADO, $this->transiciones->disponibles($pedido, $cliente), true);
    }
}
