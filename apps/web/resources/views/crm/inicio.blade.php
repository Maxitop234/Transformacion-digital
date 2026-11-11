@extends('layouts.crm')

@section('titulo', 'Panel')

@php($clp = fn ($monto) => \App\Domain\Catalogo\Precios::clp($monto))

@section('contenido')
    <h1 class="text-2xl font-semibold">Panel</h1>
    <p class="mt-1 text-sm text-slate-600">
        @if (auth()->user()->tieneRol(\App\Models\Rol::VENDEDOR))
            Indicadores de tu cartera.
        @elseif ($comercial)
            Indicadores de todo el equipo.
        @else
            Lo que requiere tu atención en la operación.
        @endif
    </p>

    @if ($comercial)
        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Indicadores" data-bloque="indicadores">
            <div class="rounded border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">Oportunidades abiertas</p>
                <p class="mt-1 text-2xl font-semibold" data-indicador="oportunidades-abiertas">{{ $indicadores['oportunidades_abiertas'] }}</p>
            </div>
            <div class="rounded border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">Conversión ({{ $indicadores['ventana_dias'] }} días)</p>
                <p class="mt-1 text-2xl font-semibold" data-indicador="conversion">
                    {{ $indicadores['conversion']['tasa'] === null ? '—' : number_format($indicadores['conversion']['tasa'], 1, ',', '.').' %' }}
                </p>
                <p class="text-xs text-slate-500">{{ $indicadores['conversion']['ganadas'] }} ganadas de {{ $indicadores['conversion']['creadas'] }} creadas</p>
            </div>
            <div class="rounded border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">Pedidos por canal ({{ $indicadores['ventana_dias'] }} días)</p>
                <dl class="mt-1 text-sm">
                    @foreach (\App\Domain\Crm\PanelCrm::CANALES as $canal => $etiqueta)
                        <div class="mt-1" data-canal="{{ $canal }}" data-pedidos="{{ $indicadores['pedidos_por_canal'][$canal]['pedidos'] }}">
                            <dt class="text-xs text-slate-500">{{ $etiqueta }}</dt>
                            <dd><strong>{{ $indicadores['pedidos_por_canal'][$canal]['pedidos'] }}</strong> pedidos · {{ $clp($indicadores['pedidos_por_canal'][$canal]['monto_neto']) }} neto</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div class="rounded border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">Tickets abiertos</p>
                <p class="mt-1 text-2xl font-semibold" data-indicador="tickets-abiertos">{{ $indicadores['tickets_abiertos'] }}</p>
            </div>
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded border border-slate-200 bg-white p-4" data-bloque="empresas-pendientes">
                <h2 class="font-medium">Empresas pendientes de validación</h2>
                @forelse ($pendientes as $empresa)
                    @if ($loop->first)<ul class="mt-2 divide-y divide-slate-100 text-sm">@endif
                    <li class="flex justify-between gap-2 py-2">
                        <a href="{{ route('crm.clientes.show', $empresa->id_cliente) }}" class="text-sky-700 hover:underline">{{ $empresa->nombre_razon_social }}</a>
                        <span class="text-slate-500">desde el {{ $empresa->fecha_registro?->setTimezone(config('app.timezone'))->format('d-m-Y') }}</span>
                    </li>
                    @if ($loop->last)</ul>@endif
                @empty
                    <p class="mt-2 text-sm text-slate-500">No hay empresas esperando validación.</p>
                @endforelse
            </section>

            <section class="rounded border border-slate-200 bg-white p-4" data-bloque="oportunidades-sin-actividad">
                <h2 class="font-medium">Oportunidades sin interacciones en {{ $diasSinActividad }} días o más</h2>
                @forelse ($sinActividad as $oportunidad)
                    @if ($loop->first)<ul class="mt-2 divide-y divide-slate-100 text-sm">@endif
                    <li class="py-2" data-oportunidad="{{ $oportunidad->id_oportunidad }}">
                        <p class="flex justify-between gap-2">
                            <span>{{ $oportunidad->titulo }}</span>
                            <span class="whitespace-nowrap text-amber-700">{{ $oportunidad->dias_sin_actividad }} días</span>
                        </p>
                        <p class="text-xs text-slate-500">
                            <a href="{{ route('crm.clientes.show', $oportunidad->id_cliente) }}" class="hover:underline">{{ $oportunidad->cliente->nombre_razon_social }}</a>
                            · {{ $oportunidad->etapaPipeline->nombre }}
                            @unless (auth()->user()->tieneRol(\App\Models\Rol::VENDEDOR))
                                · {{ $oportunidad->vendedor->nombre }}
                            @endunless
                        </p>
                    </li>
                    @if ($loop->last)</ul>@endif
                @empty
                    <p class="mt-2 text-sm text-slate-500">Todas las oportunidades abiertas tienen actividad reciente.</p>
                @endforelse
            </section>
        </div>
    @endif

    @can('alertas')
        <section class="mt-6 rounded border border-slate-200 bg-white p-4" data-bloque="alertas">
            <h2 class="font-medium">Alertas de frío abiertas con pedidos afectados{{ auth()->user()->tieneRol(\App\Models\Rol::VENDEDOR) ? ' de tu cartera' : '' }}</h2>
            @isset($alertasAbiertasTotal)
                <p class="text-xs text-slate-500" data-alertas-total="{{ $alertasAbiertasTotal }}">{{ $alertasAbiertasTotal }} {{ $alertasAbiertasTotal === 1 ? 'alerta abierta' : 'alertas abiertas' }} en toda la flota, con o sin carga.</p>
            @endisset
            @forelse ($alertas as $item)
                <article class="mt-3 rounded border border-red-200 bg-red-50 p-3 text-sm" data-alerta="{{ $item['alerta']->id_alerta }}">
                    <p class="font-medium text-red-900">
                        {{ $item['camion_id'] }} · {{ number_format($item['temp_camara'], 1, ',', '.') }} °C
                        · severidad {{ $item['alerta']->severidad }} · {{ $item['alerta']->estado }}
                    </p>
                    <p class="text-xs text-red-800">Lectura del {{ $item['fecha_hora']->setTimezone(config('app.timezone'))->format('d-m-Y H:i') }}</p>
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($item['afectados'] as $afectado)
                            <li data-cliente-afectado="{{ $afectado->id_cliente }}">
                                Parada {{ $afectado->orden_parada }}: pedido N.º {{ $afectado->id_pedido }} ·
                                @can('clientes')
                                    <a href="{{ route('crm.clientes.show', $afectado->id_cliente) }}" class="text-sky-700 hover:underline">{{ $afectado->nombre_razon_social }}</a>
                                @else
                                    {{ $afectado->nombre_razon_social }}
                                @endcan
                                <span class="text-slate-500">({{ str_replace('_', ' ', $afectado->estado_entrega) }})</span>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @empty
                <p class="mt-2 text-sm text-slate-500">Ninguna alerta abierta afecta pedidos{{ auth()->user()->tieneRol(\App\Models\Rol::VENDEDOR) ? ' de tu cartera' : '' }}.</p>
            @endforelse
            <a href="{{ route('crm.alertas') }}" class="mt-3 inline-block text-sm text-sky-700 hover:underline">Ver todas las alertas</a>
        </section>
    @endcan
@endsection
