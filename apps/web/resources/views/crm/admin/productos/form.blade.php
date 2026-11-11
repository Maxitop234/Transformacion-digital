@extends('layouts.crm')

@php($nuevo = ! $producto->exists)

@section('titulo', $nuevo ? 'Nuevo producto' : 'Editar producto')

@section('contenido')
    <a href="{{ route('crm.admin.productos.index') }}" class="text-sm text-sky-700 hover:underline">← Productos</a>
    <h1 class="mt-2 text-2xl font-semibold">{{ $nuevo ? 'Nuevo producto' : $producto->nombre }}</h1>

    @include('crm.admin._estado')

    <form method="POST" action="{{ $nuevo ? route('crm.admin.productos.store') : route('crm.admin.productos.update', $producto) }}" class="mt-4 grid max-w-xl gap-3 text-sm">
        @csrf
        @unless ($nuevo) @method('PATCH') @endunless

        <label class="grid gap-1">SKU
            <input name="sku" value="{{ old('sku', $producto->sku) }}" required maxlength="30" class="rounded border border-slate-300 px-2 py-1 font-mono">
        </label>
        <label class="grid gap-1">Nombre
            <input name="nombre" value="{{ old('nombre', $producto->nombre) }}" required maxlength="120" class="rounded border border-slate-300 px-2 py-1">
        </label>
        <label class="grid gap-1">Descripción
            <textarea name="descripcion" rows="3" class="rounded border border-slate-300 px-2 py-1">{{ old('descripcion', $producto->descripcion) }}</textarea>
        </label>
        <label class="grid gap-1">Categoría
            <select name="id_categoria" required class="rounded border border-slate-300 px-2 py-1">
                <option value="">Elige una categoría</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id_categoria }}" @selected(old('id_categoria', $producto->id_categoria) == $categoria->id_categoria)>{{ $categoria->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1">Rango de conservación
            <select name="id_rango" required class="rounded border border-slate-300 px-2 py-1">
                <option value="">Elige un rango</option>
                @foreach ($rangos as $rango)
                    <option value="{{ $rango->id_rango }}" @selected(old('id_rango', $producto->id_rango) == $rango->id_rango)>{{ $rango->nombre }} ({{ $rango->temp_min_c }} a {{ $rango->temp_max_c }} °C)</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1">Unidad de medida
            <select name="unidad_medida" required class="rounded border border-slate-300 px-2 py-1">
                @foreach ($unidades as $unidad)
                    <option value="{{ $unidad }}" @selected(old('unidad_medida', $producto->unidad_medida) === $unidad)>{{ $unidad }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $producto->activo))>
            Activo (visible en el catálogo)
        </label>

        <div>
            <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">{{ $nuevo ? 'Crear producto' : 'Guardar cambios' }}</button>
        </div>
    </form>
@endsection
