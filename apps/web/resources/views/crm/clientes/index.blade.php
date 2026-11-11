@extends('layouts.crm')

@section('titulo', 'Clientes')

@section('contenido')
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Clientes</h1>
            <p class="mt-1 text-sm text-slate-600">{{ auth()->user()->esAdministrador() ? 'Todas las carteras.' : 'Tu cartera.' }}</p>
        </div>
        <a href="{{ route('crm.clientes.create') }}" class="rounded bg-sky-700 px-3 py-1.5 text-sm text-white hover:bg-sky-800">Nuevo prospecto</a>
    </div>

    <form method="GET" action="{{ route('crm.clientes.index') }}" class="mt-4 flex flex-wrap items-end gap-3 text-sm" aria-label="Filtros de la cartera">
        <label class="grid gap-1">Buscar
            <input name="q" value="{{ request('q') }}" placeholder="Nombre o RUT" maxlength="100" class="w-56 rounded border border-slate-300 px-2 py-1">
        </label>
        <label class="grid gap-1">Tipo
            <select name="tipo" class="rounded border border-slate-300 px-2 py-1">
                <option value="">Todos</option>
                @foreach (['B2B', 'B2C'] as $tipo)
                    <option value="{{ $tipo }}" @selected(request('tipo') === $tipo)>{{ $tipo }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1">Estado
            <select name="estado" class="rounded border border-slate-300 px-2 py-1">
                <option value="">Todos</option>
                @foreach ($estados as $estado)
                    <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ str_replace('_', ' ', $estado) }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Filtrar</button>
        @if (request()->hasAny(['q', 'tipo', 'estado']))
            <a href="{{ route('crm.clientes.index') }}" class="pb-1 text-sky-700 hover:underline">Limpiar filtros</a>
        @endif
    </form>

    <table class="mt-4 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-4">Nombre o razón social</th>
                <th class="py-2 pr-4">RUT</th>
                <th class="py-2 pr-4">Tipo</th>
                <th class="py-2 pr-4">Estado</th>
                <th class="py-2 pr-4">Vendedor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clientes as $cliente)
                <tr class="border-b border-slate-200" data-cliente="{{ $cliente->id_cliente }}">
                    <td class="py-2 pr-4"><a class="text-sky-700 hover:underline" href="{{ route('crm.clientes.show', $cliente->id_cliente) }}">{{ $cliente->nombre_razon_social }}</a></td>
                    <td class="py-2 pr-4 whitespace-nowrap">{{ $cliente->rut ? \App\Domain\Clientes\Rut::desdeTexto($cliente->rut)->formateado() : '—' }}</td>
                    <td class="py-2 pr-4">{{ $cliente->tipoCliente->codigo }}</td>
                    <td class="py-2 pr-4">{{ str_replace('_', ' ', $cliente->estado_cliente) }}</td>
                    <td class="py-2 pr-4">{{ $cliente->vendedor?->nombre ?? 'Sin asignar' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-slate-500">No hay clientes que coincidan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $clientes->links() }}</div>
@endsection
