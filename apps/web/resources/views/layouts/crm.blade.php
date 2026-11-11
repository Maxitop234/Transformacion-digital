<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'CRM') · {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900" data-superficie="crm">
    <div class="flex min-h-screen">
        <aside class="w-60 shrink-0 border-r border-slate-200 bg-white">
            <div class="px-4 py-3 font-semibold">{{ config('app.name') }} · CRM</div>
            {{-- Menú por rol: cada entrada aparece solo si el gate de su área lo permite (App\Domain\Clientes\Permisos). --}}
            <nav class="px-2 text-sm" aria-label="Menú del CRM">
                @foreach ([
                    ['crm', 'crm.inicio', 'Panel'],
                    ['clientes', 'crm.clientes.index', 'Clientes'],
                    ['oportunidades', 'crm.oportunidades', 'Oportunidades'],
                    ['pedidos', 'crm.pedidos', 'Pedidos'],
                    ['despachos', 'crm.despachos', 'Despachos'],
                    ['flota', 'crm.flota', 'Flota'],
                    ['alertas', 'crm.alertas', 'Alertas de frío'],
                    ['tickets', 'crm.tickets', 'Tickets'],
                    ['administracion', 'crm.admin', 'Administración'],
                ] as [$area, $ruta, $etiqueta])
                    @can($area)
                        <a href="{{ route($ruta) }}" @class(['block rounded px-2 py-1 hover:bg-slate-100', 'bg-slate-100 font-medium' => request()->routeIs($ruta.'*')])>{{ $etiqueta }}</a>
                    @endcan
                @endforeach
            </nav>

            @auth
                <div class="mt-6 border-t border-slate-200 px-4 py-3 text-xs text-slate-600">
                    <p>{{ auth()->user()->email }}</p>
                    <p class="mt-0.5">{{ str_replace('_', ' ', auth()->user()->nombreRol()) }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-sky-700 hover:underline">Cerrar sesión</button>
                    </form>
                </div>
            @endauth
        </aside>

        <main class="flex-1 px-6 py-6">
            @yield('contenido')
        </main>
    </div>
    @livewireScripts
</body>
</html>
