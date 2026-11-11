{{--
    Botón «Agregar». Recibe $producto, $neto y $perfil. Se deshabilita sin
    disponible para venta (RN-04), sin precio en la lista o si la cuenta no
    puede comprar (RN-02). El carrito y su acción llegan en T-010.
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
        <button type="button" data-agregar="{{ $producto->sku }}" @disabled($motivo !== null)
                @class([
                    'rounded px-3 py-1.5 text-sm',
                    'bg-sky-700 text-white hover:bg-sky-800' => $motivo === null,
                    'cursor-not-allowed bg-slate-300 text-slate-600' => $motivo !== null,
                ])>Agregar</button>
        @if ($motivo)
            <p class="mt-1 text-xs text-slate-500">{{ $motivo }}</p>
        @endif
    @endguest
</div>
