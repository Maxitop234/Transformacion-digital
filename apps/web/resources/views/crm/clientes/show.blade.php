@extends('layouts.crm')

@section('titulo', $cliente->nombre_razon_social)

@php($clp = fn ($monto) => \App\Domain\Catalogo\Precios::clp($monto))
@php($zona = config('app.timezone'))

@section('contenido')
    <a href="{{ route('crm.clientes.index') }}" class="text-sm text-sky-700 hover:underline">&larr; Clientes</a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $cliente->nombre_razon_social }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $cliente->tipoCliente->nombre }} ({{ $cliente->tipoCliente->codigo }})
                · <span data-estado-cliente="{{ $cliente->estado_cliente }}">{{ str_replace('_', ' ', $cliente->estado_cliente) }}</span>
            </p>
        </div>
        @if ($cliente->estado_cliente === 'activo')
            <a href="{{ route('crm.clientes.pedidos.create', $cliente->id_cliente) }}" class="rounded bg-sky-700 px-3 py-1.5 text-sm text-white hover:bg-sky-800" data-pedido-telefonico>Pedido telefónico</a>
        @endif
    </div>

    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    <dl class="grid max-w-3xl grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
        <dt class="text-slate-500">RUT</dt><dd>{{ $cliente->rut ? \App\Domain\Clientes\Rut::desdeTexto($cliente->rut)->formateado() : '—' }}</dd>
        <dt class="text-slate-500">Lista de precios</dt><dd data-lista>{{ $cliente->listaPrecio->nombre }}</dd>
        <dt class="text-slate-500">Vendedor</dt><dd>{{ $cliente->vendedor?->nombre ?? 'Sin asignar' }}</dd>
        <dt class="text-slate-500">Cuenta del portal</dt><dd>{{ $cliente->id_usuario ? 'Sí' : 'No (prospecto)' }}</dd>
        <dt class="text-slate-500">Registro</dt><dd>{{ $cliente->fecha_registro?->setTimezone($zona)->format('d-m-Y') ?? '—' }}</dd>
        <dt class="text-slate-500">Validación</dt><dd>{{ $cliente->fecha_validacion?->setTimezone($zona)->format('d-m-Y H:i') ?? '—' }}</dd>
    </dl>

    @if ($cliente->estado_cliente === 'pendiente_validacion' && $cliente->tipoCliente->codigo === 'B2B')
        <section class="mt-6 max-w-3xl rounded border border-amber-200 bg-amber-50 p-4 text-sm" data-bloque="validar">
            <h2 class="font-medium">Validar empresa (RN-02)</h2>
            <p class="mt-1 text-amber-900">Asigna su lista institucional y su vendedor. Desde ese momento ve sus precios y puede comprar.</p>
            <form method="POST" action="{{ route('crm.clientes.validar', $cliente->id_cliente) }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <label class="grid gap-1">Lista de precios
                    <select name="id_lista_precio" required class="rounded border border-slate-300 bg-white px-2 py-1">
                        @foreach ($listas as $lista)
                            <option value="{{ $lista->id_lista_precio }}">{{ $lista->nombre }}</option>
                        @endforeach
                    </select>
                </label>
                @if (auth()->user()->esAdministrador())
                    <label class="grid gap-1">Vendedor
                        <select name="id_vendedor" class="rounded border border-slate-300 bg-white px-2 py-1">
                            @foreach ($vendedores as $vendedor)
                                <option value="{{ $vendedor->id_vendedor }}" @selected($vendedor->id_vendedor === $cliente->id_vendedor)>{{ $vendedor->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button class="rounded bg-amber-700 px-3 py-1.5 text-white hover:bg-amber-800">Validar empresa</button>
            </form>
        </section>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="contactos">
            <h2 class="font-medium">Contactos</h2>
            <ul class="mt-2 divide-y divide-slate-100">
                @forelse ($cliente->contactosCliente as $contacto)
                    <li class="py-2">
                        <strong>{{ $contacto->nombre }}</strong>@if ($contacto->es_principal) <span class="text-xs text-sky-700">principal</span>@endif
                        <span class="block text-xs text-slate-500">{{ $contacto->cargo ?? 'Sin cargo' }} · {{ $contacto->email }}{{ $contacto->telefono ? ' · '.$contacto->telefono : '' }}</span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Sin contactos.</li>
                @endforelse
            </ul>
            <details class="mt-2">
                <summary class="cursor-pointer text-sky-700">Agregar contacto</summary>
                <form method="POST" action="{{ route('crm.clientes.contactos.store', $cliente->id_cliente) }}" class="mt-2 grid gap-2 sm:grid-cols-2">
                    @csrf
                    <input name="nombre" required maxlength="100" placeholder="Nombre" class="rounded border border-slate-300 px-2 py-1">
                    <input name="cargo" maxlength="80" placeholder="Cargo" class="rounded border border-slate-300 px-2 py-1">
                    <input type="email" name="email" required maxlength="120" placeholder="Correo" class="rounded border border-slate-300 px-2 py-1">
                    <input name="telefono" maxlength="20" placeholder="Teléfono" class="rounded border border-slate-300 px-2 py-1">
                    <label class="flex items-center gap-2"><input type="checkbox" name="es_principal" value="1"> Principal</label>
                    <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Guardar contacto</button>
                </form>
            </details>
        </section>

        <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="direcciones">
            <h2 class="font-medium">Puntos de entrega</h2>
            <ul class="mt-2 divide-y divide-slate-100">
                @forelse ($cliente->direccionesDespacho as $direccion)
                    <li @class(['py-2', 'text-slate-400' => ! $direccion->activa])>
                        <strong>{{ $direccion->alias }}</strong> · {{ $direccion->calle }}, {{ $direccion->comuna->nombre }}
                        <span class="block text-xs text-slate-500">
                            Ventana {{ $direccion->ventana_inicio ? substr($direccion->ventana_inicio, 0, 5).' a '.substr($direccion->ventana_fin, 0, 5) : 'sin definir' }}{{ $direccion->activa ? '' : ' · inactivo' }}
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Sin puntos de entrega.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section class="mt-6 rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="interacciones">
        <h2 class="font-medium">Interacciones</h2>
        <form method="POST" action="{{ route('crm.clientes.interacciones.store', $cliente->id_cliente) }}" class="mt-2 grid gap-2 sm:grid-cols-[auto_auto_1fr_auto]">
            @csrf
            <select name="tipo" required class="rounded border border-slate-300 px-2 py-1" aria-label="Tipo de interacción">
                @foreach ($tiposInteraccion as $tipo)
                    <option value="{{ $tipo }}">{{ ucfirst($tipo === 'reunion' ? 'reunión' : $tipo) }}</option>
                @endforeach
            </select>
            <select name="id_oportunidad" class="rounded border border-slate-300 px-2 py-1" aria-label="Oportunidad">
                <option value="">Sin oportunidad</option>
                @foreach ($cliente->oportunidades->filter(fn ($o) => ! $o->etapaPipeline->es_cierre) as $oportunidad)
                    <option value="{{ $oportunidad->id_oportunidad }}">{{ $oportunidad->titulo }} ({{ $oportunidad->etapaPipeline->nombre }})</option>
                @endforeach
            </select>
            <input name="resumen" required maxlength="2000" placeholder="Resumen de lo conversado" class="rounded border border-slate-300 px-2 py-1" aria-label="Resumen">
            <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Registrar</button>
        </form>
        <ul class="mt-3 divide-y divide-slate-100">
            @forelse ($cliente->interacciones as $interaccion)
                <li class="py-2" data-interaccion="{{ $interaccion->id_interaccion }}">
                    <p class="text-xs text-slate-500">
                        {{ $interaccion->fecha_hora->setTimezone($zona)->format('d-m-Y H:i') }} · {{ $interaccion->tipo }} · {{ $interaccion->vendedor->nombre }}
                        @if ($interaccion->oportunidad) · {{ $interaccion->oportunidad->titulo }} @endif
                    </p>
                    <p>{{ $interaccion->resumen }}</p>
                </li>
            @empty
                <li class="py-2 text-slate-500">Sin interacciones registradas.</li>
            @endforelse
        </ul>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="pedidos">
            <h2 class="font-medium">Pedidos</h2>
            <table class="mt-2 w-full">
                <thead class="text-left text-xs text-slate-500">
                    <tr><th class="py-1">N.º</th><th>Fecha</th><th>Canal</th><th>Estado</th><th class="text-right">Total</th></tr>
                </thead>
                <tbody>
                    @forelse ($pedidos as $pedido)
                        <tr class="border-t border-slate-100" data-pedido="{{ $pedido->id_pedido }}" data-canal="{{ $pedido->canal }}">
                            <td class="py-1">{{ $pedido->id_pedido }}</td>
                            <td>{{ $pedido->fecha_pedido->setTimezone($zona)->format('d-m-Y') }}</td>
                            <td>{{ $pedido->canal === 'crm' ? 'CRM'.($pedido->vendedorRegistra ? ' ('.$pedido->vendedorRegistra->nombre.')' : '') : 'Portal' }}</td>
                            <td>{{ $pedido->estadoPedido->nombre }}</td>
                            <td class="text-right">{{ $clp($pedido->totales['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-2 text-slate-500">Sin pedidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="tickets">
            <h2 class="font-medium">Tickets</h2>
            <ul class="mt-2 divide-y divide-slate-100">
                @forelse ($cliente->tickets as $ticket)
                    <li class="py-2">
                        <a href="{{ route('crm.tickets.show', $ticket->id_ticket) }}" class="font-semibold text-sky-700 hover:underline" data-ticket="{{ $ticket->id_ticket }}">{{ $ticket->asunto }}</a>
                        <span class="block text-xs text-slate-500">{{ str_replace('_', ' ', $ticket->categoria) }} · prioridad {{ $ticket->prioridad }} · {{ str_replace('_', ' ', $ticket->estado) }}</span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Sin tickets.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section class="mt-6 rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="oportunidades">
        <h2 class="font-medium">Oportunidades</h2>
        <ul class="mt-2 divide-y divide-slate-100">
            @forelse ($cliente->oportunidades as $oportunidad)
                <li class="flex justify-between gap-2 py-2">
                    @can('oportunidades')
                        <a href="{{ route('crm.oportunidades.show', $oportunidad->id_oportunidad) }}" class="text-sky-700 hover:underline">{{ $oportunidad->titulo }}</a>
                    @else
                        <span>{{ $oportunidad->titulo }}</span>
                    @endcan
                    <span class="text-slate-500">{{ $oportunidad->etapaPipeline->nombre }} · {{ $oportunidad->fecha_creacion->setTimezone($zona)->format('d-m-Y') }}</span>
                </li>
            @empty
                <li class="py-2 text-slate-500">Sin oportunidades.</li>
            @endforelse
        </ul>
    </section>
@endsection
