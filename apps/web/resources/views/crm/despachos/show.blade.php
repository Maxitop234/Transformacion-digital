@extends('layouts.crm')

@section('titulo', 'Despacho '.$despacho->id_despacho)

@php($zona = config('app.timezone'))

@section('contenido')
    <a href="{{ route('crm.despachos') }}" class="text-sm text-sky-700 hover:underline">&larr; Despachos</a>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Despacho N.º {{ $despacho->id_despacho }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $despacho->camion_id }} ({{ $despacho->camion->patente }}) · {{ $despacho->conductor->nombre }} · {{ $despacho->fecha_programada->format('d-m-Y') }}
                @if ($rango) · {{ $rango->nombre }} ({{ (float) $rango->temp_min_c }} a {{ (float) $rango->temp_max_c }} °C) @endif
            </p>
        </div>
        <x-insignia :valor="$despacho->estado" class="text-sm" data-estado-despacho="{{ $despacho->estado }}" />
    </div>

    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    <p class="text-sm text-slate-600">
        Salida: {{ $despacho->salida_real?->setTimezone($zona)->format('d-m-Y H:i') ?? '—' }}
        · Llegada: {{ $despacho->llegada_real?->setTimezone($zona)->format('d-m-Y H:i') ?? '—' }}
    </p>

    @if ($despacho->estado === 'programado')
        <form method="POST" action="{{ route('crm.despachos.salida', $despacho->id_despacho) }}" class="mt-3">
            @csrf
            <button class="rounded bg-sky-700 px-3 py-1.5 text-sm text-white hover:bg-sky-800" data-registrar-salida>Registrar salida de bodega</button>
        </form>
    @endif

    <table class="mt-4 w-full border-collapse text-sm">
        <thead><tr class="border-b border-slate-300 text-left"><th class="py-2 pr-3">Parada</th><th class="py-2 pr-3">Pedido</th><th class="py-2 pr-3">Cliente y punto</th><th class="py-2 pr-3">Estado</th><th class="py-2">Resultado</th></tr></thead>
        <tbody>
            @foreach ($despacho->despachosPedido as $parada)
                @php($pedido = $parada->pedido)
                <tr class="border-b border-slate-200 align-top" data-parada="{{ $pedido->id_pedido }}">
                    <td class="py-2 pr-3">{{ $parada->orden_parada }}</td>
                    <td class="py-2 pr-3"><a href="{{ route('crm.pedidos.show', $pedido->id_pedido) }}" class="text-sky-700 hover:underline">{{ $pedido->id_pedido }}</a> <span class="text-xs text-slate-500">{{ $pedido->estadoPedido->nombre }}</span></td>
                    <td class="py-2 pr-3">{{ $pedido->direccionDespacho->cliente->nombre_razon_social }}<span class="block text-xs text-slate-500">{{ $pedido->direccionDespacho->alias }} · {{ $pedido->direccionDespacho->calle }}, {{ $pedido->direccionDespacho->comuna->nombre }}</span></td>
                    <td class="py-2 pr-3">{{ str_replace('_', ' ', $parada->estado_entrega) }}@if ($parada->motivo_falla)<span class="block text-xs text-slate-500">{{ $parada->motivo_falla }}</span>@endif</td>
                    <td class="py-2">
                        @if ($despacho->estado === 'en_ruta' && $parada->estado_entrega === 'pendiente')
                            <form method="POST" action="{{ route('crm.despachos.parada', [$despacho->id_despacho, $pedido->id_pedido]) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <select name="resultado" class="rounded border border-slate-300 px-2 py-1" aria-label="Resultado de la parada {{ $parada->orden_parada }}">
                                    <option value="entregado">Entregado</option>
                                    <option value="rechazado">Rechazado por el cliente</option>
                                    <option value="fallido">Entrega fallida</option>
                                </select>
                                <input name="motivo" maxlength="200" placeholder="Motivo (obligatorio si no se entregó)" class="w-56 rounded border border-slate-300 px-2 py-1" aria-label="Motivo de la parada {{ $parada->orden_parada }}">
                                <button class="rounded bg-sky-700 px-2 py-1 text-white hover:bg-sky-800">Registrar</button>
                            </form>
                        @else
                            {{ $parada->fecha_entrega?->setTimezone($zona)->format('d-m-Y H:i') ?? '—' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
