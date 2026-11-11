@extends('layouts.portal')

@section('titulo', 'Pago del pedido '.$pedido->id_pedido)

@php($precios = app(\App\Domain\Catalogo\Precios::class))

@section('contenido')
    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-semibold">Pago del pedido N.º {{ $pedido->id_pedido }}</h1>
        <p class="mt-1 text-sm text-slate-600">Pasarela simulada: no se cobra nada real.</p>

        <dl class="mt-4 grid grid-cols-2 gap-y-1 rounded border border-slate-200 p-4 text-sm">
            @foreach ($detalles as $detalle)
                <dt>{{ $detalle->producto->nombre }} × {{ \App\Domain\Ventas\Montos::legible(\App\Domain\Ventas\Montos::centesimas((string) $detalle->cantidad)) }}</dt>
                <dd class="text-right">{{ $precios::clp(\App\Domain\Ventas\Montos::subtotalNeto((string) $detalle->precio_unitario_pactado, (string) $detalle->cantidad)) }}</dd>
            @endforeach
            <dt class="border-t border-slate-200 pt-1 text-slate-600">Neto</dt>
            <dd class="border-t border-slate-200 pt-1 text-right">{{ $precios::clp($totales['neto']) }}</dd>
            <dt class="text-slate-600">IVA</dt>
            <dd class="text-right">{{ $precios::clp($totales['iva']) }}</dd>
            <dt class="font-semibold">Total a pagar</dt>
            <dd class="text-right font-semibold" data-total="{{ $totales['total'] }}">{{ $precios::clp($totales['total']) }}</dd>
        </dl>

        @if ($pedido->estadoPedido->nombre === \App\Models\EstadoPedido::PENDIENTE && now()->lessThanOrEqualTo($vence))
            <p class="mt-4 text-sm">Tu stock está reservado hasta las <strong>{{ $vence->copy()->setTimezone(config('app.timezone'))->format('H:i') }}</strong>.</p>

            <form method="POST" action="{{ route('portal.pedidos.pago.store', $pedido->id_pedido) }}" class="mt-4 space-y-4">
                @csrf
                <fieldset>
                    <legend class="text-sm font-medium">Medio de pago</legend>
                    @foreach ($metodos as $metodo)
                        <label class="mr-4 text-sm"><input type="radio" name="metodo" value="{{ $metodo }}" @checked($loop->first)> {{ ucfirst($metodo) }}</label>
                    @endforeach
                </fieldset>
                <div class="flex gap-3">
                    <button name="resultado" value="aprobado" class="rounded bg-emerald-700 px-4 py-2 text-white hover:bg-emerald-800">Simular pago aprobado</button>
                    <button name="resultado" value="rechazado" class="rounded border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50">Simular pago rechazado</button>
                </div>
            </form>
        @else
            <p class="mt-4 rounded border border-slate-200 bg-slate-50 px-4 py-2 text-sm" data-estado-pedido>
                Estado del pedido: <strong>{{ $pedido->estadoPedido->nombre }}</strong>.
                @if ($pedido->estadoPedido->nombre === \App\Models\EstadoPedido::PENDIENTE)
                    Venció el plazo para pagar; el pedido se cancelará y el stock se liberará.
                @endif
            </p>
        @endif
    </div>
@endsection
