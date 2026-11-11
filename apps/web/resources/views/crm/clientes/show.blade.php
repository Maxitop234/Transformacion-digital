@extends('layouts.crm')

@section('titulo', $cliente->nombre_razon_social)

@section('contenido')
    <a href="{{ route('crm.clientes.index') }}" class="text-sm text-sky-700 hover:underline">&larr; Clientes</a>
    <h1 class="mt-2 text-2xl font-semibold">{{ $cliente->nombre_razon_social }}</h1>

    <dl class="mt-4 grid max-w-xl grid-cols-2 gap-x-4 gap-y-2 text-sm">
        <dt class="text-slate-500">Tipo</dt><dd>{{ $cliente->tipoCliente->nombre }} ({{ $cliente->tipoCliente->codigo }})</dd>
        <dt class="text-slate-500">Estado</dt><dd>{{ str_replace('_', ' ', $cliente->estado_cliente) }}</dd>
        <dt class="text-slate-500">RUT</dt><dd>{{ $cliente->rut ?? '—' }}</dd>
        <dt class="text-slate-500">Lista de precios</dt><dd>{{ $cliente->listaPrecio->nombre }}</dd>
        <dt class="text-slate-500">Vendedor</dt><dd>{{ $cliente->vendedor?->nombre ?? 'Sin asignar' }}</dd>
    </dl>

    <p class="mt-6 text-sm text-slate-500">Contactos, puntos de entrega, interacciones, pedidos y tickets: T-014.</p>
@endsection
