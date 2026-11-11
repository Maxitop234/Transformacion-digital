<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Catalogo\Precios;
use App\Domain\Ventas\Montos;
use App\Domain\Ventas\PagarPedido;
use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pasarela de pago simulada (API.md §1.3, solo B2C). Un pedido ajeno
 * responde 404 (RN-10); uno B2B, 403 (paga con orden de compra).
 */
class PagoController extends Controller
{
    public function show(Request $request, int $pedido, Precios $precios): View
    {
        $pedido = $this->pedido($request, $pedido, $precios);
        $detalles = $pedido->detallesPedido()->with('producto')->get();

        return view('portal.pagos.show', [
            'pedido' => $pedido,
            'detalles' => $detalles,
            'totales' => Montos::totales($detalles->map(fn ($d) => [(string) $d->precio_unitario_pactado, (string) $d->cantidad])),
            'vence' => PagarPedido::vencimiento($pedido),
            'metodos' => PagarPedido::METODOS,
        ]);
    }

    public function store(Request $request, int $pedido, Precios $precios, PagarPedido $pagar): RedirectResponse
    {
        $pedido = $this->pedido($request, $pedido, $precios);
        $datos = $request->validate([
            'metodo' => ['required', Rule::in(PagarPedido::METODOS)],
            'resultado' => ['required', Rule::in(PagarPedido::RESULTADOS)],
        ]);

        $pago = $pagar($pedido, $datos['metodo'], $datos['resultado']);

        if ($pago->estado === 'aprobado') {
            return redirect()->route('portal.inicio')
                ->with('estado', "Pago aprobado: tu pedido N.º {$pedido->id_pedido} quedó Confirmado.");
        }

        return redirect()->route('portal.pedidos.pago', $pedido->id_pedido)->with('aviso', sprintf(
            'El pago fue rechazado. Puedes intentarlo de nuevo hasta las %s.',
            PagarPedido::vencimiento($pedido)->setTimezone(config('app.timezone'))->format('H:i'),
        ));
    }

    private function pedido(Request $request, int $id, Precios $precios): Pedido
    {
        $pedido = Pedido::visiblePara($request->user())->with('estadoPedido')->findOrFail($id);
        abort_if($precios->perfil($request->user())->esB2B, 403, 'Las empresas pagan con orden de compra.');

        return $pedido;
    }
}
