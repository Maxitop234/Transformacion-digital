@extends('layouts.crm')

@section('titulo', 'Pedidos')

@php($zona = config('app.timezone'))

@section('contenido')
    <h1 class="text-2xl font-semibold">Pedidos</h1>
    <p class="mt-1 text-sm text-slate-600">{{ auth()->user()->tieneRol(\App\Models\Rol::VENDEDOR) ? 'Pedidos de tu cartera, del portal y del CRM.' : 'Pedidos del portal y del CRM.' }}</p>

    <form method="GET" action="{{ route('crm.pedidos') }}" class="mt-4 flex flex-wrap items-end gap-3 text-sm" aria-label="Filtros de pedidos">
        <label class="grid gap-1">Estado
            <select name="estado" class="rounded border border-slate-300 px-2 py-1">
                <option value="">Todos</option>
                @foreach ($estados as $estado)
                    <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $estado }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1">Canal
            <select name="canal" class="rounded border border-slate-300 px-2 py-1">
                <option value="">Todos</option>
                <option value="portal" @selected(request('canal') === 'portal')>Portal</option>
                <option value="crm" @selected(request('canal') === 'crm')>CRM (teléfono)</option>
            </select>
        </label>
        <label class="grid gap-1">Desde<input type="date" name="desde" value="{{ request('desde') }}" class="rounded border border-slate-300 px-2 py-1"></label>
        <label class="grid gap-1">Hasta<input type="date" name="hasta" value="{{ request('hasta') }}" class="rounded border border-slate-300 px-2 py-1"></label>
        <label class="grid gap-1">Cliente<input name="cliente" value="{{ request('cliente') }}" maxlength="100" placeholder="Nombre o razón social" class="w-52 rounded border border-slate-300 px-2 py-1"></label>
        <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Filtrar</button>
        @if (request()->hasAny(['estado', 'canal', 'desde', 'hasta', 'cliente']))
            <a href="{{ route('crm.pedidos') }}" class="pb-1 text-sky-700 hover:underline">Limpiar filtros</a>
        @endif
    </form>

    <table class="mt-4 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-4">N.º</th>
                <th class="py-2 pr-4">Fecha</th>
                <th class="py-2 pr-4">Cliente</th>
                <th class="py-2 pr-4">Canal</th>
                <th class="py-2 pr-4">Entrega</th>
                <th class="py-2 pr-4">Estado</th>
                <th class="py-2 text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pedidos as $pedido)
                <tr class="border-b border-slate-200" data-pedido="{{ $pedido->id_pedido }}">
                    <td class="py-2 pr-4"><a href="{{ route('crm.pedidos.show', $pedido->id_pedido) }}" class="text-sky-700 hover:underline">{{ $pedido->id_pedido }}</a></td>
                    <td class="py-2 pr-4 whitespace-nowrap">{{ $pedido->fecha_pedido->setTimezone($zona)->format('d-m-Y H:i') }}</td>
                    <td class="py-2 pr-4">{{ $pedido->direccionDespacho->cliente->nombre_razon_social }} <span class="text-xs text-slate-500">{{ $pedido->direccionDespacho->cliente->tipoCliente->codigo }}</span></td>
                    <td class="py-2 pr-4">{{ $pedido->canal === 'crm' ? 'CRM' : 'Portal' }}</td>
                    <td class="py-2 pr-4 whitespace-nowrap">{{ $pedido->fecha_entrega_solicitada?->format('d-m-Y') ?? '—' }}</td>
                    <td class="py-2 pr-4"><x-insignia :valor="$pedido->estadoPedido->nombre" /></td>
                    <td class="py-2 text-right whitespace-nowrap">{{ \App\Domain\Catalogo\Precios::clp($pedido->totales['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-4 text-slate-500">No hay pedidos que coincidan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $pedidos->links() }}</div>
@endsection
