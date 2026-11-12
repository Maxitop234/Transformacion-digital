@extends('layouts.crm')

@section('titulo', 'Oportunidad '.$oportunidad->id_oportunidad)

@php($zona = config('app.timezone'))
@php($clp = fn ($monto) => \App\Domain\Catalogo\Precios::clp($monto))
@php($cerrada = $oportunidad->etapaPipeline->es_cierre)
@php($G = \App\Domain\Crm\TransicionesOportunidad::GANADO)
@php($P = \App\Domain\Crm\TransicionesOportunidad::PERDIDO)

@section('contenido')
    <a href="{{ route('crm.oportunidades') }}" class="text-sm text-sky-700 hover:underline">&larr; Pipeline</a>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $oportunidad->titulo }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                <a href="{{ route('crm.clientes.show', $oportunidad->id_cliente) }}" class="text-sky-700 hover:underline">{{ $oportunidad->cliente->nombre_razon_social }}</a>
                ({{ $oportunidad->cliente->tipoCliente->codigo }}, {{ str_replace('_', ' ', $oportunidad->cliente->estado_cliente) }})
                · origen {{ $oportunidad->origen }} · {{ $oportunidad->vendedor?->nombre }}
            </p>
        </div>
        <x-insignia :valor="$oportunidad->etapaPipeline->nombre" class="text-sm" data-etapa-actual />
    </div>

    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="datos">
                <h2 class="font-medium">Datos</h2>
                @if ($cerrada)
                    <dl class="mt-2 grid grid-cols-2 gap-y-1">
                        <dt class="text-slate-500">Monto estimado</dt><dd>{{ $oportunidad->monto_estimado ? $clp($oportunidad->monto_estimado) : '—' }}</dd>
                        <dt class="text-slate-500">Cerrada el</dt><dd>{{ $oportunidad->fecha_cierre?->setTimezone($zona)->format('d-m-Y H:i') }}</dd>
                        @if ($oportunidad->motivo_perdida)
                            <dt class="text-slate-500">Motivo de pérdida</dt><dd>{{ $oportunidad->motivo_perdida }}</dd>
                        @endif
                    </dl>
                @else
                    <form method="POST" action="{{ route('crm.oportunidades.update', $oportunidad->id_oportunidad) }}" class="mt-2 grid gap-3 sm:grid-cols-3">
                        @csrf
                        @method('PATCH')
                        <label class="grid gap-1 sm:col-span-3">Título
                            <input name="titulo" value="{{ old('titulo', $oportunidad->titulo) }}" maxlength="150" required class="rounded border border-slate-300 px-2 py-1">
                        </label>
                        <label class="grid gap-1">Monto estimado (CLP)
                            <input name="monto_estimado" type="number" min="1" step="1" value="{{ old('monto_estimado', $oportunidad->monto_estimado ? (int) $oportunidad->monto_estimado : null) }}" class="rounded border border-slate-300 px-2 py-1">
                        </label>
                        <label class="grid gap-1">Cierre estimado
                            <input name="fecha_cierre_estimada" type="date" value="{{ old('fecha_cierre_estimada', $oportunidad->fecha_cierre_estimada?->format('Y-m-d')) }}" class="rounded border border-slate-300 px-2 py-1">
                        </label>
                        <div class="flex items-end"><button class="rounded border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Guardar datos</button></div>
                    </form>
                @endif
            </section>

            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="historial">
                <h2 class="font-medium">Historial de etapas</h2>
                <ol class="mt-2 space-y-2 border-l border-slate-200 pl-4">
                    @foreach ($oportunidad->historialesEtapa as $paso)
                        <li data-paso="{{ $paso->etapaPipeline->nombre }}">
                            <p class="font-medium">{{ $paso->etapaPipeline->nombre }}</p>
                            <p class="text-xs text-slate-500">{{ $paso->fecha_hora->setTimezone($zona)->format('d-m-Y H:i') }} · {{ $paso->usuario?->vendedor?->nombre ?? $paso->usuario?->email ?? 'Sistema' }}</p>
                            @if ($paso->comentario)<p class="text-slate-600">{{ $paso->comentario }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="interacciones">
                <h2 class="font-medium">Interacciones ligadas</h2>
                <p class="text-xs text-slate-500">Se registran desde la <a href="{{ route('crm.clientes.show', $oportunidad->id_cliente) }}" class="text-sky-700 hover:underline">ficha del cliente</a>.</p>
                <ul class="mt-2 space-y-1">
                    @forelse ($oportunidad->interacciones as $interaccion)
                        <li>{{ $interaccion->fecha_hora->setTimezone($zona)->format('d-m-Y') }} · {{ $interaccion->tipo }} · {{ $interaccion->resumen }}</li>
                    @empty
                        <li class="text-slate-500">Sin interacciones ligadas a esta oportunidad.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="etapa">
                <h2 class="font-medium">Cambiar de etapa</h2>
                @if ($cerrada)
                    <p class="mt-2 text-slate-600">Una oportunidad cerrada no se reabre: crea una nueva desde el pipeline.</p>
                    @if ($puedeDeshacer)
                        <form method="POST" action="{{ route('crm.oportunidades.deshacer', $oportunidad->id_oportunidad) }}" class="mt-3 space-y-2" data-deshacer>
                            @csrf
                            <p>La ganó el sistema al confirmar un pedido. Si el pedido no corresponde a esta oportunidad, deshaz el vínculo.</p>
                            <input name="motivo" maxlength="200" required placeholder="Motivo" class="w-full rounded border border-slate-300 px-2 py-1">
                            <button class="w-full rounded border border-amber-300 px-3 py-1.5 text-amber-800 hover:bg-amber-50">Deshacer vínculo</button>
                        </form>
                    @endif
                @else
                    <ul class="mt-2 space-y-3">
                        @foreach ($destinos as $destino => $bloqueo)
                            <li data-destino="{{ $destino }}">
                                <form method="POST" action="{{ route('crm.oportunidades.etapa', $oportunidad->id_oportunidad) }}" class="space-y-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="etapa" value="{{ $destino }}">
                                    @if ($destino === $P)
                                        <input name="motivo" maxlength="200" placeholder="Motivo de la pérdida (obligatorio)" class="w-full rounded border border-slate-300 px-2 py-1">
                                    @elseif ($destino === $G && $bloqueo !== null)
                                        @if ($pedidosVinculables->isEmpty())
                                            <p class="text-xs text-slate-500">El cliente no tiene pedidos para vincular.</p>
                                        @else
                                            <select name="id_pedido" class="w-full rounded border border-slate-300 px-2 py-1" aria-label="Pedido que gana la oportunidad">
                                                @foreach ($pedidosVinculables as $pedido)
                                                    <option value="{{ $pedido->id_pedido }}">Pedido N.º {{ $pedido->id_pedido }} · {{ $pedido->fecha_pedido->setTimezone($zona)->format('d-m-Y') }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    @endif
                                    @php($bloqueado = $destino !== $P && $bloqueo !== null && ! ($destino === $G && $pedidosVinculables->isNotEmpty()))
                                    <button @disabled($bloqueado) class="w-full rounded px-3 py-1.5 {{ $destino === $P ? 'border border-red-300 text-red-700 hover:bg-red-50' : 'bg-sky-700 text-white hover:bg-sky-800' }} disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500">
                                        Pasar a «{{ $destino }}»
                                    </button>
                                    @if ($bloqueado)<p class="text-xs text-slate-500" data-bloqueo>{{ $bloqueo }}</p>@endif
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="pedidos">
                <h2 class="font-medium">Pedidos vinculados</h2>
                <ul class="mt-2 space-y-1">
                    @forelse ($oportunidad->pedidos as $pedido)
                        <li><a href="{{ route('crm.pedidos.show', $pedido->id_pedido) }}" class="text-sky-700 hover:underline">Pedido N.º {{ $pedido->id_pedido }}</a> · {{ $pedido->estadoPedido->nombre }}</li>
                    @empty
                        <li class="text-slate-500">Sin pedidos vinculados.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection
