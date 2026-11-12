@extends('layouts.crm')

@section('titulo', 'Tickets')

@php($zona = config('app.timezone'))
@php($etiqueta = fn (string $valor) => str_replace('_', ' ', $valor))

@section('contenido')
    <h1 class="text-2xl font-semibold">Tickets</h1>
    <p class="mt-1 text-sm text-slate-600">{{ auth()->user()->esAdministrador() ? 'Requerimientos y reclamos de todos los clientes.' : 'Requerimientos y reclamos de tu cartera.' }}</p>

    <form method="GET" action="{{ route('crm.tickets') }}" class="mt-4 flex flex-wrap items-end gap-3 text-sm" aria-label="Filtros de tickets">
        <label class="grid gap-1">Categoría
            <select name="categoria" class="rounded border border-slate-300 px-2 py-1">
                <option value="">Todas</option>
                @foreach ([\App\Models\Ticket::SIN_CLASIFICAR, ...\App\Models\Ticket::CATEGORIAS] as $valor)
                    <option value="{{ $valor }}" @selected(request('categoria') === $valor)>{{ $etiqueta($valor) }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1">Prioridad
            <select name="prioridad" class="rounded border border-slate-300 px-2 py-1">
                <option value="">Todas</option>
                @foreach (\App\Models\Ticket::PRIORIDADES as $valor)
                    <option value="{{ $valor }}" @selected(request('prioridad') === $valor)>{{ $valor }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1">Estado
            <select name="estado" class="rounded border border-slate-300 px-2 py-1">
                <option value="pendientes" @selected($estado === 'pendientes')>Pendientes (abierto o en proceso)</option>
                @foreach (\App\Models\Ticket::ESTADOS as $valor)
                    <option value="{{ $valor }}" @selected($estado === $valor)>{{ $etiqueta($valor) }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Filtrar</button>
        @if (request()->hasAny(['categoria', 'prioridad', 'estado']))
            <a href="{{ route('crm.tickets') }}" class="pb-1 text-sky-700 hover:underline">Limpiar filtros</a>
        @endif
    </form>

    <table class="mt-4 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-4">N.º</th>
                <th class="py-2 pr-4">Asunto</th>
                <th class="py-2 pr-4">Cliente</th>
                <th class="py-2 pr-4">Categoría</th>
                <th class="py-2 pr-4">Prioridad</th>
                <th class="py-2 pr-4">Estado</th>
                <th class="py-2 pr-4">Asignado a</th>
                <th class="py-2 pr-4">Creado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tickets as $ticket)
                <tr class="border-b border-slate-200" data-ticket="{{ $ticket->id_ticket }}">
                    <td class="py-2 pr-4"><a href="{{ route('crm.tickets.show', $ticket->id_ticket) }}" class="text-sky-700 hover:underline">{{ $ticket->id_ticket }}</a></td>
                    <td class="py-2 pr-4">{{ $ticket->asunto }}@if ($ticket->id_pedido) <span class="text-xs text-slate-500">· pedido {{ $ticket->id_pedido }}</span>@endif</td>
                    <td class="py-2 pr-4">{{ $ticket->cliente->nombre_razon_social }}</td>
                    <td class="py-2 pr-4">{{ $etiqueta($ticket->categoria) }}@if ($ticket->clasificado_por_ia) <span class="text-xs text-slate-500">(IA)</span>@endif</td>
                    <td class="py-2 pr-4"><x-insignia :valor="$ticket->prioridad" /></td>
                    <td class="py-2 pr-4"><x-insignia :valor="$ticket->estado" /></td>
                    <td class="py-2 pr-4">{{ $ticket->vendedor?->nombre ?? 'Sin asignar' }}</td>
                    <td class="py-2 pr-4 whitespace-nowrap">{{ $ticket->creado_en->setTimezone($zona)->format('d-m-Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-4 text-slate-500">No hay tickets que coincidan.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $tickets->links() }}</div>
@endsection
