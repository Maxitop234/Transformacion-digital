@extends('layouts.portal')

@php($empresa = $tipo === 'empresa')

@section('titulo', $empresa ? 'Registrar empresa' : 'Crear cuenta')

@php($campo = 'mt-1 w-full rounded border border-slate-300 px-3 py-2')

@section('contenido')
    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-semibold">{{ $empresa ? 'Registrar tu empresa' : 'Crear tu cuenta' }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            @if ($empresa)
                Para instituciones y empresas: compras con orden de compra y crédito a 30 días. Un ejecutivo validará tu
                empresa (normalmente en un día hábil); mientras tanto puedes ver el catálogo.
                <a href="{{ route('registro.persona') }}" class="text-sky-700 hover:underline">¿Compras como persona?</a>
            @else
                Para compras personales con pago en línea. Tu cuenta queda activa de inmediato.
                <a href="{{ route('registro.empresa') }}" class="text-sky-700 hover:underline">¿Compras para una empresa?</a>
            @endif
        </p>

        @if ($errors->any())
            <p class="mt-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-900" role="alert" data-errores>Revisa los campos marcados.</p>
        @endif

        <form method="POST" action="{{ route($empresa ? 'registro.empresa.store' : 'registro.persona.store') }}" class="mt-6 space-y-6 text-sm" novalidate data-registro="{{ $tipo }}">
            @csrf

            <fieldset class="space-y-3">
                <legend class="text-base font-medium">{{ $empresa ? 'Empresa' : 'Tus datos' }}</legend>
                @if ($empresa)
                    <div>
                        <label for="rut" class="block font-medium">RUT de la empresa</label>
                        <input id="rut" name="rut" value="{{ old('rut') }}" maxlength="12" required placeholder="76.123.456-7" class="{{ $campo }}">
                        @error('rut')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="razon_social" class="block font-medium">Razón social</label>
                        <input id="razon_social" name="razon_social" value="{{ old('razon_social') }}" maxlength="150" required class="{{ $campo }}">
                        @error('razon_social')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="contacto_nombre" class="block font-medium">Contacto principal</label>
                            <input id="contacto_nombre" name="contacto_nombre" value="{{ old('contacto_nombre') }}" maxlength="100" required class="{{ $campo }}">
                            @error('contacto_nombre')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="contacto_cargo" class="block font-medium">Cargo (opcional)</label>
                            <input id="contacto_cargo" name="contacto_cargo" value="{{ old('contacto_cargo') }}" maxlength="80" class="{{ $campo }}">
                        </div>
                    </div>
                    <div>
                        <label for="contacto_telefono" class="block font-medium">Teléfono (opcional)</label>
                        <input id="contacto_telefono" name="contacto_telefono" value="{{ old('contacto_telefono') }}" maxlength="20" class="{{ $campo }}">
                        @error('contacto_telefono')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                @else
                    <div>
                        <label for="nombre" class="block font-medium">Nombre</label>
                        <input id="nombre" name="nombre" value="{{ old('nombre') }}" maxlength="150" required autocomplete="name" class="{{ $campo }}">
                        @error('nombre')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                @endif
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-base font-medium">Cuenta de acceso</legend>
                <div>
                    <label for="email" class="block font-medium">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="120" required autocomplete="email" class="{{ $campo }}">
                    @error('email')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block font-medium">Contraseña</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="{{ $campo }}">
                        <p class="mt-1 text-xs text-slate-500">Al menos 8 caracteres, con letras y números.</p>
                        @error('password')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="block font-medium">Repite la contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $campo }}">
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-base font-medium">{{ $empresa ? 'Punto de entrega' : 'Dirección de despacho' }}</legend>
                @if ($empresa)
                    <div>
                        <label for="alias" class="block font-medium">Nombre del punto (opcional)</label>
                        <input id="alias" name="alias" value="{{ old('alias') }}" maxlength="60" placeholder="Bodega principal" class="{{ $campo }}">
                    </div>
                @endif
                <div>
                    <label for="calle" class="block font-medium">Calle y número</label>
                    <input id="calle" name="calle" value="{{ old('calle') }}" maxlength="150" required autocomplete="street-address" class="{{ $campo }}">
                    @error('calle')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="id_comuna" class="block font-medium">Comuna</label>
                        <select id="id_comuna" name="id_comuna" required class="{{ $campo }}">
                            <option value="">Elige una comuna</option>
                            @foreach ($comunas as $comuna)
                                <option value="{{ $comuna->id_comuna }}" @selected(old('id_comuna') == $comuna->id_comuna)>{{ $comuna->nombre }}</option>
                            @endforeach
                        </select>
                        @error('id_comuna')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="referencia" class="block font-medium">Referencia (opcional)</label>
                        <input id="referencia" name="referencia" value="{{ old('referencia') }}" maxlength="200" class="{{ $campo }}">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="ventana_inicio" class="block font-medium">Recibe desde{{ $empresa ? '' : ' (opcional)' }}</label>
                        <input id="ventana_inicio" name="ventana_inicio" type="time" value="{{ old('ventana_inicio') }}" @required($empresa) class="{{ $campo }}">
                        @error('ventana_inicio')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="ventana_fin" class="block font-medium">Hasta{{ $empresa ? '' : ' (opcional)' }}</label>
                        <input id="ventana_fin" name="ventana_fin" type="time" value="{{ old('ventana_fin') }}" @required($empresa) class="{{ $campo }}">
                        @error('ventana_fin')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <div>
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="consentimiento" value="1" @checked(old('consentimiento')) required class="mt-1">
                    <span>
                        Acepto que Cofrial SpA trate mis datos personales{{ $empresa ? ' y los del contacto indicado' : '' }} solo para
                        gestionar mi cuenta, mis pedidos y sus despachos, conforme a la Ley 21.719. Puedo pedir acceso, rectificación
                        o supresión de mis datos en cualquier momento.
                    </span>
                </label>
                @error('consentimiento')<p class="mt-1 text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>

            <button class="w-full rounded bg-sky-700 px-4 py-2 font-medium text-white hover:bg-sky-800">{{ $empresa ? 'Enviar solicitud' : 'Crear cuenta' }}</button>
        </form>

        <p class="mt-6 text-sm text-slate-600">¿Ya tienes cuenta? <a href="{{ route('login') }}" class="text-sky-700 hover:underline">Inicia sesión</a>.</p>
    </div>
@endsection
