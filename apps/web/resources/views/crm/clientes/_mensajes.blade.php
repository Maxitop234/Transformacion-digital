{{-- Confirmación de la última acción y errores generales de la ficha. --}}
@if (session('estado'))
    <p class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900" role="status" data-estado>{{ session('estado') }}</p>
@endif
@if ($errors->any())
    <ul class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-900" role="alert" data-errores>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
