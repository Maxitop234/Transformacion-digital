@extends('layouts.crm')

@section('titulo', 'Inventario')

@section('contenido')
    <h1 class="text-2xl font-semibold">Inventario</h1>
    <p class="mt-1 text-sm text-slate-600">Disponible para venta = stock disponible − reservado (RN-04). Cada ingreso o ajuste queda en la bitácora con su responsable.</p>

    @include('crm.admin._estado')

    <form method="POST" action="{{ route('crm.admin.inventario.store') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded border border-slate-200 bg-white p-4 text-sm">
        @csrf
        <label class="grid gap-1">Producto
            <select name="id_producto" required class="w-72 rounded border border-slate-300 px-2 py-1">
                <option value="">Elige un producto</option>
                @foreach ($productos as $producto)
                    <option value="{{ $producto->id_producto }}" @selected(old('id_producto') == $producto->id_producto)>{{ $producto->sku }} · {{ $producto->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1">Tipo
            <select name="tipo" required class="rounded border border-slate-300 px-2 py-1">
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ ucfirst($tipo) }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1">Cantidad
            <input name="cantidad" value="{{ old('cantidad') }}" required inputmode="decimal" class="w-28 rounded border border-slate-300 px-2 py-1 text-right">
        </label>
        <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Registrar</button>
        <p class="w-full text-xs text-slate-500">Un ingreso suma. Un ajuste corrige con su signo: «-3» descuenta 3 unidades.</p>
    </form>

    <table class="mt-6 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-4">SKU</th>
                <th class="py-2 pr-4">Producto</th>
                <th class="py-2 pr-4 text-right">Disponible</th>
                <th class="py-2 pr-4 text-right">Reservado</th>
                <th class="py-2 pr-4 text-right">Para venta</th>
                <th class="py-2 pr-4">Unidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($productos as $producto)
                @php($inv = $producto->inventario)
                <tr class="border-b border-slate-200">
                    <td class="py-1.5 pr-4 whitespace-nowrap font-mono">{{ $producto->sku }}</td>
                    <td class="py-1.5 pr-4">{{ $producto->nombre }}</td>
                    <td class="py-1.5 pr-4 text-right">{{ $inv?->stock_disponible ?? '0.00' }}</td>
                    <td class="py-1.5 pr-4 text-right">{{ $inv?->stock_reservado ?? '0.00' }}</td>
                    <td class="py-1.5 pr-4 text-right font-medium">{{ number_format((float) ($inv?->stock_disponible ?? 0) - (float) ($inv?->stock_reservado ?? 0), 2, '.', '') }}</td>
                    <td class="py-1.5 pr-4">{{ $producto->unidad_medida }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 class="mt-8 text-lg font-semibold">Últimos movimientos</h2>
    <table class="mt-2 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-4">Fecha</th>
                <th class="py-2 pr-4">Producto</th>
                <th class="py-2 pr-4">Tipo</th>
                <th class="py-2 pr-4 text-right">Cantidad</th>
                <th class="py-2 pr-4">Responsable</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimientos as $movimiento)
                <tr class="border-b border-slate-200">
                    <td class="py-1.5 pr-4">{{ $movimiento->fecha_hora->timezone('America/Santiago')->format('d-m-Y H:i') }}</td>
                    <td class="py-1.5 pr-4">{{ $movimiento->producto->sku }}</td>
                    <td class="py-1.5 pr-4">{{ $movimiento->tipo }}</td>
                    <td class="py-1.5 pr-4 text-right">{{ $movimiento->cantidad }}</td>
                    <td class="py-1.5 pr-4">{{ $movimiento->usuario->email }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-slate-500">Sin movimientos.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
