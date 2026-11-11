@extends('layouts.crm')

@section('titulo', 'Listas de precios')

@section('contenido')
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Listas de precios</h1>
        <a href="{{ route('crm.admin.listas-precio.create') }}" class="rounded bg-sky-700 px-3 py-1.5 text-sm text-white hover:bg-sky-800">Nueva lista</a>
    </div>

    @include('crm.admin._estado')

    <table class="mt-4 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-4">Nombre</th>
                <th class="py-2 pr-4">Vigencia</th>
                <th class="py-2 pr-4">Productos con precio</th>
                <th class="py-2 pr-4">Clientes</th>
                <th class="py-2 pr-4">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($listas as $lista)
                <tr class="border-b border-slate-200">
                    <td class="py-2 pr-4"><a class="text-sky-700 hover:underline" href="{{ route('crm.admin.listas-precio.edit', $lista) }}">{{ $lista->nombre }}</a></td>
                    <td class="py-2 pr-4">{{ $lista->vigente_desde->format('d-m-Y') }} – {{ $lista->vigente_hasta?->format('d-m-Y') ?? 'sin término' }}</td>
                    <td class="py-2 pr-4">{{ $lista->detalles_lista_precio_count }}</td>
                    <td class="py-2 pr-4">{{ $lista->clientes_count }}</td>
                    <td class="py-2 pr-4">{{ $lista->activa ? 'Activa' : 'Inactiva' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
