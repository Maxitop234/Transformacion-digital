@extends('layouts.crm')

@section('titulo', 'Oportunidades')

@php($clp = fn ($monto) => \App\Domain\Catalogo\Precios::clp($monto))

@section('contenido')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Pipeline de oportunidades</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ auth()->user()->esAdministrador() ? 'Oportunidades de todo el equipo.' : 'Oportunidades de tu cartera.' }}
                Las cerradas se muestran {{ $diasCerradas }} días.
            </p>
        </div>
    </div>

    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    <details class="mb-6 rounded border border-slate-200 bg-white p-4 text-sm" @if ($errors->any()) open @endif data-nueva-oportunidad>
        <summary class="cursor-pointer font-medium">Nueva oportunidad</summary>
        <form method="POST" action="{{ route('crm.oportunidades.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <label class="grid gap-1 lg:col-span-2">Cliente
                <select name="id_cliente" required class="rounded border border-slate-300 px-2 py-1">
                    <option value="">Elige un cliente</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id_cliente }}" @selected(old('id_cliente') == $cliente->id_cliente)>{{ $cliente->nombre_razon_social }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 lg:col-span-3">Título
                <input name="titulo" value="{{ old('titulo') }}" maxlength="150" required class="rounded border border-slate-300 px-2 py-1">
            </label>
            <label class="grid gap-1">Origen
                <select name="origen" class="rounded border border-slate-300 px-2 py-1">
                    @foreach ($origenes as $origen)
                        <option value="{{ $origen }}" @selected(old('origen') === $origen)>{{ $origen === 'telefono' ? 'teléfono' : $origen }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1">Monto estimado (CLP, opcional)
                <input name="monto_estimado" type="number" min="1" step="1" value="{{ old('monto_estimado') }}" class="rounded border border-slate-300 px-2 py-1">
            </label>
            <label class="grid gap-1">Cierre estimado (opcional)
                <input name="fecha_cierre_estimada" type="date" value="{{ old('fecha_cierre_estimada') }}" class="rounded border border-slate-300 px-2 py-1">
            </label>
            <div class="flex items-end"><button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Crear</button></div>
        </form>
    </details>

    <div class="flex gap-3 overflow-x-auto pb-4" data-pipeline>
        @foreach ($etapas as $etapa)
            @php($columna = $oportunidades->get($etapa->id_etapa, collect()))
            <section class="w-64 shrink-0 rounded border border-slate-200 bg-slate-50 p-2 text-sm" data-etapa="{{ $etapa->nombre }}">
                <h2 class="flex items-center justify-between px-1 font-medium">
                    <span>{{ $etapa->nombre }}</span>
                    <span class="rounded bg-white px-2 text-xs text-slate-600">{{ $columna->count() }}</span>
                </h2>
                <p class="px-1 text-xs text-slate-500">{{ $clp($columna->sum(fn ($o) => (float) $o->monto_estimado)) }}</p>
                <ul class="mt-2 space-y-2">
                    @forelse ($columna as $oportunidad)
                        @php($dias = (int) \Carbon\CarbonImmutable::parse($oportunidad->en_etapa_desde ?? $oportunidad->fecha_creacion)->diffInDays(now()))
                        <li class="rounded border border-slate-200 bg-white p-2" data-oportunidad="{{ $oportunidad->id_oportunidad }}">
                            <a href="{{ route('crm.oportunidades.show', $oportunidad->id_oportunidad) }}" class="font-medium text-sky-700 hover:underline">{{ $oportunidad->titulo }}</a>
                            <p class="text-xs text-slate-600">{{ $oportunidad->cliente->nombre_razon_social }}</p>
                            <p class="mt-1 flex justify-between text-xs text-slate-500">
                                <span>{{ $oportunidad->monto_estimado ? $clp($oportunidad->monto_estimado) : 'Sin monto' }}</span>
                                <span data-dias-en-etapa="{{ $dias }}">{{ $dias === 1 ? '1 día' : "{$dias} días" }}</span>
                            </p>
                            @if (auth()->user()->esAdministrador())
                                <p class="text-xs text-slate-500">{{ $oportunidad->vendedor?->nombre }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="px-1 text-xs text-slate-400">Sin oportunidades.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
@endsection
