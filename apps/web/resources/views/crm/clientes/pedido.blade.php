@extends('layouts.crm')

@section('titulo', 'Pedido telefónico')

@php($precios = app(\App\Domain\Catalogo\Precios::class))

@section('contenido')
    <a href="{{ route('crm.clientes.show', $cliente->id_cliente) }}" class="text-sm text-sky-700 hover:underline">&larr; {{ $cliente->nombre_razon_social }}</a>
    <h1 class="mt-2 text-2xl font-semibold">Pedido telefónico</h1>
    <p class="mt-1 text-sm text-slate-600">
        Mismas reglas que el portal (RN-08): lista «{{ $perfil->lista->nombre }}», pedido mínimo {{ $precios::clp($minimo) }} {{ $perfil->esB2B ? 'neto' : 'con IVA' }} y reserva de stock al crear.
        @unless ($perfil->esB2B)
            El cliente paga en línea desde el portal dentro de {{ config('negocio.reserva_stock_minutos_b2c') }} minutos; si no, el pedido se cancela.
        @endunless
    </p>

    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    @if (! $perfil->puedeComprar)
        <p class="rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">La cuenta del cliente aún no puede comprar.</p>
    @else
        <form method="POST" action="{{ route('crm.clientes.pedidos.store', $cliente->id_cliente) }}" class="grid max-w-3xl gap-4 text-sm">
            @csrf
            <table class="w-full">
                <thead class="text-left text-xs text-slate-500">
                    <tr><th class="py-1">Producto (precio neto de su lista)</th><th class="w-32">Cantidad</th></tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $filas; $i++)
                        <tr>
                            <td class="py-1 pr-2">
                                <select name="lineas[{{ $i }}][id_producto]" class="w-full rounded border border-slate-300 px-2 py-1" aria-label="Producto {{ $i + 1 }}">
                                    <option value="">—</option>
                                    @foreach ($productos as $producto)
                                        <option value="{{ $producto->id_producto }}" @selected(old("lineas.$i.id_producto") == $producto->id_producto)>
                                            {{ $producto->sku }} · {{ $producto->nombre }} · {{ $precios::clp($preciosNetos[$producto->id_producto]) }} por {{ $producto->unidad_medida }} · disp. {{ \App\Domain\Ventas\Montos::legible(\App\Domain\Ventas\Montos::centesimas((string) $producto->disponibleParaVenta())) }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" name="lineas[{{ $i }}][cantidad]" value="{{ old("lineas.$i.cantidad") }}" min="0.01" step="0.01" class="w-full rounded border border-slate-300 px-2 py-1" aria-label="Cantidad {{ $i + 1 }}"></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1">Punto de entrega
                    <select name="id_direccion" required class="rounded border border-slate-300 px-2 py-1">
                        @foreach ($direcciones as $direccion)
                            <option value="{{ $direccion->id_direccion }}" @selected(old('id_direccion') == $direccion->id_direccion)>{{ $direccion->alias }} · {{ $direccion->comuna->nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1">Fecha de entrega
                    <input type="date" name="fecha_entrega_solicitada" required min="{{ $primeraFecha->toDateString() }}" value="{{ old('fecha_entrega_solicitada', $primeraFecha->toDateString()) }}" class="rounded border border-slate-300 px-2 py-1">
                </label>
                @if ($perfil->esB2B)
                    <label class="grid gap-1">N.º de orden de compra
                        <input name="orden_compra" required maxlength="80" value="{{ old('orden_compra') }}" class="rounded border border-slate-300 px-2 py-1">
                    </label>
                @endif
                <label class="grid gap-1 sm:col-span-2">Observaciones
                    <input name="observaciones" maxlength="300" value="{{ old('observaciones') }}" class="rounded border border-slate-300 px-2 py-1">
                </label>
            </div>
            <div><button class="rounded bg-sky-700 px-4 py-2 text-white hover:bg-sky-800">Crear pedido</button></div>
        </form>
    @endif
@endsection
