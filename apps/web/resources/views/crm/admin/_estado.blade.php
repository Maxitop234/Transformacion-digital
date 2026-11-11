{{-- Aviso de la última acción y errores de validación de los mantenedores. --}}
@if (session('estado'))
    <p role="status" class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('estado') }}</p>
@endif
@if ($errors->any())
    <div role="alert" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
