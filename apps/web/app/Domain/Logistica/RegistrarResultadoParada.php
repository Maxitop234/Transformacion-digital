<?php

namespace App\Domain\Logistica;

use App\Domain\Ventas\TransicionesPedido;
use App\Models\Despacho;
use App\Models\DespachoPedido;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resultado de una parada (T-017, BUSINESS_RULES.md §4):
 *
 * - `entregado`: recepción conforme; el pedido pasa a «Entregado» y
 *   `TransicionesPedido` libera la reserva y registra la `salida` de stock.
 * - `rechazado`: el cliente rechaza la carga (por ejemplo, fuera de
 *   temperatura); el pedido pasa a «Rechazado» (`merma`) y se abre un ticket
 *   de reclamo de prioridad alta para su vendedor.
 * - `fallido`: no se pudo entregar; el pedido vuelve a «Confirmado» y puede
 *   ir en otro despacho.
 *
 * Rechazado y fallido exigen motivo. Cuando no quedan paradas pendientes,
 * el despacho se cierra (`finalizado`, `llegada_real`).
 */
final class RegistrarResultadoParada
{
    public const RESULTADOS = ['entregado', 'rechazado', 'fallido'];

    public function __construct(private readonly TransicionesPedido $transiciones) {}

    public function __invoke(Despacho $despacho, int $idPedido, string $resultado, ?string $motivo, Usuario $actor): DespachoPedido
    {
        $motivo = $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null;

        if ($resultado !== 'entregado' && $motivo === null) {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo del rechazo o de la entrega fallida.']);
        }

        return DB::transaction(function () use ($despacho, $idPedido, $resultado, $motivo, $actor) {
            $despacho = Despacho::whereKey($despacho->id_despacho)->lockForUpdate()->firstOrFail();

            if ($despacho->estado !== 'en_ruta') {
                throw new ConflictoLogistica('Las paradas se registran con el despacho en ruta.');
            }

            $parada = DespachoPedido::where('id_despacho', $despacho->id_despacho)->where('id_pedido', $idPedido)->first();

            if ($parada === null) {
                abort(404);
            }
            if ($parada->estado_entrega !== 'pendiente') {
                throw new ConflictoLogistica("La parada del pedido N.º {$idPedido} ya tiene resultado.");
            }

            $pedido = Pedido::with('direccionDespacho.cliente')->findOrFail($idPedido);

            $parada->update([
                'estado_entrega' => match ($resultado) {
                    'entregado' => 'entregado',
                    'rechazado' => 'rechazado_temperatura',
                    'fallido' => 'fallido',
                },
                'fecha_entrega' => $resultado === 'fallido' ? null : now(),
                'motivo_falla' => $motivo,
            ]);

            match ($resultado) {
                'entregado' => $this->transiciones->cambiar($pedido, EstadoPedido::ENTREGADO, $actor, $motivo ?? 'Recepción conforme'),
                'rechazado' => $this->rechazar($pedido, $motivo, $actor),
                'fallido' => $this->transiciones->cambiar($pedido, EstadoPedido::CONFIRMADO, $actor, "Entrega fallida: {$motivo}"),
            };

            if (! DespachoPedido::where('id_despacho', $despacho->id_despacho)->where('estado_entrega', 'pendiente')->exists()) {
                $despacho->update(['estado' => 'finalizado', 'llegada_real' => now()]);
            }

            return $parada;
        });
    }

    private function rechazar(Pedido $pedido, string $motivo, Usuario $actor): void
    {
        $this->transiciones->cambiar($pedido, EstadoPedido::RECHAZADO, $actor, $motivo);

        $cliente = $pedido->direccionDespacho->cliente;

        Ticket::create([
            'id_cliente' => $cliente->id_cliente,
            'id_pedido' => $pedido->id_pedido,
            'id_vendedor' => $cliente->id_vendedor,
            'asunto' => "Carga rechazada en la entrega del pedido N.º {$pedido->id_pedido}",
            'descripcion' => "El cliente rechazó la carga al recibir. Motivo registrado por logística: {$motivo}",
            'categoria' => 'reclamo',
            'prioridad' => 'alta',
            'estado' => 'abierto',
            'clasificado_por_ia' => false,
        ]);
    }
}
