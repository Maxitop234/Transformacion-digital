@extends('layouts.portal')

@section('titulo', $producto->nombre)

@section('contenido')
    <a href="{{ route('portal.catalogo') }}" class="text-sm text-sky-700 hover:underline">← Catálogo</a>

    <div class="mt-4">@include('portal.catalogo._perfil')</div>

    <article class="mt-6 grid gap-6 md:grid-cols-3" data-sku="{{ $producto->sku }}">
        <div class="md:col-span-2">
            <p class="text-sm text-slate-500">{{ $producto->categoria->nombre }}</p>
            <h1 class="mt-1 text-2xl font-semibold">{{ $producto->nombre }}</h1>
            <p class="mt-1 text-sm text-slate-500">SKU {{ $producto->sku }} · se vende por {{ $producto->unidad_medida }}</p>

            @if ($producto->descripcion)
                <p class="mt-4 whitespace-pre-line">{{ $producto->descripcion }}</p>
            @endif

            <dl class="mt-6 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                <dt class="text-slate-500">Conservación</dt>
                <dd>{{ $producto->rangoConservacion->nombre }}: {{ (float) $producto->rangoConservacion->temp_min_c }} a {{ (float) $producto->rangoConservacion->temp_max_c }} °C</dd>
                <dt class="text-slate-500">Disponible para venta</dt>
                <dd>
                    @if ($producto->disponibleParaVenta() > 0)
                        {{ rtrim(rtrim(number_format($producto->disponibleParaVenta(), 2, ',', '.'), '0'), ',') }} {{ $producto->unidad_medida }}
                    @else
                        <span class="font-medium text-red-700" data-sin-stock>Sin stock</span>
                    @endif
                </dd>
            </dl>
        </div>

        <aside class="rounded border border-slate-200 p-4">
            @include('portal.catalogo._precio', ['neto' => $precioNeto])
            @if ($precioNeto !== null && ! $perfil->muestraNeto())
                <p class="text-xs text-slate-500">{{ \App\Domain\Catalogo\Precios::clp($precioNeto) }} neto</p>
            @elseif ($precioNeto !== null)
                <p class="text-xs text-slate-500">{{ \App\Domain\Catalogo\Precios::clp(app(\App\Domain\Catalogo\Precios::class)->conIva($precioNeto)) }} con IVA</p>
            @endif
            <div class="mt-4">@include('portal.catalogo._agregar', ['neto' => $precioNeto, 'conCantidad' => true])</div>
        </aside>
    </article>
@endsection
