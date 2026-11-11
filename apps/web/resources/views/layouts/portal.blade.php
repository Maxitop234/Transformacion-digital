<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Portal') · {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="min-h-screen bg-white text-slate-900" data-superficie="portal">
    <header class="border-b border-slate-200">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('portal.inicio') }}" class="font-semibold">{{ config('app.name') }}</a>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @yield('contenido')
    </main>

    <footer class="border-t border-slate-200 py-4 text-center text-sm text-slate-500">
        {{ config('app.name') }} SpA · Distribución refrigerada
    </footer>
    @livewireScripts
</body>
</html>
