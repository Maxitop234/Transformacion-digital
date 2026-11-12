@extends('layouts.crm')

@section('titulo', 'Pedido '.$pedido->id_pedido)

@php($zona = config('app.timezone'))
@php($clp = fn ($monto) => \App\Domain\Catalogo\Precios::clp($monto))
@php($cliente = $pedido->direccionDespacho->cliente)

@section('contenido')
    <a href="{{ route('crm.pedidos') }}" class="text-sm text-sky-700 hover:underline">&larr; Pedidos</a>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Pedido N.º {{ $pedido->id_pedido }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                @can('clientes')
                    <a href="{{ route('crm.clientes.show', $cliente->id_cliente) }}" class="text-sky-700 hover:underline">{{ $cliente->nombre_razon_social }}</a>
                @else
                    {{ $cliente->nombre_razon_social }}
                @endcan
                ({{ $cliente->tipoCliente->codigo }}) · {{ $pedido->canal === 'crm' ? 'CRM'.($pedido->vendedorRegistra ? ', ingresado por '.$pedido->vendedorRegistra->nombre : '') : 'Portal' }}
            </p>
        </div>
        <x-insignia :valor="$pedido->estadoPedido->nombre" class="text-sm" data-estado-pedido />
    </div>

    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    <dl class="grid max-w-3xl grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
        <dt class="text-slate-500">Fecha del pedido</dt><dd>{{ $pedido->fecha_pedido->setTimezone($zona)->format('d-m-Y H:i') }}</dd>
        <dt class="text-slate-500">Entrega solicitada</dt><dd>{{ $pedido->fecha_entrega_solicitada?->format('d-m-Y') ?? '—' }}</dd>
        <dt class="text-slate-500">Punto de entrega</dt><dd>{{ $pedido->direccionDespacho->alias }} · {{ $pedido->direccionDespacho->comuna->nombre }}</dd>
        <dt class="text-slate-500">Ventana</dt><dd>{{ $pedido->direccionDespacho->ventana_inicio ? substr($pedido->direccionDespacho->ventana_inicio, 0, 5).' a '.substr($pedido->direccionDespacho->ventana_fin, 0, 5) : '—' }}</dd>
        @if ($pedido->oportunidad)
            <dt class="text-slate-500">Oportunidad</dt><dd>{{ $pedido->oportunidad->titulo }}</dd>
        @endif
        @if ($pedido->observaciones)
            <dt class="text-slate-500">Observaciones</dt><dd class="sm:col-span-3">{{ $pedido->observaciones }}</dd>
        @endif
    </dl>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="productos">
                <h2 class="font-medium">Productos (precio pactado, RN-01)</h2>
                <table class="mt-2 w-full">
                    <thead class="text-left text-xs text-slate-500"><tr><th class="py-1">Producto</th><th class="text-right">Cantidad</th><th class="text-right">Precio neto</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody>
                        @foreach ($pedido->detallesPedido as $detalle)
                            <tr class="border-t border-slate-100">
                                <td class="py-1">{{ $detalle->producto->sku }} · {{ $detalle->producto->nombre }}</td>
                                <td class="text-right">{{ \App\Domain\Ventas\Montos::legible(\App\Domain\Ventas\Montos::centesimas((string) $detalle->cantidad)) }} {{ $detalle->producto->unidad_medida }}</td>
                                <td class="text-right" data-precio-pactado="{{ $detalle->precio_unitario_pactado }}">{{ $clp($detalle->precio_unitario_pactado) }}</td>
                                <td class="text-right">{{ $clp(\App\Domain\Ventas\Montos::subtotalNeto((string) $detalle->precio_unitario_pactado, (string) $detalle->cantidad)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <dl class="mt-2 ml-auto grid max-w-xs grid-cols-2 gap-y-1 border-t border-slate-200 pt-2">
                    <dt class="text-slate-600">Neto</dt><dd class="text-right">{{ $clp($totales['neto']) }}</dd>
                    <dt class="text-slate-600">IVA</dt><dd class="text-right">{{ $clp($totales['iva']) }}</dd>
                    <dt class="font-semibold">Total</dt><dd class="text-right font-semibold">{{ $clp($totales['total']) }}</dd>
                </dl>
            </section>

            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="historial">
                <h2 class="font-medium">Historial de estados (RN-09)</h2>
                <ol class="mt-2 space-y-2">
                    @foreach ($pedido->historialesEstadoPedido as $paso)
                        <li class="border-l-2 border-sky-300 pl-3" data-historial="{{ $paso->id_historial }}">
                            <p><strong>{{ $paso->estadoPedido->nombre }}</strong> · {{ $paso->fecha_hora->setTimezone($zona)->format('d-m-Y H:i') }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $paso->usuario?->vendedor?->nombre ?? $paso->usuario?->email ?? 'Sistema' }}{{ $paso->comentario ? ' · '.$paso->comentario : '' }}
                            </p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="acciones">
                <h2 class="font-medium">Avanzar el pedido</h2>
                @if ($puedeConfirmarOc)
                    <form method="POST" action="{{ route('crm.pedidos.confirmar-oc', $pedido->id_pedido) }}" class="mt-2 grid gap-2" data-confirmar-oc>
                        @csrf
                        <p class="text-slate-600">Orden de compra <strong>{{ $ocPendiente->referencia_externa }}</strong> por {{ $clp($ocPendiente->monto) }} (crédito a 30 días).</p>
                        <input name="comentario" maxlength="200" placeholder="Comentario (opcional)" class="rounded border border-slate-300 px-2 py-1">
                        <button class="rounded bg-emerald-700 px-3 py-1.5 text-white hover:bg-emerald-800">Validar orden de compra y confirmar</button>
                    </form>
                @endif
                @if ($destinos)
                    <form method="POST" action="{{ route('crm.pedidos.estado', $pedido->id_pedido) }}" class="mt-3 grid gap-2" data-cambio-estado>
                        @csrf
                        <label class="grid gap-1">Nuevo estado
                            <select name="estado" class="rounded border border-slate-300 px-2 py-1">
                                @foreach ($destinos as $destino)
                                    <option value="{{ $destino }}">{{ $destino }}{{ in_array($destino, $conComentario, true) ? ' (requiere motivo)' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1">Comentario
                            <textarea name="comentario" maxlength="300" rows="2" class="rounded border border-slate-300 px-2 py-1">{{ old('comentario') }}</textarea>
                        </label>
                        <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Cambiar estado</button>
                    </form>
                @elseif (! $puedeConfirmarOc)
                    <p class="mt-2 text-slate-500" data-sin-acciones>No hay cambios de estado disponibles para tu rol.</p>
                @endif
            </section>

            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="pagos">
                <h2 class="font-medium">Pago</h2>
                <ul class="mt-2 space-y-1">
                    @forelse ($pedido->pagos as $pago)
                        <li data-pago="{{ $pago->estado }}">{{ $pago->metodo === 'credito_30d' ? 'Crédito 30 días' : ucfirst($pago->metodo) }} · {{ $pago->estado }} · {{ $clp($pago->monto) }}
                            <span class="block text-xs text-slate-500">{{ $pago->referencia_externa }} · {{ $pago->fecha_hora->setTimezone($zona)->format('d-m-Y H:i') }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">Sin pagos registrados.</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="despacho">
                <h2 class="font-medium">Despacho</h2>
                <ul class="mt-2 space-y-1">
                    @forelse ($pedido->despachosPedido as $parada)
                        <li>Despacho {{ $parada->id_despacho }} · camión {{ $parada->despacho->camion_id }} · parada {{ $parada->orden_parada }} · {{ str_replace('_', ' ', $parada->estado_entrega) }}
                            @if ($parada->motivo_falla)<span class="block text-xs text-slate-500">{{ $parada->motivo_falla }}</span>@endif
                        </li>
                    @empty
                        <li class="text-slate-500">Aún sin despacho asignado.</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="tickets">
                <h2 class="font-medium">Tickets</h2>
                <ul class="mt-2 space-y-1">
                    @forelse ($pedido->tickets as $ticket)
                        <li data-ticket="{{ $ticket->id_ticket }}">
                            @can('tickets')
                                <a href="{{ route('crm.tickets.show', $ticket->id_ticket) }}" class="text-sky-700 hover:underline">{{ $ticket->asunto }}</a>
                            @else
                                {{ $ticket->asunto }}
                            @endcan
                            <span class="text-xs text-slate-500">· {{ str_replace('_', ' ', $ticket->categoria) }} · {{ str_replace('_', ' ', $ticket->estado) }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">Sin tickets.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection
