<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Portal') · {{ config('app.name') }}</title>
    @include('layouts._tema')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-slate-50 text-slate-900" data-superficie="portal">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Saltar al contenido</a>

    <header class="cabecera border-b border-slate-200">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-2 px-4 py-2.5" aria-label="Menú principal">
            <a href="{{ route('portal.inicio') }}" class="marca">
                <span class="marca-icono"><x-icono nombre="copo" tamano="16" /></span>
                {{ config('app.name') }}
            </a>

            <div class="ml-auto flex items-center gap-1 lg:order-3 lg:ml-0">
                @include('layouts._conmutador-tema')

                @auth
                    @php($cuenta = auth()->user())
                    <div class="relative">
                        <button type="button" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-100"
                                data-conmuta="menu-cuenta" aria-expanded="false" aria-haspopup="true" aria-controls="menu-cuenta">
                            <span class="grid h-7 w-7 place-items-center rounded-full bg-sky-700 text-xs font-semibold uppercase text-white">{{ mb_substr($cuenta->email, 0, 1) }}</span>
                            <span class="hidden max-w-44 truncate md:inline">{{ $cuenta->email }}</span>
                            <x-icono nombre="abajo" tamano="14" />
                        </button>
                        <div id="menu-cuenta" class="desplegable text-sm" data-cierra-fuera>
                            <p class="break-all px-2.5 pb-1.5 pt-1 text-xs text-slate-500">{{ $cuenta->email }}</p>
                            @can('portal.comprar')
                                <a href="{{ route('portal.mis-pedidos') }}" class="desplegable-item"><x-icono nombre="pedidos" tamano="16" /> Mis pedidos</a>
                                <a href="{{ route('portal.direcciones') }}" class="desplegable-item"><x-icono nombre="flota" tamano="16" /> Mis direcciones</a>
                            @endcan
                            @can('crm')
                                <a href="{{ route('crm.inicio') }}" class="desplegable-item"><x-icono nombre="panel" tamano="16" /> CRM</a>
                            @endcan
                            <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-slate-200 pt-1">
                                @csrf
                                <button type="submit" class="desplegable-item"><x-icono nombre="salir" tamano="16" /> Cerrar sesión</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100">Iniciar sesión</a>
                    <a href="{{ route('registro.persona') }}" class="hidden rounded bg-sky-700 px-3 py-1.5 text-sm text-white hover:bg-sky-800 sm:inline-block">Crear cuenta</a>
                @endauth

                <button type="button" class="boton-icono lg:hidden" data-conmuta="menu-portal" aria-expanded="false" aria-controls="menu-portal" aria-label="Abrir o cerrar el menú">
                    <x-icono nombre="menu" />
                </button>
            </div>

            <div id="menu-portal" class="menu-portal lg:order-2 lg:ml-8 lg:w-auto lg:flex-1" data-cierra-fuera>
                <div>
                    <ul class="flex flex-col gap-1 pb-2 pt-2 text-sm lg:flex-row lg:items-center lg:gap-6 lg:p-0">
                        <li><a href="{{ route('portal.catalogo') }}" class="enlace-menu" @if (request()->routeIs('portal.catalogo', 'portal.productos.*')) aria-current="page" @endif>Catálogo</a></li>
                        @auth
                            @can('portal.comprar')
                                <li><a href="{{ route('portal.carrito') }}" class="enlace-menu" data-enlace-carrito @if (request()->routeIs('portal.carrito', 'portal.checkout')) aria-current="page" @endif><x-icono nombre="carrito" tamano="16" /> Carrito</a></li>
                                <li><a href="{{ route('portal.mis-pedidos') }}" class="enlace-menu" data-enlace-mis-pedidos @if (request()->routeIs('portal.mis-pedidos*')) aria-current="page" @endif>Mis pedidos</a></li>
                                <li><a href="{{ route('portal.direcciones') }}" class="enlace-menu" @if (request()->routeIs('portal.direcciones*')) aria-current="page" @endif>Mis direcciones</a></li>
                                <li><a href="{{ route('portal.asistente') }}" class="enlace-menu" @if (request()->routeIs('portal.asistente*')) aria-current="page" @endif>Asistente</a></li>
                                <li><a href="{{ route('portal.tickets') }}" class="enlace-menu" @if (request()->routeIs('portal.tickets*')) aria-current="page" @endif>Ayuda</a></li>
                            @endcan
                            @can('crm')
                                <li><a href="{{ route('crm.inicio') }}" class="enlace-menu">CRM</a></li>
                            @endcan
                        @else
                            <li class="sm:hidden"><a href="{{ route('registro.persona') }}" class="enlace-menu">Crear cuenta</a></li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main id="contenido" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        @include('portal._mensajes')
        @yield('contenido')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-5 text-sm text-slate-500">
            <p class="flex items-center gap-2"><x-icono nombre="copo" tamano="14" /> {{ config('app.name') }} SpA · Distribución refrigerada</p>
            <p>La Araucanía, Chile</p>
        </div>
    </footer>
    @livewireScripts
</body>
</html>
