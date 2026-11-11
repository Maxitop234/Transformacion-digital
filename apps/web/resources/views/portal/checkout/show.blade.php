@extends('layouts.portal')

@section('titulo', 'Checkout')

@php($precios = app(\App\Domain\Catalogo\Precios::class))

@section('contenido')
    <a href="{{ route('portal.carrito') }}" class="text-sm text-sky-700 hover:underline">← Volver al carrito</a>
    <h1 class="mt-2 text-2xl font-semibold">Checkout</h1>

    <div class="mt-4">@include('portal.catalogo._perfil')</div>

    @if ($bloqueos)
        <ul class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900" role="alert" data-bloqueos>
            @foreach ($bloqueos as $bloqueo)
                <li>{{ $bloqueo }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('portal.pedidos.store') }}" class="mt-6 grid gap-8 md:grid-cols-3">
        @csrf
        <div class="space-y-6 md:col-span-2">
            <fieldset>
                <legend class="font-medium">Punto de entrega</legend>
                <div class="mt-2 space-y-2">
                    @forelse ($direcciones as $direccion)
                        <label class="flex gap-3 rounded border border-slate-200 p-3 text-sm">
                            <input type="radio" name="id_direccion" value="{{ $direccion->id_direccion }}" required
                                   @checked(old('id_direccion', $direcciones->count() === 1 ? $direccion->id_direccion : null) == $direccion->id_direccion)>
                            <span>
                                <strong>{{ $direccion->alias }}</strong> · {{ $direccion->calle }}, {{ $direccion->comuna->nombre }}
                                <span class="block text-xs text-slate-500">
                                    Ventana de recepción:
                                    @if ($direccion->ventana_inicio && $direccion->ventana_fin)
                                        {{ substr($direccion->ventana_inicio, 0, 5) }} a {{ substr($direccion->ventana_fin, 0, 5) }}
                                    @else
                                        horario de reparto
                                    @endif
                                </span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-slate-600">No tienes puntos de entrega activos. Contacta a Cofrial para registrar uno.</p>
                    @endforelse
                </div>
                @error('id_direccion')<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </fieldset>

            <div>
                <label for="fecha_entrega_solicitada" class="font-medium">Fecha de entrega</label>
                <input id="fecha_entrega_solicitada" type="date" name="fecha_entrega_solicitada" required
                       min="{{ $primeraFecha->toDateString() }}" value="{{ old('fecha_entrega_solicitada', $primeraFecha->toDateString()) }}"
                       class="mt-1 block rounded border border-slate-300 px-2 py-1">
                <p class="mt-1 text-xs text-slate-500">Desde el {{ $primeraFecha->format('d-m-Y') }}, de lunes a sábado.</p>
                @error('fecha_entrega_solicitada')<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>

            <fieldset>
                <legend class="font-medium">Forma de pago</legend>
                @if ($perfil->esB2B)
                    <p class="mt-1 text-sm text-slate-600">Orden de compra con crédito a 30 días. Tu ejecutivo la valida antes de confirmar el pedido.</p>
                    <label for="orden_compra" class="mt-2 block text-sm">N.º de orden de compra</label>
                    <input id="orden_compra" name="orden_compra" value="{{ old('orden_compra') }}" required maxlength="80"
                           class="mt-1 w-64 rounded border border-slate-300 px-2 py-1">
                    @error('orden_compra')<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                @else
                    <p class="mt-1 text-sm text-slate-600">
                        Pago en línea (tarjeta o transferencia) en el paso siguiente. Tienes {{ config('negocio.reserva_stock_minutos_b2c') }} minutos para pagar; si no, el pedido se cancela y el stock se libera.
                    </p>
                @endif
            </fieldset>

            <div>
                <label for="observaciones" class="font-medium">Observaciones <span class="text-sm font-normal text-slate-500">(opcional)</span></label>
                <textarea id="observaciones" name="observaciones" maxlength="300" rows="2" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1 text-sm">{{ old('observaciones') }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Indicaciones para la entrega. No incluyas datos personales.</p>
            </div>
        </div>

        <aside class="h-fit rounded border border-slate-200 p-4 text-sm">
            <h2 class="font-medium">Resumen</h2>
            <ul class="mt-2 space-y-1">
                @foreach ($resumen->lineas as $linea)
                    <li class="flex justify-between gap-2">
                        <span>{{ $linea->producto->nombre }} × {{ \App\Domain\Ventas\Montos::legible(\App\Domain\Ventas\Montos::centesimas($linea->cantidad)) }}</span>
                        <span class="whitespace-nowrap">{{ $linea->subtotalNeto === null ? '—' : $precios::clp($linea->subtotalNeto) }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="mt-3 grid grid-cols-2 gap-y-1 border-t border-slate-200 pt-2" data-totales>
                <dt class="text-slate-600">Neto</dt>
                <dd class="text-right" data-neto="{{ $resumen->neto }}">{{ $precios::clp($resumen->neto) }}</dd>
                <dt class="text-slate-600">IVA ({{ config('negocio.iva_pct') }} %)</dt>
                <dd class="text-right" data-iva="{{ $resumen->iva }}">{{ $precios::clp($resumen->iva) }}</dd>
                <dt class="font-semibold">Total</dt>
                <dd class="text-right font-semibold" data-total="{{ $resumen->total }}">{{ $precios::clp($resumen->total) }}</dd>
            </dl>
            <p class="mt-2 text-xs text-slate-500">Pedido mínimo: {{ $precios::clp($minimo) }} {{ $perfil->esB2B ? 'neto' : 'con IVA' }}.</p>

            <button type="submit" data-confirmar @disabled($bloqueos !== [])
                    @class([
                        'mt-4 w-full rounded px-4 py-2 font-medium',
                        'bg-sky-700 text-white hover:bg-sky-800' => $bloqueos === [],
                        'cursor-not-allowed bg-slate-300 text-slate-600' => $bloqueos !== [],
                    ])>Confirmar pedido</button>
        </aside>
    </form>
@endsection
