@extends('layouts.crm')

@section('titulo', 'Productos')

@section('contenido')
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Productos</h1>
        <a href="{{ route('crm.admin.productos.create') }}" class="rounded bg-sky-700 px-3 py-1.5 text-sm text-white hover:bg-sky-800">Nuevo producto</a>
    </div>

    @include('crm.admin._estado')

    <form method="GET" class="mt-4 flex gap-2 text-sm">
        <label for="q" class="sr-only">Buscar</label>
        <input id="q" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o SKU" class="w-72 rounded border border-slate-300 px-2 py-1">
        <button class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-100">Buscar</button>
    </form>

    <table class="mt-4 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-4">SKU</th>
                <th class="py-2 pr-4">Nombre</th>
                <th class="py-2 pr-4">Categoría</th>
                <th class="py-2 pr-4">Conservación</th>
                <th class="py-2 pr-4">Unidad</th>
                <th class="py-2 pr-4">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($productos as $producto)
                <tr class="border-b border-slate-200">
                    <td class="py-2 pr-4 whitespace-nowrap font-mono">{{ $producto->sku }}</td>
                    <td class="py-2 pr-4"><a class="text-sky-700 hover:underline" href="{{ route('crm.admin.productos.edit', $producto) }}">{{ $producto->nombre }}</a></td>
                    <td class="py-2 pr-4">{{ $producto->categoria->nombre }}</td>
                    <td class="py-2 pr-4">{{ $producto->rangoConservacion->nombre }}</td>
                    <td class="py-2 pr-4">{{ $producto->unidad_medida }}</td>
                    <td class="py-2 pr-4">{{ $producto->activo ? 'Activo' : 'Inactivo' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-4 text-slate-500">No hay productos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $productos->links() }}</div>
@endsection
