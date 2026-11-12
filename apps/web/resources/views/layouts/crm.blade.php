<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'CRM') · {{ config('app.name') }}</title>
    @include('layouts._tema')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900" data-superficie="crm">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded focus:bg-white focus:px-3 focus:py-2">Saltar al contenido</a>
    <div class="velo lg:hidden" data-velo-de="lateral-crm"></div>

    <div class="flex min-h-screen">
        <aside id="lateral-crm" class="lateral flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white lg:sticky lg:top-0 lg:h-screen">
            <div class="flex items-center justify-between px-4 py-3.5">
                <a href="{{ route('crm.inicio') }}" class="marca">
                    <span class="marca-icono"><x-icono nombre="copo" tamano="16" /></span>
                    {{ config('app.name') }} · CRM
                </a>
                <button type="button" class="boton-icono lg:hidden" data-conmuta="lateral-crm" aria-label="Cerrar el menú">
                    <x-icono nombre="cerrar" />
                </button>
            </div>

            {{-- Menú por rol: cada entrada aparece solo si el gate de su área lo permite (App\Domain\Clientes\Permisos). --}}
            <nav class="flex-1 overflow-y-auto px-3 pb-4 text-sm" aria-label="Menú del CRM">
                @foreach ([
                    null => [
                        ['crm', 'crm.inicio', 'Panel', 'panel', 'crm.inicio'],
                    ],
                    'Comercial' => [
                        ['clientes', 'crm.clientes.index', 'Clientes', 'clientes', 'crm.clientes.*'],
                        ['oportunidades', 'crm.oportunidades', 'Oportunidades', 'oportunidades', 'crm.oportunidades*'],
                        ['pedidos', 'crm.pedidos', 'Pedidos', 'pedidos', 'crm.pedidos*'],
                        ['tickets', 'crm.tickets', 'Tickets', 'tickets', 'crm.tickets*'],
                    ],
                    'Operación' => [
                        ['despachos', 'crm.despachos', 'Despachos', 'despachos', 'crm.despachos*'],
                        ['flota', 'crm.flota', 'Flota', 'flota', 'crm.flota'],
                        ['alertas', 'crm.alertas', 'Alertas de frío', 'alertas', 'crm.alertas*'],
                    ],
                    'Sistema' => [
                        ['administracion', 'crm.admin', 'Administración', 'administracion', 'crm.admin*'],
                    ],
                ] as $grupo => $entradas)
                    @php($visibles = array_filter($entradas, fn ($entrada) => auth()->user()?->can($entrada[0])))
                    @if ($visibles)
                        @if ($grupo)
                            <p class="mb-1 mt-4 px-2.5 text-[0.68rem] font-semibold uppercase tracking-wider text-slate-400">{{ $grupo }}</p>
                        @endif
                        <ul class="space-y-0.5">
                            @foreach ($visibles as [$area, $ruta, $etiqueta, $icono, $patron])
                                <li>
                                    <a href="{{ route($ruta) }}" class="lateral-enlace" @if (request()->routeIs($patron)) aria-current="page" @endif>
                                        <x-icono :nombre="$icono" /> {{ $etiqueta }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
            </nav>

            @auth
                <div class="border-t border-slate-200 px-4 py-3 text-xs text-slate-600">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-sky-700 text-sm font-semibold uppercase text-white">{{ mb_substr(auth()->user()->email, 0, 1) }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-slate-900" title="{{ auth()->user()->email }}">{{ auth()->user()->email }}</p>
                            <p class="mt-0.5 capitalize">{{ str_replace('_', ' ', auth()->user()->nombreRol()) }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="lateral-enlace w-full text-sm"><x-icono nombre="salir" tamano="16" /> Cerrar sesión</button>
                    </form>
                </div>
            @endauth
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="cabecera flex items-center gap-2 border-b border-slate-200 px-4 py-2 lg:px-6">
                <button type="button" class="boton-icono lg:hidden" data-conmuta="lateral-crm" aria-expanded="false" aria-controls="lateral-crm" aria-label="Abrir el menú">
                    <x-icono nombre="menu" />
                </button>
                <p class="truncate text-sm font-medium text-slate-600">@yield('titulo', 'CRM')</p>
                <div class="ml-auto flex items-center gap-1">
                    <a href="{{ route('portal.inicio') }}" class="hidden rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 sm:block">Ver portal</a>
                    @include('layouts._conmutador-tema')
                </div>
            </header>

            <main id="contenido" class="min-w-0 flex-1 px-4 py-6 lg:px-6">
                @yield('contenido')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
