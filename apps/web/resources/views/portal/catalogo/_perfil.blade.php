{{-- Perfil activo y lista de precios en uso (T-008). --}}
<div class="rounded border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" data-perfil>
    <p>
        Perfil: <strong>{{ $perfil->etiqueta }}</strong>
        · Lista de precios: <strong data-lista>{{ $perfil->lista->nombre }}</strong>
        · {{ $perfil->muestraNeto() ? 'Precios netos (más IVA)' : 'Precios con IVA incluido' }}
    </p>
    @if ($perfil->esB2B && ! $perfil->puedeComprar)
        <p class="mt-1">Tu empresa está pendiente de validación: mientras tanto ves los precios de «{{ $perfil->lista->nombre }}» y aún no puedes comprar. Un ejecutivo la revisará pronto.</p>
    @endif
    @guest
        <p class="mt-1"><a href="{{ route('login') }}" class="underline">Inicia sesión</a> para ver los precios de tu empresa y comprar.</p>
    @endguest
</div>
