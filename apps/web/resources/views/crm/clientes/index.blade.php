@extends('layouts.crm')

@section('titulo', 'Clientes')

@section('contenido')
    <h1 class="text-2xl font-semibold">Clientes</h1>
    <p class="mt-1 text-sm text-slate-600">
        @if (auth()->user()->esAdministrador())
            Todas las carteras.
        @else
            Tu cartera.
        @endif
    </p>

    <table class="mt-4 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-4">Nombre o razón social</th>
                <th class="py-2 pr-4">Tipo</th>
                <th class="py-2 pr-4">Estado</th>
                <th class="py-2 pr-4">Vendedor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clientes as $cliente)
                <tr class="border-b border-slate-200">
                    <td class="py-2 pr-4"><a class="text-sky-700 hover:underline" href="{{ route('crm.clientes.show', $cliente->id_cliente) }}">{{ $cliente->nombre_razon_social }}</a></td>
                    <td class="py-2 pr-4">{{ $cliente->tipoCliente->codigo }}</td>
                    <td class="py-2 pr-4">{{ str_replace('_', ' ', $cliente->estado_cliente) }}</td>
                    <td class="py-2 pr-4">{{ $cliente->vendedor?->nombre ?? 'Sin asignar' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-4 text-slate-500">No hay clientes en tu cartera.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $clientes->links() }}</div>
@endsection
