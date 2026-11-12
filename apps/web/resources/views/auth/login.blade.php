@extends('layouts.portal')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <div class="mx-auto mt-6 max-w-sm rounded border border-slate-200 bg-white p-6 sm:p-8">
        <h1 class="text-2xl font-semibold">Iniciar sesión</h1>
        <p class="mt-1 text-sm text-slate-600">Clientes y equipo de Cofrial usan la misma cuenta de acceso.</p>

        <form method="POST" action="{{ route('login.iniciar') }}" class="mt-6 space-y-4" novalidate>
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="mt-1 w-full rounded border border-slate-300 px-3 py-2" @error('email') aria-invalid="true" aria-describedby="error-email" @enderror>
                @error('email')
                    <p id="error-email" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                @error('password')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded bg-sky-700 px-4 py-2 font-medium text-white hover:bg-sky-800">Entrar</button>
        </form>

        <p class="mt-6 text-sm text-slate-600" data-enlaces-registro>
            ¿No tienes cuenta?
            <a href="{{ route('registro.persona') }}" class="text-sky-700 hover:underline">Crear cuenta personal</a>
            o <a href="{{ route('registro.empresa') }}" class="text-sky-700 hover:underline">registrar tu empresa</a>.
        </p>
    </div>
@endsection
