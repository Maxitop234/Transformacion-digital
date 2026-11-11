@extends('layouts.portal')

@section('titulo', 'Inicio')

@section('contenido')
    <h1 class="text-2xl font-semibold">Portal de ventas</h1>
    <p class="mt-2 text-slate-600">Productos refrigerados y congelados para personas y empresas de La Araucanía.</p>
    <a href="{{ route('portal.catalogo') }}" class="mt-4 inline-block rounded bg-sky-700 px-4 py-2 text-white hover:bg-sky-800">Ver catálogo</a>
@endsection
