@extends('layouts.portal')

@section('titulo', 'Carrito')

@php($precios = app(\App\Domain\Catalogo\Precios::class))

@section('contenido')
    <h1 class="text-2xl font-semibold">Carrito</h1>

    <div class="mt-4">@include('portal.catalogo._perfil')</div>

    @if ($resumen->vacio())
        <p class="mt-6 text-slate-600" data-carrito-vacio>Tu carrito está vacío.</p>
        <a href="{{ route('portal.catalogo') }}" class="mt-2 inline-block text-sky-700 hover:underline">Ir al catálogo</a>
    @else
        <p class="mt-4 text-sm text-slate-600">
            Los precios se calculan con tu lista vigente cada vez que abres el carrito. Agregar productos no los reserva: el stock se reserva al confirmar el pedido.
        </p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm" data-carrito>
                <thead class="border-b border-slate-200 text-left text-slate-500">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Producto</th>
                        <th class="py-2 pr-4 text-right font-medium">Precio {{ $perfil->muestraNeto() ? 'neto' : 'IVA incl.' }}</th>
                        <th class="py-2 pr-4 font-medium">Cantidad</th>
                        <th class="py-2 pr-4 text-right font-medium">Subtotal</th>
                        <th class="py-2"><span class="sr-only">Quitar</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($resumen->lineas as $linea)
                        @php($producto = $linea->producto)
                        <tr class="border-b border-slate-100 align-top" data-linea="{{ $producto->sku }}">
                            <td class="py-3 pr-4">
                                <a href="{{ route('portal.productos.show', $producto->sku) }}" class="font-medium hover:underline">{{ $producto->nombre }}</a>
                                <p class="text-xs text-slate-500">SKU {{ $producto->sku }} · por {{ $producto->unidad_medida }}</p>
                                @if (! $linea->tienePrecio())
                                    <p class="mt-1 text-xs font-medium text-red-700">Ya no está disponible en tu lista: quítalo para continuar.</p>
                                @elseif ($linea->excedeDisponible())
                                    <p class="mt-1 text-xs font-medium text-amber-700" data-excede>
                                        Hoy quedan {{ rtrim(rtrim(number_format($linea->disponible, 2, ',', '.'), '0'), ',') }} {{ $producto->unidad_medida }} disponibles. Actualiza la cantidad.
                                    </p>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-right whitespace-nowrap">
                                @if ($linea->tienePrecio())
                                    {{ $precios::clp($perfil->muestraNeto() ? $linea->precioNeto : $precios->conIva($linea->precioNeto)) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                <form method="POST" action="{{ route('portal.carrito.items.update', $producto->sku) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="cantidad-{{ $producto->sku }}">Cantidad de {{ $producto->nombre }}</label>
                                    <input id="cantidad-{{ $producto->sku }}" type="number" name="cantidad"
                                           value="{{ rtrim(rtrim($linea->cantidad, '0'), '.') }}"
                                           min="{{ $producto->unidad_medida === 'kg' ? '0.01' : '1' }}" step="{{ $producto->unidad_medida === 'kg' ? '0.01' : '1' }}"
                                           class="w-24 rounded border border-slate-300 px-2 py-1">
                                    <button type="submit" class="text-sky-700 hover:underline">Actualizar</button>
                                </form>
                            </td>
                            <td class="py-3 pr-4 text-right whitespace-nowrap" data-subtotal="{{ $linea->subtotalNeto }}">
                                @if ($linea->subtotalNeto !== null)
                                    {{ $precios::clp($perfil->muestraNeto() ? $linea->subtotalNeto : $precios->conIva($linea->subtotalNeto)) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3 text-right">
                                <form method="POST" action="{{ route('portal.carrito.items.destroy', $producto->sku) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-700 hover:underline">Quitar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <dl class="mt-6 ml-auto grid w-full max-w-xs grid-cols-2 gap-y-1 text-sm" data-totales>
            <dt class="text-slate-600">Neto</dt>
            <dd class="text-right" data-neto="{{ $resumen->neto }}">{{ $precios::clp($resumen->neto) }}</dd>
            <dt class="text-slate-600">IVA ({{ config('negocio.iva_pct') }} %)</dt>
            <dd class="text-right" data-iva="{{ $resumen->iva }}">{{ $precios::clp($resumen->iva) }}</dd>
            <dt class="border-t border-slate-200 pt-1 font-semibold">Total</dt>
            <dd class="border-t border-slate-200 pt-1 text-right font-semibold" data-total="{{ $resumen->total }}">{{ $precios::clp($resumen->total) }}</dd>
        </dl>

        <div class="mt-6 flex justify-between">
            <a href="{{ route('portal.catalogo') }}" class="text-sky-700 hover:underline">← Seguir comprando</a>
            <span class="text-sm text-slate-500">El pago y la fecha de entrega se eligen en el checkout.</span>
        </div>
    @endif
@endsection
