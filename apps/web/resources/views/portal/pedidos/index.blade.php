@extends('layouts.portal')

@section('titulo', 'Mis pedidos')

@php($zona = config('app.timezone'))
@php($clp = fn ($monto) => \App\Domain\Catalogo\Precios::clp($monto))

@section('contenido')
    <h1 class="text-2xl font-semibold">Mis pedidos</h1>
    <p class="mt-1 text-sm text-slate-600">
        {{ $perfil->esB2B ? 'Pedidos de todos los puntos de entrega de tu empresa.' : 'Tus pedidos, del más reciente al más antiguo.' }}
    </p>

    @if ($pedidos->isEmpty())
        <p class="mt-6 rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm" data-sin-pedidos>
            Aún no tienes pedidos. <a href="{{ route('portal.catalogo') }}" class="text-sky-700 hover:underline">Ver el catálogo</a>.
        </p>
    @else
        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-500">
                    <tr>
                        <th class="py-2">N.º</th>
                        <th>Fecha</th>
                        @if ($perfil->esB2B)<th>Punto de entrega</th>@endif
                        <th>Entrega solicitada</th>
                        <th>Estado</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pedidos as $pedido)
                        <tr class="border-t border-slate-100" data-pedido="{{ $pedido->id_pedido }}">
                            <td class="py-2"><a href="{{ route('portal.mis-pedidos.show', $pedido->id_pedido) }}" class="text-sky-700 hover:underline">{{ $pedido->id_pedido }}</a></td>
                            <td>{{ $pedido->fecha_pedido->setTimezone($zona)->format('d-m-Y H:i') }}</td>
                            @if ($perfil->esB2B)<td>{{ $pedido->direccionDespacho->alias }} · {{ $pedido->direccionDespacho->comuna->nombre }}</td>@endif
                            <td>{{ $pedido->fecha_entrega_solicitada?->format('d-m-Y') ?? '—' }}</td>
                            <td><x-insignia :valor="$pedido->estadoPedido->nombre" /></td>
                            <td class="text-right">{{ $clp($pedido->totales['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $pedidos->links() }}</div>
    @endif
@endsection
