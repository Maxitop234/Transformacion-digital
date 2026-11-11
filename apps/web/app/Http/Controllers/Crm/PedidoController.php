<?php

namespace App\Http\Controllers\Crm;

use App\Domain\Ventas\ConfirmarOrdenCompra;
use App\Domain\Ventas\Montos;
use App\Domain\Ventas\TransicionesPedido;
use App\Http\Controllers\Controller;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pedidos en el CRM (T-016, API.md §1.4). Lee directo de la base, así que
 * un pedido del portal aparece apenas se crea. El vendedor ve solo su
 * cartera (RN-10, `visiblePara`: un pedido ajeno responde 404). Los cambios
 * de estado pasan siempre por `TransicionesPedido`.
 */
class PedidoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::exists('estado_pedido', 'nombre')],
            'canal' => ['nullable', Rule::in(['portal', 'crm'])],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'cliente' => ['nullable', 'string', 'max:100'],
        ]);
        $zona = config('app.timezone');

        $pedidos = Pedido::visiblePara($request->user())
            ->with(['estadoPedido', 'direccionDespacho.cliente.tipoCliente', 'detallesPedido'])
            ->when($filtros['estado'] ?? null, fn (Builder $q, $estado) => $q->whereHas('estadoPedido', fn (Builder $q) => $q->where('nombre', $estado)))
            ->when($filtros['canal'] ?? null, fn (Builder $q, $canal) => $q->where('canal', $canal))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $desde) => $q->where('fecha_pedido', '>=', CarbonImmutable::parse($desde, $zona)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $hasta) => $q->where('fecha_pedido', '<=', CarbonImmutable::parse($hasta, $zona)->endOfDay()))
            ->when(trim($filtros['cliente'] ?? ''), fn (Builder $q, $texto) => $q->whereHas(
                'direccionDespacho.cliente',
                fn (Builder $q) => $q->where('nombre_razon_social', 'ilike', '%'.addcslashes($texto, '%_\\').'%'),
            ))
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id_pedido')
            ->paginate(25)
            ->withQueryString();

        $pedidos->getCollection()->each(fn (Pedido $p) => $p->setAttribute('totales', self::totales($p)));

        return view('crm.pedidos.index', [
            'pedidos' => $pedidos,
            'estados' => EstadoPedido::orderBy('orden')->pluck('nombre'),
        ]);
    }

    public function show(Request $request, int $pedido, TransicionesPedido $transiciones): View
    {
        $pedido = $this->pedidoVisible($request, $pedido);
        $pedido->load([
            'direccionDespacho.comuna',
            'direccionDespacho.cliente.tipoCliente',
            'detallesPedido.producto',
            'historialesEstadoPedido' => fn ($q) => $q->with(['estadoPedido', 'usuario.vendedor'])->orderBy('fecha_hora')->orderBy('id_historial'),
            'pagos' => fn ($q) => $q->orderBy('fecha_hora'),
            'despachosPedido.despacho',
            'tickets',
            'vendedorRegistra',
            'oportunidad',
        ]);

        $origen = $pedido->estadoPedido->nombre;
        // Pendiente → Confirmado tiene su propio camino: pago en línea (B2C) o
        // validación de la orden de compra (B2B, botón aparte).
        $destinos = array_values(array_filter(
            $transiciones->disponibles($pedido, $request->user()),
            fn (string $destino) => ! ($origen === EstadoPedido::PENDIENTE && $destino === EstadoPedido::CONFIRMADO),
        ));

        $ocPendiente = $pedido->pagos->first(fn ($p) => $p->metodo === 'credito_30d' && $p->estado === 'pendiente');

        return view('crm.pedidos.show', [
            'pedido' => $pedido,
            'totales' => self::totales($pedido),
            'destinos' => $destinos,
            'conComentario' => array_values(array_filter($destinos, fn ($d) => TransicionesPedido::exigeComentario($origen, $d))),
            'ocPendiente' => $ocPendiente,
            'puedeConfirmarOc' => $ocPendiente !== null && $origen === EstadoPedido::PENDIENTE && Gate::allows('clientes')
                && in_array(EstadoPedido::CONFIRMADO, $transiciones->disponibles($pedido, $request->user()), true),
        ]);
    }

    public function estado(Request $request, int $pedido, TransicionesPedido $transiciones): RedirectResponse
    {
        $pedido = $this->pedidoVisible($request, $pedido);
        $datos = $request->validate([
            'estado' => ['required', Rule::exists('estado_pedido', 'nombre')],
            'comentario' => ['nullable', 'string', 'max:300'],
        ]);

        if ($pedido->estadoPedido->nombre === EstadoPedido::PENDIENTE && $datos['estado'] === EstadoPedido::CONFIRMADO) {
            return back()->withErrors(['estado' => 'Un pedido pendiente se confirma con el pago en línea o validando su orden de compra.']);
        }

        $transiciones->cambiar($pedido, $datos['estado'], $request->user(), $datos['comentario'] ?? null);

        return redirect()->route('crm.pedidos.show', $pedido->id_pedido)->with('estado', "Pedido N.º {$pedido->id_pedido} pasó a «{$datos['estado']}».");
    }

    public function confirmarOc(Request $request, int $pedido, ConfirmarOrdenCompra $confirmar): RedirectResponse
    {
        abort_unless(Gate::allows('clientes'), 403);
        $pedido = $this->pedidoVisible($request, $pedido);
        $datos = $request->validate(['comentario' => ['nullable', 'string', 'max:200']]);

        $confirmar($pedido, $request->user(), $datos['comentario'] ?? null);

        return redirect()->route('crm.pedidos.show', $pedido->id_pedido)->with('estado', 'Orden de compra validada: el pedido quedó «Confirmado» y el pago aprobado.');
    }

    private function pedidoVisible(Request $request, int $id): Pedido
    {
        return Pedido::visiblePara($request->user())->with('estadoPedido')->findOrFail($id);
    }

    /** @return array{neto: int, iva: int, total: int} */
    private static function totales(Pedido $pedido): array
    {
        return Montos::totales($pedido->detallesPedido->map(fn ($d) => [(string) $d->precio_unitario_pactado, (string) $d->cantidad]));
    }
}
