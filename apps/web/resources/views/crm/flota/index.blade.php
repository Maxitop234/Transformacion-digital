@extends('layouts.crm')

@section('titulo', 'Flota')

@php($zona = config('app.timezone'))

@section('contenido')
    <h1 class="text-2xl font-semibold">Flota</h1>
    <p class="mt-1 text-sm text-slate-600">Última lectura de cada camión (el simulador IoT escribe cada 30 segundos). Actualiza la página para ver la siguiente.</p>

    <table class="mt-4 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-3">Camión</th><th class="py-2 pr-3">Última lectura</th><th class="py-2 pr-3 text-right">Temp. cámara</th>
                <th class="py-2 pr-3 text-right">Combustible</th><th class="py-2 pr-3">Posición</th><th class="py-2 pr-3">Despacho</th><th class="py-2">Frío</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($camiones as $camion)
                <tr @class(['border-b border-slate-200', 'bg-red-50' => $camion->alerta_abierta, 'text-slate-400' => ! $camion->activo]) data-camion="{{ $camion->camion_id }}">
                    <td class="py-2 pr-3">{{ $camion->camion_id }} <span class="text-xs text-slate-500">{{ $camion->patente }}</span></td>
                    <td class="py-2 pr-3 whitespace-nowrap">{{ $camion->fecha_hora ? \Carbon\CarbonImmutable::parse($camion->fecha_hora)->setTimezone($zona)->format('d-m-Y H:i:s') : 'Sin lecturas' }}</td>
                    <td class="py-2 pr-3 text-right">{{ $camion->temp_camara !== null ? number_format((float) $camion->temp_camara, 1, ',', '.').' °C' : '—' }}</td>
                    <td class="py-2 pr-3 text-right">{{ $camion->combustible_pct !== null ? $camion->combustible_pct.' %' : '—' }}</td>
                    <td class="py-2 pr-3 text-xs">{{ $camion->lat !== null ? number_format((float) $camion->lat, 4).', '.number_format((float) $camion->long, 4) : '—' }}</td>
                    <td class="py-2 pr-3">@if ($camion->id_despacho)<a href="{{ route('crm.despachos.show', $camion->id_despacho) }}" class="text-sky-700 hover:underline">N.º {{ $camion->id_despacho }} en ruta</a>@else — @endif</td>
                    <td class="py-2">
                        @if ($camion->alerta_abierta)
                            <a href="{{ route('crm.alertas') }}" class="font-medium text-red-700 hover:underline" data-alerta-abierta>Alerta abierta</a>
                        @elseif ($camion->alerta_ruptura_frio)
                            <span class="text-amber-700">Fuera de rango</span>
                        @else
                            <span class="text-emerald-700">OK</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
