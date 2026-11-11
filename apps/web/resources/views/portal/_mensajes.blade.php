{{--
    Mensajes de la última acción del carrito: confirmación, aviso de ajuste y
    errores de `sku`/`cantidad`/`pedido`. Los formularios con sus propios campos (login)
    muestran sus errores junto a cada campo.
--}}
@if (session('estado'))
    <p class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900" role="status" data-estado>{{ session('estado') }}</p>
@endif
@if (session('aviso'))
    <p class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900" role="alert" data-aviso>{{ session('aviso') }}</p>
@endif
@if ($errors->hasAny(['sku', 'cantidad', 'pedido']))
    <ul class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-900" role="alert" data-errores>
        @foreach ([...$errors->get('sku'), ...$errors->get('cantidad'), ...$errors->get('pedido')] as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
