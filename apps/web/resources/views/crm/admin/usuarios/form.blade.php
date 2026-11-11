@extends('layouts.crm')

@php($nuevo = ! $usuario->exists)
@php($esPersonal = $nuevo || $usuario->esPersonal())

@section('titulo', $nuevo ? 'Nuevo usuario' : $usuario->email)

@section('contenido')
    <a href="{{ route('crm.admin.usuarios.index') }}" class="text-sm text-sky-700 hover:underline">← Usuarios</a>
    <h1 class="mt-2 text-2xl font-semibold">{{ $nuevo ? 'Nuevo usuario del personal' : $usuario->email }}</h1>

    @include('crm.admin._estado')

    <form method="POST" action="{{ $nuevo ? route('crm.admin.usuarios.store') : route('crm.admin.usuarios.update', $usuario->id_usuario) }}" class="mt-4 grid max-w-xl gap-3 text-sm">
        @csrf
        @unless ($nuevo) @method('PATCH') @endunless

        <label class="grid gap-1">Correo electrónico
            <input type="email" name="email" value="{{ old('email', $usuario->email) }}" required maxlength="120" class="rounded border border-slate-300 px-2 py-1">
        </label>

        @if ($esPersonal)
            <label class="grid gap-1">Rol
                <select name="rol" required class="rounded border border-slate-300 px-2 py-1">
                    @foreach ($roles as $rol)
                        <option value="{{ $rol }}" @selected(old('rol', $nuevo ? null : $usuario->nombreRol()) === $rol)>{{ $rol }}</option>
                    @endforeach
                </select>
            </label>
            <fieldset class="grid gap-3 rounded border border-slate-200 p-3">
                <legend class="px-1 text-slate-600">Datos de vendedor (obligatorio el nombre si el rol es vendedor)</legend>
                <label class="grid gap-1">Nombre
                    <input name="nombre" value="{{ old('nombre', $usuario->vendedor?->nombre) }}" maxlength="100" class="rounded border border-slate-300 px-2 py-1">
                </label>
                <label class="grid gap-1">Teléfono corporativo
                    <input name="telefono" value="{{ old('telefono', $usuario->vendedor?->telefono) }}" maxlength="20" class="rounded border border-slate-300 px-2 py-1">
                </label>
            </fieldset>
        @else
            <p class="text-slate-600">Cuenta de cliente ({{ str_replace('_', ' ', $usuario->nombreRol()) }}). Su rol depende del registro en el portal; aquí solo se activa o desactiva.</p>
        @endif

        <label class="grid gap-1">{{ $nuevo ? 'Contraseña' : 'Nueva contraseña (opcional)' }}
            <input type="password" name="password" @if ($nuevo) required @endif minlength="10" autocomplete="new-password" class="rounded border border-slate-300 px-2 py-1">
        </label>
        <label class="grid gap-1">Repite la contraseña
            <input type="password" name="password_confirmation" autocomplete="new-password" class="rounded border border-slate-300 px-2 py-1">
        </label>

        @unless ($nuevo)
            <label class="flex items-center gap-2">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $usuario->activo))>
                Activo (un usuario inactivo no puede iniciar sesión)
            </label>
        @endunless

        <div>
            <button class="rounded bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-800">{{ $nuevo ? 'Crear usuario' : 'Guardar cambios' }}</button>
        </div>
    </form>

    @if (! $nuevo && $usuario->vendedor && $usuario->nombreRol() === \App\Models\Rol::VENDEDOR)
        <h2 class="mt-8 text-lg font-semibold">Cartera</h2>
        <p class="mt-1 text-sm text-slate-600">{{ $usuario->vendedor->nombre }} tiene {{ $clientesEnCartera }} clientes asignados (RN-10).</p>

        @if ($clientesEnCartera > 0)
            <form method="POST" action="{{ route('crm.admin.usuarios.cartera', $usuario->id_usuario) }}" class="mt-3 flex items-end gap-3 text-sm">
                @csrf
                @method('PATCH')
                <label class="grid gap-1">Reasignar toda la cartera a
                    <select name="id_vendedor_destino" required class="w-64 rounded border border-slate-300 px-2 py-1">
                        <option value="">Elige un vendedor activo</option>
                        @foreach ($destinos as $destino)
                            <option value="{{ $destino->id_vendedor }}">{{ $destino->nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded border border-slate-300 px-3 py-1.5 hover:bg-slate-100">Reasignar</button>
            </form>
        @endif
    @endif
@endsection
