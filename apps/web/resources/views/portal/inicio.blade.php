@extends('layouts.portal')

@section('titulo', 'Inicio')

@section('contenido')
    <section class="portada px-6 py-12 sm:px-12 sm:py-16">
        <p class="text-sm font-medium uppercase tracking-widest text-sky-200">Distribución refrigerada · La Araucanía</p>
        <h1 class="mt-3 max-w-2xl text-3xl font-semibold leading-tight sm:text-5xl">Portal de ventas</h1>
        <p class="mt-4 max-w-xl text-base text-slate-200 sm:text-lg">Productos refrigerados y congelados para personas y empresas de La Araucanía.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('portal.catalogo') }}" class="portada-boton">Ver catálogo <x-icono nombre="flecha" tamano="16" /></a>
            @guest
                <a href="{{ route('registro.empresa') }}" class="portada-boton-secundario">Registrar mi empresa</a>
            @else
                @can('portal.comprar')
                    <a href="{{ route('portal.mis-pedidos') }}" class="portada-boton-secundario">Mis pedidos</a>
                @endcan
            @endguest
        </div>
    </section>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-productos>
        @foreach ([
            ['termometro', 'Cadena de frío a la vista', 'Cada pedido informa si su trayecto tuvo rupturas de frío.', route('portal.catalogo')],
            ['reloj', 'Seguimiento del pedido', 'Línea de tiempo desde la compra hasta la entrega, con camión y parada.', auth()->check() && auth()->user()->can('portal.comprar') ? route('portal.mis-pedidos') : route('login')],
            ['edificio', 'Personas y empresas', 'Pago en línea para personas; orden de compra y lista institucional para empresas.', auth()->check() ? route('portal.catalogo') : route('registro.empresa')],
            ['chat', 'Asistente y ayuda', 'Consulta el estado de tus pedidos o reporta un problema en un minuto.', auth()->check() && auth()->user()->can('portal.comprar') ? route('portal.asistente') : route('login')],
        ] as [$icono, $titulo, $texto, $destino])
            <li>
                <a href="{{ $destino }}" class="tarjeta-accion block h-full rounded border border-slate-200 bg-white p-5">
                    <span class="icono-destacado"><x-icono :nombre="$icono" tamano="20" /></span>
                    <h2 class="mt-4 font-medium">{{ $titulo }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $texto }}</p>
                </a>
            </li>
        @endforeach
    </ul>
@endsection
