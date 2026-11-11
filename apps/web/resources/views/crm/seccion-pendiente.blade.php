@extends('layouts.crm')

@section('titulo', $titulo)

@section('contenido')
    <h1 class="text-2xl font-semibold">{{ $titulo }}</h1>
    <p class="mt-2 text-slate-600">Esta sección se construye en {{ $tarea }}. El acceso por rol ya está activo.</p>
@endsection
