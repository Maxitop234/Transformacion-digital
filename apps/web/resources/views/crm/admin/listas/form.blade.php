@extends('layouts.crm')

@php($nueva = ! $lista->exists)

@section('titulo', $nueva ? 'Nueva lista de precios' : $lista->nombre)

@section('contenido')
    <a href="{{ route('crm.admin.listas-precio.index') }}" class="text-sm text-sky-700 hover:underline">← Listas de precios</a>
    <h1 class="mt-2 text-2xl font-semibold">{{ $nueva ? 'Nueva lista de precios' : $lista->nombre }}</h1>

    @include('crm.admin._estado')

    <form method="POST" action="{{ $nueva ? route('crm.admin.listas-precio.store') : route('crm.admin.listas-precio.update', $lista) }}" class="mt-4 grid max-w-xl gap-3 text-sm">
        @csrf
        @unless ($nueva) @method('PATCH') @endunless

        <label class="grid gap-1">Nombre
            <input name="nombre" value="{{ old('nombre', $lista->nombre) }}" required maxlength="80" class="rounded border border-slate-300 px-2 py-1">
        </label>
        <div class="grid grid-cols-2 gap-3">
            <label class="grid gap-1">Vigente desde
                <input type="date" name="vigente_desde" value="{{ old('vigente_desde', $lista->vigente_desde?->format('Y-m-d')) }}" required class="rounded border border-slate-300 px-2 py-1">
            </label>
            <label class="grid gap-1">Vigente hasta (opcional)
                <input type="date" name="vigente_hasta" value="{{ old('vigente_hasta', $lista->vigente_hasta?->format('Y-m-d')) }}" class="rounded border border-slate-300 px-2 py-1">
            </label>
        </div>
        <label class="flex items-center gap-2">
            <input type="hidden" name="activa" value="0">
            <input type="checkbox" name="activa" value="1" @checked(old('activa', $lista->activa))>
            Activa
        </label>
        <div>
            <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">{{ $nueva ? 'Crear lista' : 'Guardar lista' }}</button>
        </div>
    </form>

    @unless ($nueva)
        <h2 class="mt-8 text-lg font-semibold">Precios</h2>
        <p class="mt-1 text-sm text-slate-600">Precio unitario neto en pesos chilenos. Deja vacío para quitar el producto de la lista. Los pedidos ya creados conservan su precio pactado (RN-01).</p>

        <form method="POST" action="{{ route('crm.admin.listas-precio.precios', $lista) }}" class="mt-4 text-sm">
            @csrf
            @method('PATCH')
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-slate-300 text-left">
                        <th class="py-2 pr-4">SKU</th>
                        <th class="py-2 pr-4">Producto</th>
                        <th class="py-2 pr-4">Categoría</th>
                        <th class="py-2 pr-4">Precio (CLP)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($productos as $producto)
                        <tr class="border-b border-slate-200">
                            <td class="py-1.5 pr-4 whitespace-nowrap font-mono">{{ $producto->sku }}</td>
                            <td class="py-1.5 pr-4">{{ $producto->nombre }} @unless ($producto->activo)<span class="text-xs text-slate-500">(inactivo)</span>@endunless</td>
                            <td class="py-1.5 pr-4">{{ $producto->categoria->nombre }}</td>
                            <td class="py-1.5 pr-4">
                                <label class="sr-only" for="precio-{{ $producto->id_producto }}">Precio de {{ $producto->nombre }}</label>
                                <input id="precio-{{ $producto->id_producto }}" name="precios[{{ $producto->id_producto }}]" inputmode="decimal"
                                       value="{{ old('precios.'.$producto->id_producto, isset($precios[$producto->id_producto]) ? rtrim(rtrim($precios[$producto->id_producto], '0'), '.') : '') }}"
                                       class="w-32 rounded border border-slate-300 px-2 py-1 text-right">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button class="mt-4 rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Guardar precios</button>
        </form>
    @endunless
@endsection
