<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Catalogo\PerfilPrecio;
use App\Domain\Catalogo\Precios;
use App\Domain\Ventas\CalendarioEntregas;
use App\Domain\Ventas\Carritos;
use App\Domain\Ventas\CrearPedido;
use App\Domain\Ventas\DatosPedido;
use App\Domain\Ventas\SinStock;
use App\Http\Controllers\Controller;
use App\Models\DireccionDespacho;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Checkout y creación del pedido (API.md §1.3: `GET /checkout`,
 * `POST /pedidos`). La lógica vive en `App\Domain\Ventas\CrearPedido`.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly Precios $precios) {}

    public function show(Request $request, Carritos $carritos, CalendarioEntregas $calendario): View|RedirectResponse
    {
        $perfil = $this->perfil($request);
        $resumen = $carritos->resumen($perfil);

        if ($resumen->vacio()) {
            return redirect()->route('portal.carrito')->with('estado', 'Agrega productos antes de ir al checkout.');
        }

        $minimo = CrearPedido::minimo($perfil);
        $bloqueos = [];

        if (! $perfil->puedeComprar) {
            $bloqueos[] = 'Tu cuenta aún no puede comprar.';
        }

        foreach ($resumen->lineas as $linea) {
            if (! $linea->tienePrecio()) {
                $bloqueos[] = "{$linea->producto->nombre} ya no está disponible en tu lista: quítalo del carrito.";
            } elseif ($linea->excedeDisponible()) {
                $bloqueos[] = "{$linea->producto->nombre}: la cantidad supera el disponible. Actualízala en el carrito.";
            }
        }

        if ($mixto = $resumen->rangosMixtos()) {
            $bloqueos[] = $mixto;
        }

        if (CrearPedido::montoParaMinimo($perfil, $resumen->neto, $resumen->total) < $minimo) {
            $bloqueos[] = sprintf('El pedido mínimo es %s%s.', Precios::clp($minimo), $perfil->esB2B ? ' neto' : ' con IVA');
        }

        $direcciones = DireccionDespacho::where('id_cliente', $perfil->cliente->id_cliente)
            ->where('activa', true)->with('comuna')->orderBy('alias')->get();

        if ($direcciones->isEmpty()) {
            $bloqueos[] = 'No tienes puntos de entrega activos.';
        }

        return view('portal.checkout.show', [
            'perfil' => $perfil,
            'resumen' => $resumen,
            'direcciones' => $direcciones,
            'primeraFecha' => $calendario->primeraFecha(),
            'minimo' => $minimo,
            'bloqueos' => $bloqueos,
        ]);
    }

    public function store(Request $request, CrearPedido $crear): RedirectResponse
    {
        $datos = $request->validate([
            'id_direccion' => ['required', 'integer'],
            'fecha_entrega_solicitada' => ['required', 'date_format:Y-m-d'],
            'orden_compra' => ['nullable', 'string', 'max:80'],
            'observaciones' => ['nullable', 'string', 'max:300'],
        ], [], [
            'id_direccion' => 'punto de entrega',
            'fecha_entrega_solicitada' => 'fecha de entrega',
            'orden_compra' => 'orden de compra',
        ]);

        $perfil = $this->perfil($request);

        try {
            $pedido = $crear($perfil, $request->user(), new DatosPedido(
                idDireccion: (int) $datos['id_direccion'],
                fechaEntrega: $datos['fecha_entrega_solicitada'],
                ordenCompra: $datos['orden_compra'] ?? null,
                observaciones: $datos['observaciones'] ?? null,
            ));
        } catch (SinStock $e) {
            // En JSON responde 409; en la web vuelve al carrito, que marca la línea.
            if ($request->expectsJson()) {
                throw $e;
            }

            return redirect()->route('portal.carrito')->withErrors(['pedido' => $e->getMessage()]);
        }

        if ($perfil->esB2B) {
            return redirect()->route('portal.inicio')->with('estado', sprintf(
                'Recibimos tu pedido N.º %d con la orden de compra %s. Queda pendiente hasta que tu ejecutivo la valide.',
                $pedido->id_pedido,
                trim($datos['orden_compra']),
            ));
        }

        return redirect()->route('portal.pedidos.pago', $pedido->id_pedido)
            ->with('estado', "Pedido N.º {$pedido->id_pedido} creado. Tu stock está reservado mientras pagas.");
    }

    private function perfil(Request $request): PerfilPrecio
    {
        $perfil = $this->precios->perfil($request->user());
        abort_if($perfil->cliente === null, 403);

        return $perfil;
    }
}
