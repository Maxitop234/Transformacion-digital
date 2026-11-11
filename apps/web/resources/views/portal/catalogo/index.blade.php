@extends('layouts.portal')

@section('titulo', 'Catálogo')

@section('contenido')
    <h1 class="text-2xl font-semibold">Catálogo</h1>

    <div class="mt-4">@include('portal.catalogo._perfil')</div>

    <form method="GET" action="{{ route('portal.catalogo') }}" class="mt-4 flex flex-wrap items-end gap-3 text-sm" aria-label="Filtros del catálogo">
        <label class="grid gap-1">Buscar
            <input name="q" value="{{ request('q') }}" placeholder="Nombre o SKU" maxlength="100" class="w-56 rounded border border-slate-300 px-2 py-1">
        </label>
        <label class="grid gap-1">Categoría
            <select name="categoria" class="rounded border border-slate-300 px-2 py-1">
                <option value="">Todas</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id_categoria }}" @selected(request('categoria') == $categoria->id_categoria)>{{ $categoria->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1">Conservación
            <select name="rango" class="rounded border border-slate-300 px-2 py-1">
                <option value="">Todas</option>
                @foreach ($rangos as $rango)
                    <option value="{{ $rango->id_rango }}" @selected(request('rango') == $rango->id_rango)>{{ $rango->nombre }} ({{ (float) $rango->temp_min_c }} a {{ (float) $rango->temp_max_c }} °C)</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 pb-1">
            <input type="checkbox" name="disponible" value="1" @checked(request()->boolean('disponible'))>
            Solo con stock
        </label>
        <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Filtrar</button>
        @if (request()->hasAny(['q', 'categoria', 'rango', 'disponible']))
            <a href="{{ route('portal.catalogo') }}" class="pb-1 text-sky-700 hover:underline">Limpiar filtros</a>
        @endif
    </form>

    <p class="mt-4 text-sm text-slate-600">{{ $productos->total() }} {{ $productos->total() === 1 ? 'producto' : 'productos' }}</p>

    <ul class="mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-productos>
        @forelse ($productos as $producto)
            @php($neto = $preciosNetos->get($producto->id_producto))
            <li class="flex flex-col justify-between rounded border border-slate-200 p-4" data-sku="{{ $producto->sku }}">
                <div>
                    <p class="text-xs text-slate-500">{{ $producto->categoria->nombre }} · {{ $producto->rangoConservacion->nombre }}</p>
                    <h2 class="mt-1 font-medium">
                        <a href="{{ route('portal.productos.show', $producto->sku) }}" class="hover:underline">{{ $producto->nombre }}</a>
                    </h2>
                    <p class="text-xs text-slate-500">SKU {{ $producto->sku }} · por {{ $producto->unidad_medida }}</p>
                </div>
                <div class="mt-3 flex items-end justify-between gap-2">
                    <div>
                        @include('portal.catalogo._precio', ['neto' => $neto])
                        @if ($producto->disponibleParaVenta() > 0)
                            <p class="text-xs text-emerald-700">Disponible</p>
                        @else
                            <p class="text-xs font-medium text-red-700" data-sin-stock>Sin stock</p>
                        @endif
                    </div>
                    @include('portal.catalogo._agregar', ['neto' => $neto])
                </div>
            </li>
        @empty
            <li class="text-slate-500">No hay productos que coincidan con los filtros.</li>
        @endforelse
    </ul>

    <div class="mt-6">{{ $productos->links() }}</div>
@endsection
