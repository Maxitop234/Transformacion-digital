<?php

namespace App\Http\Controllers\Crm;

use App\Domain\Crm\GestionarTicket;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bandeja de tickets del CRM (T-018, API.md §1.4, gate `tickets`: vendedor y
 * administrador). El vendedor ve solo los de su cartera (RN-10, ajeno → 404).
 * Los cambios pasan por `GestionarTicket`.
 */
class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'categoria' => ['nullable', Rule::in([Ticket::SIN_CLASIFICAR, ...Ticket::CATEGORIAS])],
            'prioridad' => ['nullable', Rule::in(Ticket::PRIORIDADES)],
            'estado' => ['nullable', Rule::in(['pendientes', ...Ticket::ESTADOS])],
        ]);

        $estado = $filtros['estado'] ?? 'pendientes';

        $tickets = Ticket::visiblePara($request->user())
            ->with(['cliente:id_cliente,nombre_razon_social', 'vendedor:id_vendedor,nombre'])
            ->when($filtros['categoria'] ?? null, fn (Builder $q, $v) => $q->where('categoria', $v))
            ->when($filtros['prioridad'] ?? null, fn (Builder $q, $v) => $q->where('prioridad', $v))
            ->when($estado === 'pendientes', fn (Builder $q) => $q->whereIn('estado', ['abierto', 'en_proceso']))
            ->when($estado !== 'pendientes', fn (Builder $q) => $q->where('estado', $estado))
            ->orderByRaw("CASE prioridad WHEN 'alta' THEN 0 WHEN 'media' THEN 1 ELSE 2 END")
            ->orderByDesc('creado_en')
            ->orderByDesc('id_ticket')
            ->paginate(25)
            ->withQueryString();

        return view('crm.tickets.index', ['tickets' => $tickets, 'estado' => $estado]);
    }

    public function show(Request $request, int $ticket): View
    {
        $ticket = $this->ticketVisible($request, $ticket);
        $ticket->load(['cliente', 'pedido.estadoPedido', 'vendedor']);

        return view('crm.tickets.show', [
            'ticket' => $ticket,
            'vendedores' => $request->user()->esAdministrador()
                ? Vendedor::where('activo', true)->orderBy('nombre')->get(['id_vendedor', 'nombre'])
                : collect(),
        ]);
    }

    public function update(Request $request, int $ticket, GestionarTicket $gestionar): RedirectResponse
    {
        $ticket = $this->ticketVisible($request, $ticket);
        $datos = $request->validate([
            'categoria' => ['nullable', Rule::in(Ticket::CATEGORIAS)],
            'prioridad' => ['nullable', Rule::in(Ticket::PRIORIDADES)],
            'estado' => ['nullable', Rule::in(Ticket::ESTADOS)],
            'id_vendedor' => ['nullable', 'integer'],
        ]);

        $ticket = $gestionar($ticket, [
            ...$datos,
            'id_vendedor' => isset($datos['id_vendedor']) ? (int) $datos['id_vendedor'] : null,
        ], $request->user());

        return redirect()->route('crm.tickets.show', $ticket->id_ticket)->with('estado', "Ticket N.º {$ticket->id_ticket} actualizado.");
    }

    private function ticketVisible(Request $request, int $id): Ticket
    {
        return Ticket::visiblePara($request->user())->findOrFail($id);
    }
}
