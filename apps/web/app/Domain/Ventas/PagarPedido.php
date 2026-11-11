<?php

namespace App\Domain\Ventas;

use App\Models\EstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pasarela de pago simulada para clientes B2C (RN-05). Cada intento queda
 * en `pago`; un pago aprobado confirma el pedido a través de
 * `TransicionesPedido` (actor: el sistema). Un rechazo deja el pedido
 * «Pendiente» para reintentar mientras dure la reserva.
 */
final class PagarPedido
{
    public const METODOS = ['tarjeta', 'transferencia'];

    public const RESULTADOS = ['aprobado', 'rechazado'];

    public function __construct(private readonly TransicionesPedido $transiciones) {}

    /** Momento en que vence la reserva de un pedido B2C sin pago. */
    public static function vencimiento(Pedido $pedido): CarbonInterface
    {
        return $pedido->fecha_pedido->copy()->addMinutes(config('negocio.reserva_stock_minutos_b2c'));
    }

    /**
     * @throws ConflictoPedido pedido que ya no espera pago o plazo vencido (409)
     */
    public function __invoke(Pedido $pedido, string $metodo, string $resultado): Pago
    {
        return DB::transaction(function () use ($pedido, $metodo, $resultado) {
            $pedido = Pedido::whereKey($pedido->id_pedido)->with('estadoPedido')->lockForUpdate()->firstOrFail();
            $estado = $pedido->estadoPedido->nombre;

            if ($estado !== EstadoPedido::PENDIENTE) {
                throw new TransicionInvalida($estado, EstadoPedido::CONFIRMADO);
            }

            if (now()->greaterThan(self::vencimiento($pedido))) {
                throw new ConflictoPedido('Venció el plazo para pagar este pedido: la reserva se libera y el pedido se cancela.');
            }

            $pago = Pago::create([
                'id_pedido' => $pedido->id_pedido,
                'monto' => self::total($pedido),
                'metodo' => $metodo,
                'estado' => $resultado,
                'referencia_externa' => 'SIM-'.$pedido->id_pedido.'-'.Str::upper(Str::random(8)),
                'fecha_hora' => now(),
            ]);

            if ($resultado === 'aprobado') {
                $this->transiciones->cambiar($pedido, EstadoPedido::CONFIRMADO, null, 'Pago en línea aprobado');
            }

            return $pago;
        });
    }

    /** Total con IVA del pedido, desde sus precios pactados. */
    public static function total(Pedido $pedido): int
    {
        return Montos::totales(
            $pedido->detallesPedido()->get()->map(fn ($d) => [(string) $d->precio_unitario_pactado, (string) $d->cantidad]),
        )['total'];
    }
}
