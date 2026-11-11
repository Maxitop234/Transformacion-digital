@extends('layouts.crm')

@section('titulo', 'Usuarios')

@section('contenido')
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Usuarios</h1>
        <a href="{{ route('crm.admin.usuarios.create') }}" class="rounded bg-sky-700 px-3 py-1.5 text-sm text-white hover:bg-sky-800">Nuevo usuario del personal</a>
    </div>

    @include('crm.admin._estado')

    <form method="GET" class="mt-4 flex gap-2 text-sm">
        <label for="rol" class="sr-only">Rol</label>
        <select id="rol" name="rol" class="rounded border border-slate-300 px-2 py-1">
            <option value="">Todos los roles</option>
            @foreach ($roles as $rol)
                <option value="{{ $rol }}" @selected(request('rol') === $rol)>{{ str_replace('_', ' ', $rol) }}</option>
            @endforeach
        </select>
        <button class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-100">Filtrar</button>
    </form>

    <table class="mt-4 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left">
                <th class="py-2 pr-4">Correo</th>
                <th class="py-2 pr-4">Rol</th>
                <th class="py-2 pr-4">Nombre</th>
                <th class="py-2 pr-4">Cartera</th>
                <th class="py-2 pr-4">Último acceso</th>
                <th class="py-2 pr-4">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($usuarios as $usuario)
                <tr class="border-b border-slate-200">
                    <td class="py-2 pr-4"><a class="text-sky-700 hover:underline" href="{{ route('crm.admin.usuarios.edit', $usuario->id_usuario) }}">{{ $usuario->email }}</a></td>
                    <td class="py-2 pr-4">{{ str_replace('_', ' ', $usuario->nombreRol()) }}</td>
                    <td class="py-2 pr-4">{{ $usuario->vendedor?->nombre ?? $usuario->cliente?->nombre_razon_social ?? '—' }}</td>
                    <td class="py-2 pr-4">{{ $usuario->vendedor ? $usuario->vendedor->clientes_count.' clientes' : '—' }}</td>
                    <td class="py-2 pr-4">{{ $usuario->ultimo_acceso?->timezone('America/Santiago')->format('d-m-Y H:i') ?? 'Nunca' }}</td>
                    <td class="py-2 pr-4">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">{{ $usuarios->links() }}</div>
@endsection
