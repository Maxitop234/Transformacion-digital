@extends('layouts.portal')

@section('titulo', 'Mis direcciones')

@section('contenido')
    <h1 class="text-2xl font-semibold">Mis puntos de entrega</h1>
    <p class="mt-1 text-sm text-slate-600">
        {{ $perfil->esB2B ? 'Solo los puntos activos se ofrecen en el checkout. Cada uno tiene su ventana de recepción.' : 'Dirección donde recibes tus pedidos.' }}
    </p>

    <ul class="mt-6 divide-y divide-slate-100 rounded border border-slate-200 text-sm">
        @forelse ($direcciones as $direccion)
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" data-direccion="{{ $direccion->id_direccion }}" data-activa="{{ $direccion->activa ? '1' : '0' }}">
                <div>
                    <p class="font-medium">{{ $direccion->alias }} @unless ($direccion->activa)<span class="ml-1 rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Inactivo</span>@endunless</p>
                    <p class="text-slate-600">{{ $direccion->calle }}, {{ $direccion->comuna->nombre }}{{ $direccion->referencia ? ' · '.$direccion->referencia : '' }}</p>
                    <p class="text-xs text-slate-500">Ventana: {{ $direccion->ventana_inicio ? substr($direccion->ventana_inicio, 0, 5).' a '.substr($direccion->ventana_fin, 0, 5) : 'sin ventana' }}</p>
                </div>
                @if ($perfil->esB2B)
                    <form method="POST" action="{{ route('portal.direcciones.update', $direccion->id_direccion) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="activa" value="{{ $direccion->activa ? '0' : '1' }}">
                        <button class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-50">{{ $direccion->activa ? 'Desactivar' : 'Activar' }}</button>
                    </form>
                @endif
            </li>
        @empty
            <li class="px-4 py-3 text-slate-600">No tienes puntos de entrega registrados.</li>
        @endforelse
    </ul>

    @if ($perfil->esB2B)
        <form method="POST" action="{{ route('portal.direcciones.store') }}" class="mt-8 max-w-xl space-y-3 text-sm" data-nueva-direccion>
            @csrf
            <h2 class="text-lg font-medium">Agregar punto de entrega</h2>
            <div>
                <label for="alias" class="block text-slate-600">Nombre (ej.: «Casino», «Bodega central»)</label>
                <input id="alias" name="alias" value="{{ old('alias') }}" maxlength="60" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
                @error('alias')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="calle" class="block text-slate-600">Calle y número</label>
                <input id="calle" name="calle" value="{{ old('calle') }}" maxlength="150" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
                @error('calle')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="id_comuna" class="block text-slate-600">Comuna</label>
                <select id="id_comuna" name="id_comuna" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
                    <option value="">Elige una comuna</option>
                    @foreach ($comunas as $comuna)
                        <option value="{{ $comuna->id_comuna }}" @selected(old('id_comuna') == $comuna->id_comuna)>{{ $comuna->nombre }}</option>
                    @endforeach
                </select>
                @error('id_comuna')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="referencia" class="block text-slate-600">Referencia (opcional)</label>
                <input id="referencia" name="referencia" value="{{ old('referencia') }}" maxlength="200" class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="ventana_inicio" class="block text-slate-600">Recibe desde</label>
                    <input id="ventana_inicio" type="time" name="ventana_inicio" value="{{ old('ventana_inicio') }}" class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
                    @error('ventana_inicio')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="ventana_fin" class="block text-slate-600">Hasta</label>
                    <input id="ventana_fin" type="time" name="ventana_fin" value="{{ old('ventana_fin') }}" class="mt-1 w-full rounded border border-slate-300 px-2 py-1">
                    @error('ventana_fin')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
            <button class="rounded bg-sky-700 px-4 py-2 text-white hover:bg-sky-800">Agregar</button>
        </form>
    @endif
@endsection
