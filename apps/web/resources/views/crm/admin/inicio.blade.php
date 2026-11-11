@extends('layouts.crm')

@section('titulo', 'Administración')

@section('contenido')
    <h1 class="text-2xl font-semibold">Administración</h1>
    <p class="mt-1 text-sm text-slate-600">Mantenedores del catálogo, los precios, el stock y los usuarios.</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach ([
            ['crm.admin.productos.index', 'Productos', 'Alta y edición, categoría, rango de conservación, activar y desactivar.'],
            ['crm.admin.listas-precio.index', 'Listas de precios', 'Lista pública e institucionales, vigencia y precio por producto.'],
            ['crm.admin.inventario.index', 'Inventario', 'Ingresos y ajustes de stock; cada uno queda en la bitácora de movimientos.'],
            ['crm.admin.usuarios.index', 'Usuarios', 'Personal y roles, activar y desactivar, reasignar la cartera de un vendedor.'],
        ] as [$ruta, $titulo, $texto])
            <a href="{{ route($ruta) }}" class="block rounded border border-slate-200 bg-white p-4 hover:border-sky-300">
                <h2 class="font-medium text-sky-700">{{ $titulo }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $texto }}</p>
            </a>
        @endforeach
    </div>
@endsection
