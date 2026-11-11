<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'CRM') · {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900" data-superficie="crm">
    <div class="flex min-h-screen">
        <aside class="w-60 shrink-0 border-r border-slate-200 bg-white">
            <div class="px-4 py-3 font-semibold">{{ config('app.name') }} · CRM</div>
            <nav class="px-2 text-sm">
                {{-- Menú por rol: se completa en T-006 y T-013. --}}
                <a href="{{ route('crm.inicio') }}" class="block rounded px-2 py-1 hover:bg-slate-100">Panel</a>
            </nav>
        </aside>

        <main class="flex-1 px-6 py-6">
            @yield('contenido')
        </main>
    </div>
    @livewireScripts
</body>
</html>
