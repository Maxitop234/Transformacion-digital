<?php

namespace App\Http\Controllers\Crm;

use App\Domain\Logistica\ArmarDespacho;
use App\Domain\Logistica\RegistrarResultadoParada;
use App\Domain\Logistica\RegistrarSalida;
use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\Conductor;
use App\Models\Despacho;
use App\Models\DespachoPedido;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Despachos (T-017, API.md §1.4; gate `despachos`: despachador y
 * administrador). La lógica vive en `App\Domain\Logistica`.
 */
class DespachoController extends Controller
{
    public const ESTADOS = ['programado', 'en_ruta', 'finalizado', 'cancelado'];

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', Rule::in(self::ESTADOS)],
        ]);

        $despachos = Despacho::with(['despachosPedido'])
            ->when($filtros['fecha'] ?? null, fn (Builder $q, $fecha) => $q->whereDate('fecha_programada', $fecha))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('fecha_programada')
            ->orderByDesc('id_despacho')
            ->paginate(20)
            ->withQueryString();

        $ocupados = Despacho::whereIn('estado', ['programado', 'en_ruta']);

        // Pedidos confirmados sin parada pendiente, con el rango de frío de su carga (RN-07).
        $porDespachar = Pedido::whereHas('estadoPedido', fn (Builder $q) => $q->where('nombre', EstadoPedido::CONFIRMADO))
            ->whereDoesntHave('despachosPedido', fn (Builder $q) => $q->where('estado_entrega', 'pendiente'))
            ->with(['direccionDespacho.cliente', 'direccionDespacho.comuna', 'detallesPedido.producto.rangoConservacion'])
            ->orderBy('fecha_entrega_solicitada')
            ->orderBy('id_pedido')
            ->get()
            ->each(fn (Pedido $p) => $p->setAttribute('rangos', $p->detallesPedido->pluck('producto.rangoConservacion.nombre')->unique()->sort()->values()));

        return view('crm.despachos.index', [
            'despachos' => $despachos,
            'estados' => self::ESTADOS,
            'porDespachar' => $porDespachar,
            'camiones' => Camion::where('activo', true)->whereNotIn('camion_id', (clone $ocupados)->select('camion_id'))->orderBy('camion_id')->get(),
            'conductores' => Conductor::where('activo', true)->whereNotIn('id_conductor', (clone $ocupados)->select('id_conductor'))->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request, ArmarDespacho $armar): RedirectResponse
    {
        $datos = $request->validate([
            'camion_id' => ['required', 'string', 'max:20'],
            'id_conductor' => ['required', 'integer'],
            'fecha_programada' => ['required', 'date_format:Y-m-d'],
            'pedidos' => ['required', 'array', 'min:1'],
            'pedidos.*' => ['integer'],
            'orden' => ['nullable', 'array'],
            'orden.*' => ['nullable', 'integer', 'min:1'],
        ], [], ['camion_id' => 'camión', 'id_conductor' => 'conductor', 'fecha_programada' => 'fecha', 'pedidos' => 'pedidos']);

        // Orden de parada: el número indicado y, a igualdad, el número de pedido.
        $orden = $datos['orden'] ?? [];
        $ids = collect($datos['pedidos'])->map(fn ($id) => (int) $id)
            ->sortBy(fn (int $id) => [(int) ($orden[$id] ?? PHP_INT_MAX), $id])
            ->values()
            ->all();

        $despacho = $armar($request->user(), $datos['camion_id'], (int) $datos['id_conductor'], $datos['fecha_programada'], $ids);

        return redirect()->route('crm.despachos.show', $despacho->id_despacho)->with('estado', "Despacho {$despacho->id_despacho} programado con ".count($ids).' paradas.');
    }

    public function show(int $despacho): View
    {
        $despacho = Despacho::with([
            'camion',
            'conductor',
            'despachosPedido' => fn ($q) => $q->orderBy('orden_parada'),
            'despachosPedido.pedido.estadoPedido',
            'despachosPedido.pedido.direccionDespacho.cliente',
            'despachosPedido.pedido.direccionDespacho.comuna',
            'despachosPedido.pedido.detallesPedido.producto.rangoConservacion',
        ])->findOrFail($despacho);

        return view('crm.despachos.show', [
            'despacho' => $despacho,
            'resultados' => RegistrarResultadoParada::RESULTADOS,
            'rango' => $despacho->despachosPedido->flatMap(fn (DespachoPedido $p) => $p->pedido->detallesPedido->pluck('producto.rangoConservacion'))->first(),
        ]);
    }

    public function salida(Request $request, int $despacho, RegistrarSalida $registrar): RedirectResponse
    {
        $despacho = $registrar(Despacho::findOrFail($despacho), $request->user());

        return redirect()->route('crm.despachos.show', $despacho->id_despacho)->with('estado', "Salida registrada: el camión {$despacho->camion_id} va en ruta.");
    }

    public function parada(Request $request, int $despacho, int $pedido, RegistrarResultadoParada $registrar): RedirectResponse
    {
        $datos = $request->validate([
            'resultado' => ['required', Rule::in(RegistrarResultadoParada::RESULTADOS)],
            'motivo' => ['nullable', 'string', 'max:200'],
        ]);

        $registrar(Despacho::findOrFail($despacho), $pedido, $datos['resultado'], $datos['motivo'] ?? null, $request->user());

        return redirect()->route('crm.despachos.show', $despacho)->with('estado', "Parada del pedido N.º {$pedido}: {$datos['resultado']}.");
    }
}
