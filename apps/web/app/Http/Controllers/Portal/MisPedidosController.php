<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Catalogo\Precios;
use App\Domain\Ventas\Montos;
use App\Domain\Ventas\RepetirPedido;
use App\Domain\Ventas\SeguimientoPedido;
use App\Domain\Ventas\TransicionesPedido;
use App\Http\Controllers\Controller;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Seguimiento de pedidos en el portal (T-012, API.md §1.3). Todo pasa por
 * `Pedido::visiblePara()`: el cliente ve solo los pedidos de sus puntos de
 * entrega (B2B: los de toda la empresa) y un pedido ajeno responde 404.
 */
class MisPedidosController extends Controller
{
    public function index(Request $request, Precios $precios): View
    {
        $pedidos = Pedido::visiblePara($request->user())
            ->with(['estadoPedido', 'direccionDespacho.comuna', 'detallesPedido'])
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id_pedido')
            ->paginate(20);

        $pedidos->getCollection()->each(fn (Pedido $p) => $p->setAttribute('totales', self::totales($p)));

        return view('portal.pedidos.index', [
            'pedidos' => $pedidos,
            'perfil' => $precios->perfil($request->user()),
        ]);
    }

    public function show(Request $request, int $pedido, Precios $precios, SeguimientoPedido $seguimiento): View
    {
        $pedido = $this->pedido($request, $pedido);
        $pedido->load(['direccionDespacho.comuna', 'detallesPedido.producto', 'pagos' => fn ($q) => $q->orderBy('fecha_hora')]);
        $perfil = $precios->perfil($request->user());

        return view('portal.pedidos.show', [
            'pedido' => $pedido,
            'perfil' => $perfil,
            'totales' => self::totales($pedido),
            'lineaDeTiempo' => $seguimiento->lineaDeTiempo($pedido),
            'despachos' => $seguimiento->despachos($pedido),
            'puedeCancelar' => $seguimiento->puedeCancelar($pedido, $request->user()),
            'puedePagar' => ! $perfil->esB2B && $pedido->estadoPedido->nombre === EstadoPedido::PENDIENTE,
        ]);
    }

    /** Cancela por el ciclo de vida del pedido: solo antes de «En preparación» (409 si no). */
    public function cancelar(Request $request, int $pedido, TransicionesPedido $transiciones): RedirectResponse
    {
        $pedido = $this->pedido($request, $pedido);
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:250']], [], ['motivo' => 'motivo']);

        $transiciones->cambiar($pedido, EstadoPedido::CANCELADO, $request->user(), 'Cancelado por el cliente: '.trim($datos['motivo']));

        return redirect()->route('portal.mis-pedidos.show', $pedido->id_pedido)
            ->with('estado', "Pedido N.º {$pedido->id_pedido} cancelado. El stock reservado quedó liberado.");
    }

    /** Repetir pedido (B2B): los productos van al carrito con el precio vigente (RN-01). */
    public function repetir(Request $request, int $pedido, Precios $precios, RepetirPedido $repetir): RedirectResponse
    {
        $pedido = $this->pedido($request, $pedido);
        $perfil = $precios->perfil($request->user());
        abort_unless($perfil->esB2B, 403, 'Repetir pedidos está disponible para cuentas de empresa.');

        $resultado = $repetir($perfil, $pedido);
        $redireccion = redirect()->route('portal.carrito');

        if ($resultado['avisos'] !== []) {
            $redireccion->with('aviso', implode(' ', $resultado['avisos']));
        }

        return $redireccion->with('estado', $resultado['agregados'] > 0
            ? "Agregamos {$resultado['agregados']} producto(s) del pedido N.º {$pedido->id_pedido} a tu carrito con los precios vigentes."
            : "Ningún producto del pedido N.º {$pedido->id_pedido} está disponible hoy.");
    }

    private function pedido(Request $request, int $id): Pedido
    {
        return Pedido::visiblePara($request->user())->with('estadoPedido')->findOrFail($id);
    }

    /** @return array{neto: int, iva: int, total: int} */
    private static function totales(Pedido $pedido): array
    {
        return Montos::totales($pedido->detallesPedido->map(fn ($d) => [(string) $d->precio_unitario_pactado, (string) $d->cantidad]));
    }
}
