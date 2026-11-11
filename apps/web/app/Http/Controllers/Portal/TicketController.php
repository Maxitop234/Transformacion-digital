<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Crm\CrearTicket;
use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «Reportar un problema» desde el portal (T-018, API.md §1.3). El cliente
 * ve y crea solo sus tickets; el pedido opcional se resuelve con
 * `visiblePara()`, así que uno ajeno responde 404.
 */
class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();

        return view('portal.tickets.index', [
            'tickets' => Ticket::visiblePara($usuario)->orderByDesc('creado_en')->orderByDesc('id_ticket')->paginate(20),
            'pedidos' => Pedido::visiblePara($usuario)->orderByDesc('fecha_pedido')->limit(30)->pluck('id_pedido'),
            'idPedido' => $request->integer('pedido') ?: null,
        ]);
    }

    public function store(Request $request, CrearTicket $crear): RedirectResponse
    {
        $datos = $request->validate([
            'asunto' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'id_pedido' => ['nullable', 'integer'],
        ], [], ['descripcion' => 'descripción', 'id_pedido' => 'pedido']);

        $cliente = $request->user()->cliente;
        abort_if($cliente === null, 403);

        $pedido = isset($datos['id_pedido'])
            ? Pedido::visiblePara($request->user())->findOrFail($datos['id_pedido'])
            : null;

        $ticket = $crear($cliente, $datos['asunto'], $datos['descripcion'], $pedido);

        $destino = $pedido
            ? redirect()->route('portal.mis-pedidos.show', $pedido->id_pedido)
            : redirect()->route('portal.tickets');

        return $destino->with('estado', "Recibimos tu reporte N.º {$ticket->id_ticket}. Un ejecutivo lo revisará.");
    }
}
