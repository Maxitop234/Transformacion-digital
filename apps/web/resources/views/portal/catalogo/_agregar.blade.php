{{--
    Botón «Agregar». Recibe $producto, $neto y $perfil. Se deshabilita sin
    disponible para venta (RN-04), sin precio en la lista o si la cuenta no
    puede comprar (RN-02). Con $conCantidad (detalle del producto) pide la
    cantidad; si no, agrega 1. Agrega al carrito (T-010) sin reservar stock.
--}}
@php
    $disponible = $producto->disponibleParaVenta();
    $motivo = match (true) {
        $disponible <= 0 => 'Sin stock',
        $neto === null => 'Sin precio en tu lista',
        auth()->guest() => null,
        ! $perfil->puedeComprar => $perfil->esB2B ? 'Empresa pendiente de validación' : 'Tu cuenta no puede comprar',
        default => null,
    };
@endphp
<div>
    @guest
        @if ($motivo === null)
            <a href="{{ route('login') }}" class="inline-block rounded bg-sky-700 px-3 py-1.5 text-sm text-white hover:bg-sky-800">Inicia sesión para comprar</a>
        @else
            <button type="button" disabled class="cursor-not-allowed rounded bg-slate-300 px-3 py-1.5 text-sm text-slate-600">Agregar</button>
            <p class="mt-1 text-xs text-slate-500">{{ $motivo }}</p>
        @endif
    @else
        <form method="POST" action="{{ route('portal.carrito.items.store') }}" class="flex items-center gap-2">
            @csrf
            <input type="hidden" name="sku" value="{{ $producto->sku }}">
            @if (($conCantidad ?? false) && $motivo === null)
                <label class="sr-only" for="cantidad-{{ $producto->sku }}">Cantidad</label>
                <input id="cantidad-{{ $producto->sku }}" type="number" name="cantidad" value="1" min="{{ $producto->unidad_medida === 'kg' ? '0.01' : '1' }}" step="{{ $producto->unidad_medida === 'kg' ? '0.01' : '1' }}" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm">
                <span class="text-xs text-slate-500">{{ $producto->unidad_medida }}</span>
            @endif
            <button type="submit" data-agregar="{{ $producto->sku }}" @disabled($motivo !== null)
                    @class([
                        'rounded px-3 py-1.5 text-sm',
                        'bg-sky-700 text-white hover:bg-sky-800' => $motivo === null,
                        'cursor-not-allowed bg-slate-300 text-slate-600' => $motivo !== null,
                    ])>Agregar</button>
        </form>
        @if ($motivo)
            <p class="mt-1 text-xs text-slate-500">{{ $motivo }}</p>
        @endif
    @endguest
</div>
