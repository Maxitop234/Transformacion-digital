<?php

namespace App\Domain\Ventas;

use App\Models\EstadoPedido;
use App\Models\Pedido;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * RN-04: un pedido B2C «Pendiente» sin pago tras
 * `negocio.reserva_stock_minutos_b2c` (30) minutos se cancela y libera su
 * reserva. Lo ejecuta el planificador (`pedidos:cancelar-sin-pago`).
 *
 * Los B2B no vencen: esperan que el vendedor valide la orden de compra.
 */
final class CancelarPedidosSinPago
{
    public function __construct(private readonly TransicionesPedido $transiciones) {}

    /** @return int pedidos cancelados */
    public function __invoke(?CarbonInterface $ahora = null): int
    {
        $limite = ($ahora ?? now())->copy()->subMinutes(config('negocio.reserva_stock_minutos_b2c'));

        $vencidos = Pedido::whereHas('estadoPedido', fn (Builder $q) => $q->where('nombre', EstadoPedido::PENDIENTE))
            ->whereHas('direccionDespacho.cliente.tipoCliente', fn (Builder $q) => $q->where('codigo', 'B2C'))
            ->where('fecha_pedido', '<=', $limite)
            ->orderBy('id_pedido')
            ->pluck('id_pedido');

        $cancelados = 0;

        foreach ($vencidos as $idPedido) {
            try {
                $this->transiciones->cambiar(Pedido::findOrFail($idPedido), EstadoPedido::CANCELADO, null, 'Sin pago dentro del plazo de reserva');
                $cancelados++;
            } catch (TransicionInvalida) {
                // Se pagó (o cambió) entre la consulta y el bloqueo: nada que hacer.
            }
        }

        return $cancelados;
    }
}
