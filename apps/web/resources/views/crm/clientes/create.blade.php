@extends('layouts.crm')

@section('titulo', 'Nuevo prospecto')

@section('contenido')
    <a href="{{ route('crm.clientes.index') }}" class="text-sm text-sky-700 hover:underline">&larr; Clientes</a>
    <h1 class="mt-2 text-2xl font-semibold">Nuevo prospecto</h1>
    <p class="mt-1 text-sm text-slate-600">Queda en tu cartera con una oportunidad en «Prospecto nuevo». La cuenta del portal la crea el cliente al registrarse.</p>

    <div class="mt-4">@include('crm.clientes._mensajes')</div>

    <form method="POST" action="{{ route('crm.clientes.store') }}" class="grid max-w-2xl gap-4 text-sm">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1">Tipo
                <select name="tipo" required class="rounded border border-slate-300 px-2 py-1">
                    <option value="B2B" @selected(old('tipo', 'B2B') === 'B2B')>Empresa (B2B)</option>
                    <option value="B2C" @selected(old('tipo') === 'B2C')>Persona (B2C)</option>
                </select>
            </label>
            <label class="grid gap-1">RUT <span class="text-xs text-slate-500">(opcional; obligatorio para validar una empresa)</span>
                <input name="rut" value="{{ old('rut') }}" maxlength="12" placeholder="12.345.678-5" class="rounded border border-slate-300 px-2 py-1">
            </label>
        </div>
        <label class="grid gap-1">Nombre o razón social
            <input name="nombre_razon_social" value="{{ old('nombre_razon_social') }}" required maxlength="150" class="rounded border border-slate-300 px-2 py-1">
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-1">Origen
                <select name="origen" class="rounded border border-slate-300 px-2 py-1">
                    @foreach ($origenes as $origen)
                        <option value="{{ $origen }}" @selected(old('origen') === $origen)>{{ ucfirst($origen) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1">Título de la oportunidad
                <input name="titulo_oportunidad" value="{{ old('titulo_oportunidad', 'Primer abastecimiento') }}" required maxlength="150" class="rounded border border-slate-300 px-2 py-1">
            </label>
        </div>
        @if (auth()->user()->esAdministrador())
            <label class="grid gap-1">Vendedor
                <select name="id_vendedor" required class="rounded border border-slate-300 px-2 py-1">
                    <option value="">Elige un vendedor</option>
                    @foreach ($vendedores as $vendedor)
                        <option value="{{ $vendedor->id_vendedor }}" @selected(old('id_vendedor') == $vendedor->id_vendedor)>{{ $vendedor->nombre }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <fieldset class="grid gap-4 rounded border border-slate-200 p-3 sm:grid-cols-2">
            <legend class="px-1 font-medium">Contacto principal (opcional)</legend>
            <label class="grid gap-1">Nombre<input name="contacto_nombre" value="{{ old('contacto_nombre') }}" maxlength="100" class="rounded border border-slate-300 px-2 py-1"></label>
            <label class="grid gap-1">Cargo<input name="contacto_cargo" value="{{ old('contacto_cargo') }}" maxlength="80" class="rounded border border-slate-300 px-2 py-1"></label>
            <label class="grid gap-1">Correo<input type="email" name="contacto_email" value="{{ old('contacto_email') }}" maxlength="120" class="rounded border border-slate-300 px-2 py-1"></label>
            <label class="grid gap-1">Teléfono<input name="contacto_telefono" value="{{ old('contacto_telefono') }}" maxlength="20" class="rounded border border-slate-300 px-2 py-1"></label>
        </fieldset>
        <div><button class="rounded bg-sky-700 px-4 py-2 text-white hover:bg-sky-800">Ingresar prospecto</button></div>
    </form>
@endsection
