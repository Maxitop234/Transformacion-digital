<?php

namespace App\Http\Controllers\Crm;

use App\Domain\Catalogo\Precios;
use App\Domain\Ventas\CalendarioEntregas;
use App\Domain\Ventas\CrearPedido;
use App\Domain\Ventas\DatosPedido;
use App\Domain\Ventas\SinStock;
use App\Http\Controllers\Controller;
use App\Models\DireccionDespacho;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pedido telefónico (RN-08, API.md §1.4 `POST /crm/clientes/{id}/pedidos`):
 * el vendedor compra a nombre del cliente con `CrearPedido::telefonico()`,
 * es decir, con los mismos precios de lista, mínimo y reserva de stock que
 * el portal.
 */
class PedidoTelefonicoController extends Controller
{
    /** Filas de producto del formulario. */
    public const FILAS = 8;

    public function create(Request $request, int $cliente, Precios $precios, CalendarioEntregas $calendario): View
    {
        $cliente = ClienteController::visibleOFalla($request, $cliente);
        $perfil = $precios->perfilDeCliente($cliente);

        $productos = Producto::where('activo', true)->with('inventario')->orderBy('nombre')->get();
        $preciosNetos = $precios->precios($productos->pluck('id_producto'), $perfil->lista);

        return view('crm.clientes.pedido', [
            'cliente' => $cliente,
            'perfil' => $perfil,
            'productos' => $productos->filter(fn (Producto $p) => $preciosNetos->has($p->id_producto))->values(),
            'preciosNetos' => $preciosNetos,
            'direcciones' => DireccionDespacho::where('id_cliente', $cliente->id_cliente)->where('activa', true)->with('comuna')->orderBy('alias')->get(),
            'primeraFecha' => $calendario->primeraFecha(),
            'minimo' => CrearPedido::minimo($perfil),
            'filas' => self::FILAS,
        ]);
    }

    public function store(Request $request, int $cliente, Precios $precios, CrearPedido $crear): RedirectResponse
    {
        $cliente = ClienteController::visibleOFalla($request, $cliente);

        $datos = $request->validate([
            'lineas' => ['required', 'array', 'max:'.self::FILAS],
            'lineas.*.id_producto' => ['nullable', 'integer'],
            'lineas.*.cantidad' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'id_direccion' => ['required', 'integer'],
            'fecha_entrega_solicitada' => ['required', 'date_format:Y-m-d'],
            'orden_compra' => ['nullable', 'string', 'max:80'],
            'observaciones' => ['nullable', 'string', 'max:300'],
        ], [], [
            'id_direccion' => 'punto de entrega',
            'fecha_entrega_solicitada' => 'fecha de entrega',
            'orden_compra' => 'orden de compra',
        ]);

        // Filas completas; un producto repetido suma sus cantidades.
        $cantidades = [];
        foreach ($datos['lineas'] as $linea) {
            if (! empty($linea['id_producto']) && ! empty($linea['cantidad'])) {
                $id = (int) $linea['id_producto'];
                $cantidades[$id] = (string) (((float) ($cantidades[$id] ?? 0)) + (float) $linea['cantidad']);
            }
        }

        try {
            $pedido = $crear->telefonico($precios->perfilDeCliente($cliente), $request->user(), new DatosPedido(
                idDireccion: (int) $datos['id_direccion'],
                fechaEntrega: $datos['fecha_entrega_solicitada'],
                ordenCompra: $datos['orden_compra'] ?? null,
                observaciones: $datos['observaciones'] ?? null,
            ), $cantidades);
        } catch (SinStock $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()->withInput()->withErrors(['pedido' => $e->getMessage()]);
        }

        return redirect()->route('crm.clientes.show', $cliente->id_cliente)
            ->with('estado', "Pedido telefónico N.º {$pedido->id_pedido} creado en estado Pendiente.");
    }
}
