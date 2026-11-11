<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 32rem; margin: 15vh auto; padding: 0 1rem; }
        .codigo { color: #64748b; font-size: .9rem; }
        a { color: #0369a1; }
    </style>
</head>
<body>
    <main>
        <p class="codigo">Error {{ $codigo }}</p>
        <h1>{{ $titulo }}</h1>
        <p>{{ $detalle }}</p>
        <p><a href="{{ url('/') }}">Volver al inicio</a></p>
    </main>
</body>
</html>
