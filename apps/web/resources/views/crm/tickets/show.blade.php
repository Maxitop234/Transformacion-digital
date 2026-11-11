@extends('layouts.crm')

@section('titulo', 'Ticket '.$ticket->id_ticket)

@php($zona = config('app.timezone'))
@php($etiqueta = fn (string $valor) => str_replace('_', ' ', $valor))
@php($cerrado = $ticket->estado === \App\Models\Ticket::CERRADO)

@section('contenido')
    <a href="{{ route('crm.tickets') }}" class="text-sm text-sky-700 hover:underline">&larr; Tickets</a>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Ticket N.º {{ $ticket->id_ticket }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                <a href="{{ route('crm.clientes.show', $ticket->id_cliente) }}" class="text-sky-700 hover:underline">{{ $ticket->cliente->nombre_razon_social }}</a>
                @if ($ticket->pedido)
                    · <a href="{{ route('crm.pedidos.show', $ticket->id_pedido) }}" class="text-sky-700 hover:underline">pedido N.º {{ $ticket->id_pedido }}</a> ({{ $ticket->pedido->estadoPedido->nombre }})
                @endif
            </p>
        </div>
        <p class="rounded bg-slate-100 px-3 py-1 text-sm font-medium" data-estado-ticket>{{ $etiqueta($ticket->estado) }}</p>
    </div>

    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded border border-slate-200 bg-white p-4 text-sm lg:col-span-2">
            <h2 class="font-medium">{{ $ticket->asunto }}</h2>
            <p class="mt-1 text-xs text-slate-500">Creado el {{ $ticket->creado_en->setTimezone($zona)->format('d-m-Y H:i') }}@if ($ticket->cerrado_en) · cerrado el {{ $ticket->cerrado_en->setTimezone($zona)->format('d-m-Y H:i') }}@endif</p>
            <p class="mt-3 whitespace-pre-line">{{ $ticket->descripcion }}</p>
            <dl class="mt-4 grid grid-cols-2 gap-y-1 sm:grid-cols-4">
                <dt class="text-slate-500">Categoría</dt><dd data-categoria>{{ $etiqueta($ticket->categoria) }}{{ $ticket->clasificado_por_ia ? ' (IA)' : '' }}</dd>
                <dt class="text-slate-500">Prioridad</dt><dd>{{ $ticket->prioridad }}</dd>
                <dt class="text-slate-500">Asignado a</dt><dd>{{ $ticket->vendedor?->nombre ?? 'Sin asignar' }}</dd>
            </dl>
        </section>

        <section class="rounded border border-slate-200 bg-white p-4 text-sm" data-bloque="gestion">
            <h2 class="font-medium">Gestión</h2>
            @if ($cerrado)
                <p class="mt-2 text-slate-600">El ticket está cerrado.</p>
            @else
                <form method="POST" action="{{ route('crm.tickets.update', $ticket->id_ticket) }}" class="mt-2 space-y-3">
                    @csrf
                    @method('PATCH')
                    <label class="grid gap-1">Categoría
                        <select name="categoria" class="rounded border border-slate-300 px-2 py-1">
                            @if ($ticket->categoria === \App\Models\Ticket::SIN_CLASIFICAR)<option value="">Sin clasificar</option>@endif
                            @foreach (\App\Models\Ticket::CATEGORIAS as $valor)
                                <option value="{{ $valor }}" @selected($ticket->categoria === $valor)>{{ $etiqueta($valor) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1">Prioridad
                        <select name="prioridad" class="rounded border border-slate-300 px-2 py-1">
                            @foreach (\App\Models\Ticket::PRIORIDADES as $valor)
                                <option value="{{ $valor }}" @selected($ticket->prioridad === $valor)>{{ $valor }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1">Estado
                        <select name="estado" class="rounded border border-slate-300 px-2 py-1">
                            @foreach (\App\Models\Ticket::ESTADOS as $valor)
                                <option value="{{ $valor }}" @selected($ticket->estado === $valor)>{{ $etiqueta($valor) }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if (auth()->user()->esAdministrador())
                        <label class="grid gap-1">Asignar a
                            <select name="id_vendedor" class="rounded border border-slate-300 px-2 py-1">
                                <option value="">— Sin cambio —</option>
                                @foreach ($vendedores as $vendedor)
                                    <option value="{{ $vendedor->id_vendedor }}" @selected($ticket->id_vendedor === $vendedor->id_vendedor)>{{ $vendedor->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                    @elseif ($ticket->id_vendedor !== auth()->user()->vendedor?->id_vendedor)
                        <label class="flex items-center gap-2"><input type="checkbox" name="id_vendedor" value="{{ auth()->user()->vendedor?->id_vendedor }}"> Tomar el ticket</label>
                    @endif
                    <button class="w-full rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">Guardar</button>
                </form>
            @endif
        </section>
    </div>
@endsection
