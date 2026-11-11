@extends('layouts.crm')

@section('titulo', 'Alertas de frío')

@php($zona = config('app.timezone'))
@php($esVendedor = auth()->user()->tieneRol(\App\Models\Rol::VENDEDOR))

@section('contenido')
    <h1 class="text-2xl font-semibold">Alertas de frío</h1>
    <p class="mt-1 text-sm text-slate-600">{{ $esVendedor ? 'Alertas que afectan a clientes de tu cartera.' : 'Rupturas de frío y combustible de toda la flota.' }}</p>

    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    <nav class="flex gap-3 text-sm" aria-label="Filtro de alertas">
        @foreach (['abiertas' => 'Abiertas', 'resueltas' => 'Resueltas', 'todas' => 'Todas'] as $valor => $etiqueta)
            <a href="{{ route('crm.alertas', ['estado' => $valor]) }}" @class(['rounded px-2 py-1', 'bg-slate-200 font-medium' => $estado === $valor, 'text-sky-700 hover:underline' => $estado !== $valor])>{{ $etiqueta }}</a>
        @endforeach
    </nav>

    <div class="mt-4 space-y-3">
        @forelse ($alertas as $alerta)
            @php($filas = $afectados->get($alerta->id_alerta, collect()))
            <article @class(['rounded border p-4 text-sm', 'border-red-200 bg-red-50' => $alerta->estado !== 'resuelta', 'border-slate-200 bg-white' => $alerta->estado === 'resuelta']) data-alerta="{{ $alerta->id_alerta }}">
                <div class="flex flex-wrap justify-between gap-2">
                    <p class="font-medium">
                        Alerta {{ $alerta->id_alerta }} · {{ $alerta->telemetria->camion_id }} · {{ str_replace('_', ' ', $alerta->tipo) }}
                        · {{ number_format((float) $alerta->telemetria->temp_camara, 1, ',', '.') }} °C · severidad {{ $alerta->severidad }}
                    </p>
                    <p class="text-xs text-slate-600">{{ $alerta->telemetria->fecha_hora->setTimezone($zona)->format('d-m-Y H:i') }} · {{ $alerta->estado }}</p>
                </div>

                @if ($filas->isEmpty())
                    <p class="mt-1 text-slate-600">Sin pedidos afectados (el camión no llevaba carga en ruta).</p>
                @else
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($filas as $fila)
                            <li data-cliente-afectado="{{ $fila->id_cliente }}">
                                Parada {{ $fila->orden_parada }}: <a href="{{ route('crm.pedidos.show', $fila->id_pedido) }}" class="text-sky-700 hover:underline">pedido N.º {{ $fila->id_pedido }}</a> · {{ $fila->nombre_razon_social }}
                                <span class="text-slate-500">({{ str_replace('_', ' ', $fila->estado_entrega) }})</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($alerta->estado === 'resuelta')
                    <p class="mt-2 text-xs text-slate-600">
                        Resuelta el {{ $alerta->fecha_resolucion?->setTimezone($zona)->format('d-m-Y H:i') }} por {{ $alerta->usuarioResuelve?->email ?? '—' }}: {{ $alerta->accion_correctiva }}
                    </p>
                @elseif ($puedeResolver)
                    <form method="POST" action="{{ route('crm.alertas.resolver', $alerta->id_alerta) }}" class="mt-3 flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="estado" value="{{ $estado }}">
                        <input name="accion_correctiva" required maxlength="300" placeholder="Acción correctiva (obligatoria)" class="min-w-72 flex-1 rounded border border-slate-300 bg-white px-2 py-1" aria-label="Acción correctiva de la alerta {{ $alerta->id_alerta }}">
                        <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Resolver</button>
                    </form>
                @endif
            </article>
        @empty
            <p class="text-slate-500" data-sin-alertas>No hay alertas {{ $estado === 'todas' ? '' : $estado }}.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $alertas->links() }}</div>
@endsection
