@extends('layouts.portal')

@section('titulo', 'Ayuda')

@php($zona = config('app.timezone'))

@section('contenido')
    <h1 class="text-2xl font-semibold">Reportar un problema</h1>
    <p class="mt-1 text-sm text-slate-600">Cuéntanos qué pasó y un ejecutivo de Cofrial lo revisará.</p>

    <div class="mt-6 grid gap-8 lg:grid-cols-2">
        <form method="POST" action="{{ route('portal.tickets.store') }}" class="space-y-3 text-sm" data-nuevo-ticket>
            @csrf
            <div>
                <label for="id_pedido" class="block text-slate-600">Pedido (opcional)</label>
                <select id="id_pedido" name="id_pedido" class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
                    <option value="">No es sobre un pedido</option>
                    @foreach ($pedidos as $id)
                        <option value="{{ $id }}" @selected((int) old('id_pedido', $idPedido) === $id)>Pedido N.º {{ $id }}</option>
                    @endforeach
                </select>
                @error('id_pedido')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="asunto" class="block text-slate-600">Asunto</label>
                <input id="asunto" name="asunto" value="{{ old('asunto') }}" maxlength="150" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
                @error('asunto')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="descripcion" class="block text-slate-600">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="5" maxlength="2000" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1">{{ old('descripcion') }}</textarea>
                @error('descripcion')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <button class="rounded bg-sky-700 px-4 py-2 text-white hover:bg-sky-800">Enviar</button>
        </form>

        <section class="text-sm">
            <h2 class="text-lg font-medium">Mis reportes</h2>
            <ul class="mt-2 divide-y divide-slate-100 rounded border border-slate-200">
                @forelse ($tickets as $ticket)
                    <li class="px-4 py-2" data-ticket="{{ $ticket->id_ticket }}">
                        <p class="font-medium">N.º {{ $ticket->id_ticket }} · {{ $ticket->asunto }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $ticket->creado_en->setTimezone($zona)->format('d-m-Y H:i') }}
                            @if ($ticket->id_pedido) · pedido N.º {{ $ticket->id_pedido }} @endif
                            · {{ str_replace('_', ' ', $ticket->estado) }}
                        </p>
                    </li>
                @empty
                    <li class="px-4 py-2 text-slate-600">No has reportado problemas.</li>
                @endforelse
            </ul>
            <div class="mt-3">{{ $tickets->links() }}</div>
        </section>
    </div>
@endsection
