<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Portal') · {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="min-h-screen bg-white text-slate-900" data-superficie="portal">
    <header class="border-b border-slate-200">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('portal.inicio') }}" class="font-semibold">{{ config('app.name') }}</a>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('portal.catalogo') }}" class="text-sky-700 hover:underline">Catálogo</a>
                @auth
                    @can('portal.comprar')
                        <a href="{{ route('portal.carrito') }}" class="text-sky-700 hover:underline" data-enlace-carrito>Carrito</a>
                    @endcan
                    @can('crm')
                        <a href="{{ route('crm.inicio') }}" class="text-sky-700 hover:underline">CRM</a>
                    @endcan
                    <span class="text-slate-600">{{ auth()->user()->email }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sky-700 hover:underline">Cerrar sesión</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-sky-700 hover:underline">Iniciar sesión</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @include('portal._mensajes')
        @yield('contenido')
    </main>

    <footer class="border-t border-slate-200 py-4 text-center text-sm text-slate-500">
        {{ config('app.name') }} SpA · Distribución refrigerada
    </footer>
    @livewireScripts
</body>
</html>
