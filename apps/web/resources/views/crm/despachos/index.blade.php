@extends('layouts.crm')

@section('titulo', 'Despachos')

@section('contenido')
    <h1 class="text-2xl font-semibold">Despachos</h1>
    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="nuevo-despacho">
        <h2 class="font-medium">Nuevo despacho</h2>
        <p class="mt-1 text-slate-600">RN-07: el camión tiene una sola cámara; todos los productos del despacho deben compartir el rango de conservación. Indica el orden de parada (1, 2, 3…).</p>
        @if ($porDespachar->isEmpty())
            <p class="mt-3 text-slate-500" data-sin-pedidos>No hay pedidos confirmados esperando despacho.</p>
        @else
            <form method="POST" action="{{ route('crm.despachos.store') }}" class="mt-3 grid gap-3">
                @csrf
                <div class="flex flex-wrap gap-3">
                    <label class="grid gap-1">Camión
                        <select name="camion_id" required class="rounded border border-slate-300 px-2 py-1">
                            @foreach ($camiones as $camion)
                                <option value="{{ $camion->camion_id }}" @selected(old('camion_id') === $camion->camion_id)>{{ $camion->camion_id }} · {{ $camion->patente }} · {{ number_format($camion->capacidad_kg, 0, ',', '.') }} kg</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1">Conductor
                        <select name="id_conductor" required class="rounded border border-slate-300 px-2 py-1">
                            @foreach ($conductores as $conductor)
                                <option value="{{ $conductor->id_conductor }}" @selected(old('id_conductor') == $conductor->id_conductor)>{{ $conductor->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1">Fecha
                        <input type="date" name="fecha_programada" required min="{{ now(config('app.timezone'))->toDateString() }}" value="{{ old('fecha_programada', now(config('app.timezone'))->addDay()->toDateString()) }}" class="rounded border border-slate-300 px-2 py-1">
                    </label>
                </div>
                <table class="w-full">
                    <thead class="text-left text-xs text-slate-500">
                        <tr><th class="py-1">Incluir</th><th>Parada</th><th>Pedido</th><th>Cliente y comuna</th><th>Entrega</th><th>Rango de frío</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($porDespachar as $pedido)
                            <tr class="border-t border-slate-100" data-por-despachar="{{ $pedido->id_pedido }}">
                                <td class="py-1"><input type="checkbox" name="pedidos[]" value="{{ $pedido->id_pedido }}" aria-label="Incluir pedido {{ $pedido->id_pedido }}" @checked(in_array($pedido->id_pedido, old('pedidos', [])))></td>
                                <td><input type="number" name="orden[{{ $pedido->id_pedido }}]" min="1" value="{{ old("orden.{$pedido->id_pedido}") }}" class="w-16 rounded border border-slate-300 px-1 py-0.5" aria-label="Parada del pedido {{ $pedido->id_pedido }}"></td>
                                <td><a href="{{ route('crm.pedidos.show', $pedido->id_pedido) }}" class="text-sky-700 hover:underline">{{ $pedido->id_pedido }}</a></td>
                                <td>{{ $pedido->direccionDespacho->cliente->nombre_razon_social }} · {{ $pedido->direccionDespacho->comuna->nombre }}</td>
                                <td>{{ $pedido->fecha_entrega_solicitada?->format('d-m-Y') ?? '—' }}</td>
                                <td @class(['text-red-700' => $pedido->rangos->count() > 1])>{{ $pedido->rangos->implode(' + ') }}{{ $pedido->rangos->count() > 1 ? ' (mezcla rangos)' : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div><button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Programar despacho</button></div>
            </form>
        @endif
    </section>

    <form method="GET" action="{{ route('crm.despachos') }}" class="mt-6 flex flex-wrap items-end gap-3 text-sm" aria-label="Filtros de despachos">
        <label class="grid gap-1">Fecha<input type="date" name="fecha" value="{{ request('fecha') }}" class="rounded border border-slate-300 px-2 py-1"></label>
        <label class="grid gap-1">Estado
            <select name="estado" class="rounded border border-slate-300 px-2 py-1">
                <option value="">Todos</option>
                @foreach ($estados as $estado)
                    <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ str_replace('_', ' ', $estado) }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Filtrar</button>
    </form>

    <table class="mt-3 w-full border-collapse text-sm">
        <thead><tr class="border-b border-slate-300 text-left"><th class="py-2 pr-4">N.º</th><th class="py-2 pr-4">Fecha</th><th class="py-2 pr-4">Camión</th><th class="py-2 pr-4">Paradas</th><th class="py-2 pr-4">Estado</th></tr></thead>
        <tbody>
            @forelse ($despachos as $despacho)
                <tr class="border-b border-slate-200" data-despacho="{{ $despacho->id_despacho }}">
                    <td class="py-2 pr-4"><a href="{{ route('crm.despachos.show', $despacho->id_despacho) }}" class="text-sky-700 hover:underline">{{ $despacho->id_despacho }}</a></td>
                    <td class="py-2 pr-4">{{ $despacho->fecha_programada->format('d-m-Y') }}</td>
                    <td class="py-2 pr-4">{{ $despacho->camion_id }}</td>
                    <td class="py-2 pr-4">{{ $despacho->despachosPedido->count() }} ({{ $despacho->despachosPedido->where('estado_entrega', 'pendiente')->count() }} pendientes)</td>
                    <td class="py-2 pr-4"><x-insignia :valor="$despacho->estado" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-slate-500">No hay despachos.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $despachos->links() }}</div>
@endsection
