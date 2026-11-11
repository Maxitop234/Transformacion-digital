@extends('layouts.portal')

@section('titulo', 'Pedido '.$pedido->id_pedido)

@php($zona = config('app.timezone'))
@php($clp = fn ($monto) => \App\Domain\Catalogo\Precios::clp($monto))
@php($direccion = $pedido->direccionDespacho)
@php($entregas = ['pendiente' => 'Pendiente', 'entregado' => 'Entregado', 'fallido' => 'Entrega fallida (se reprograma)', 'rechazado_temperatura' => 'Rechazado por temperatura'])
@php($viajes = ['programado' => 'Programado', 'en_ruta' => 'En ruta', 'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado'])

@section('contenido')
    <a href="{{ route('portal.mis-pedidos') }}" class="text-sm text-sky-700 hover:underline">&larr; Mis pedidos</a>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Pedido N.º {{ $pedido->id_pedido }}</h1>
            <p class="mt-1 text-sm text-slate-600">Realizado el {{ $pedido->fecha_pedido->setTimezone($zona)->format('d-m-Y H:i') }}</p>
        </div>
        <p class="rounded bg-slate-100 px-3 py-1 text-sm font-medium" data-estado-pedido>{{ $pedido->estadoPedido->nombre }}</p>
    </div>

    <dl class="mt-4 grid max-w-3xl grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
        <dt class="text-slate-500">Punto de entrega</dt><dd>{{ $direccion->alias }} · {{ $direccion->calle }}, {{ $direccion->comuna->nombre }}</dd>
        <dt class="text-slate-500">Entrega solicitada</dt><dd>{{ $pedido->fecha_entrega_solicitada?->format('d-m-Y') ?? '—' }}</dd>
        <dt class="text-slate-500">Ventana de recepción</dt><dd>{{ $direccion->ventana_inicio ? substr($direccion->ventana_inicio, 0, 5).' a '.substr($direccion->ventana_fin, 0, 5) : '—' }}</dd>
        @if ($pedido->observaciones)
            <dt class="text-slate-500">Observaciones</dt><dd>{{ $pedido->observaciones }}</dd>
        @endif
    </dl>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded border border-slate-200 p-4 text-sm" data-bloque="productos">
                <h2 class="font-medium">Productos</h2>
                <table class="mt-2 w-full">
                    <thead class="text-left text-xs text-slate-500"><tr><th class="py-1">Producto</th><th class="text-right">Cantidad</th><th class="text-right">Precio neto pactado</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody>
                        @foreach ($pedido->detallesPedido as $detalle)
                            <tr class="border-t border-slate-100">
                                <td class="py-1">{{ $detalle->producto->nombre }}</td>
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

            <section class="rounded border border-slate-200 p-4 text-sm" data-bloque="despacho">
                <h2 class="font-medium">Despacho y cadena de frío</h2>
                @forelse ($despachos as $parada)
                    <div class="mt-3 border-t border-slate-100 pt-3 first-of-type:border-0" data-parada>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-4">
                            <dt class="text-slate-500">Camión</dt><dd data-camion>{{ $parada['camion'] }} ({{ $parada['patente'] }})</dd>
                            <dt class="text-slate-500">Parada</dt><dd>N.º {{ $parada['orden_parada'] }}</dd>
                            <dt class="text-slate-500">Viaje</dt><dd>{{ $viajes[$parada['estado_despacho']] ?? $parada['estado_despacho'] }}{{ $parada['fecha_programada'] ? ' · '.$parada['fecha_programada']->format('d-m-Y') : '' }}</dd>
                            <dt class="text-slate-500">Entrega</dt><dd>{{ $entregas[$parada['estado_entrega']] ?? $parada['estado_entrega'] }}{{ $parada['fecha_entrega'] ? ' · '.$parada['fecha_entrega']->setTimezone($zona)->format('d-m-Y H:i') : '' }}</dd>
                            @if ($parada['salida'])
                                <dt class="text-slate-500">Salida de bodega</dt><dd>{{ $parada['salida']->setTimezone($zona)->format('d-m-Y H:i') }}</dd>
                            @endif
                        </dl>
                        @if ($parada['rupturas']->isEmpty())
                            <p class="mt-2 rounded border border-emerald-200 bg-emerald-50 px-3 py-1 text-emerald-900" data-frio="ok">
                                {{ $parada['salida'] ? 'Sin rupturas de frío registradas en el trayecto.' : 'El camión aún no sale de bodega.' }}
                            </p>
                        @else
                            <div class="mt-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-red-900" data-frio="ruptura">
                                <p>Se registraron {{ $parada['rupturas']->count() }} ruptura(s) de frío durante el trayecto:</p>
                                <ul class="mt-1 list-disc pl-5">
                                    @foreach ($parada['rupturas'] as $ruptura)
                                        <li>{{ \Carbon\CarbonImmutable::parse($ruptura->fecha_hora)->setTimezone($zona)->format('d-m-Y H:i') }} · {{ number_format((float) $ruptura->temp_camara, 1, ',', '.') }} °C{{ $ruptura->estado === 'resuelta' ? ' · gestionada por Cofrial' : '' }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="mt-2 text-slate-600" data-sin-despacho>Tu pedido aún no está asignado a un camión.</p>
                @endforelse
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded border border-slate-200 p-4 text-sm" data-bloque="linea-de-tiempo">
                <h2 class="font-medium">Línea de tiempo</h2>
                <ol class="mt-2 space-y-2 border-l border-slate-200 pl-4">
                    @foreach ($lineaDeTiempo as $paso)
                        <li data-paso="{{ $paso['estado'] }}">
                            <p class="font-medium">{{ $paso['estado'] }}</p>
                            <p class="text-xs text-slate-500">{{ $paso['fecha_hora']->setTimezone($zona)->format('d-m-Y H:i') }}</p>
                            @if ($paso['comentario'])<p class="text-slate-600">{{ $paso['comentario'] }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="space-y-3 rounded border border-slate-200 p-4 text-sm" data-bloque="acciones">
                <h2 class="font-medium">Acciones</h2>

                @if ($puedePagar)
                    <a href="{{ route('portal.pedidos.pago', $pedido->id_pedido) }}" class="block rounded bg-emerald-700 px-4 py-2 text-center text-white hover:bg-emerald-800">Ir a pagar</a>
                @endif

                @if ($perfil->esB2B)
                    <form method="POST" action="{{ route('portal.mis-pedidos.repetir', $pedido->id_pedido) }}">
                        @csrf
                        <button class="w-full rounded border border-sky-300 px-4 py-2 text-sky-800 hover:bg-sky-50" data-repetir>Repetir pedido</button>
                        <p class="mt-1 text-xs text-slate-500">Copia los productos al carrito con los precios vigentes de tu lista.</p>
                    </form>
                @endif

                @if ($puedeCancelar)
                    <form method="POST" action="{{ route('portal.mis-pedidos.cancelar', $pedido->id_pedido) }}" data-cancelar>
                        @csrf
                        <label for="motivo" class="block text-slate-600">Motivo de la cancelación</label>
                        <textarea id="motivo" name="motivo" rows="2" maxlength="250" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1">{{ old('motivo') }}</textarea>
                        @error('motivo')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                        <button class="mt-2 w-full rounded border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50">Cancelar pedido</button>
                    </form>
                @endif

            </section>

            <section class="rounded border border-slate-200 p-4 text-sm" data-bloque="tickets">
                <h2 class="font-medium">Reportar un problema</h2>
                @if ($tickets->isNotEmpty())
                    <ul class="mt-2 space-y-1">
                        @foreach ($tickets as $ticket)
                            <li data-ticket="{{ $ticket->id_ticket }}">N.º {{ $ticket->id_ticket }} · {{ $ticket->asunto }} <span class="text-xs text-slate-500">· {{ str_replace('_', ' ', $ticket->estado) }}</span></li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('portal.tickets.store') }}" class="mt-3 space-y-2" data-reportar-problema>
                    @csrf
                    <input type="hidden" name="id_pedido" value="{{ $pedido->id_pedido }}">
                    <label for="asunto" class="block text-slate-600">Asunto</label>
                    <input id="asunto" name="asunto" value="{{ old('asunto') }}" maxlength="150" required class="w-full rounded border border-slate-300 px-2 py-1">
                    @error('asunto')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    <label for="descripcion" class="block text-slate-600">¿Qué pasó?</label>
                    <textarea id="descripcion" name="descripcion" rows="3" maxlength="2000" required class="w-full rounded border border-slate-300 px-2 py-1">{{ old('descripcion') }}</textarea>
                    @error('descripcion')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    <button class="w-full rounded border border-slate-300 px-4 py-2 hover:bg-slate-50">Reportar un problema</button>
                </form>
            </section>
        </div>
    </div>
@endsection
