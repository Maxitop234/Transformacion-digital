{{-- Precio de un producto según el perfil. Recibe $neto (string|null) y $perfil. --}}
@php($precios = app(\App\Domain\Catalogo\Precios::class))
@if ($neto === null)
    <p class="text-sm text-slate-500" data-precio="">Precio no disponible en tu lista</p>
@elseif ($perfil->muestraNeto())
    <p data-precio="{{ $neto }}">
        <span class="text-lg font-semibold">{{ $precios::clp($neto) }}</span>
        <span class="text-xs text-slate-500">+ IVA</span>
    </p>
@else
    <p data-precio="{{ $neto }}">
        <span class="text-lg font-semibold">{{ $precios::clp($precios->conIva($neto)) }}</span>
        <span class="text-xs text-slate-500">IVA incl.</span>
    </p>
@endif
