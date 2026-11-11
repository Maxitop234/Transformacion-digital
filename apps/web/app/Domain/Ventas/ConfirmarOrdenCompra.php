<?php

namespace App\Domain\Ventas;

use App\Domain\Crm\GanarOportunidadConPedido;
use App\Models\EstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * RN-05 · El vendedor valida la orden de compra de un pedido B2B: el pago
 * `credito_30d` pasa a `aprobado` y el pedido a «Confirmado» por
 * `TransicionesPedido` (que valida rol y cartera, RN-10, y escribe el
 * historial). Si el cliente tenía una oportunidad en «Propuesta» o
 * «Negociación», se gana con este pedido (BUSINESS_RULES.md §6).
 */
final class ConfirmarOrdenCompra
{
    public function __construct(
        private readonly TransicionesPedido $transiciones,
        private readonly GanarOportunidadConPedido $ganarOportunidad,
    ) {}

    /**
     * @throws ConflictoPedido sin orden de compra pendiente o estado inválido (409)
     */
    public function __invoke(Pedido $pedido, Usuario $vendedor, ?string $comentario = null): Pedido
    {
        return DB::transaction(function () use ($pedido, $vendedor, $comentario) {
            $pedido = Pedido::whereKey($pedido->id_pedido)->with(['estadoPedido', 'direccionDespacho'])->lockForUpdate()->firstOrFail();

            $pago = Pago::where('id_pedido', $pedido->id_pedido)
                ->where('metodo', 'credito_30d')
                ->where('estado', 'pendiente')
                ->lockForUpdate()
                ->first();

            if ($pago === null) {
                throw new ConflictoPedido('Este pedido no tiene una orden de compra pendiente de validar.');
            }

            $this->transiciones->cambiar(
                $pedido,
                EstadoPedido::CONFIRMADO,
                $vendedor,
                trim("Orden de compra {$pago->referencia_externa} validada. ".(string) $comentario),
            );

            $pago->update(['estado' => 'aprobado', 'fecha_hora' => now()]);
            ($this->ganarOportunidad)($pedido, $pedido->direccionDespacho->id_cliente);

            return $pedido->refresh();
        });
    }
}
