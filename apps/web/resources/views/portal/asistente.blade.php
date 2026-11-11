@extends('layouts.portal')

@section('titulo', 'Asistente')

@section('contenido')
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-semibold">Asistente</h1>
        <p class="mt-1 text-sm text-slate-600">
            Pregunta por el estado de tus pedidos o por productos del catálogo. Si no puede resolverlo, deja tu consulta
            como ticket para un ejecutivo. No escribas datos personales: no los necesita.
        </p>

        <ol id="conversacion" class="mt-6 space-y-3 text-sm" aria-live="polite" data-conversacion></ol>

        <form id="form-asistente" class="mt-4 flex gap-2" data-url="{{ route('portal.asistente.mensajes') }}">
            <label for="mensaje" class="sr-only">Tu consulta</label>
            <input id="mensaje" name="mensaje" maxlength="500" required autocomplete="off" placeholder="Ej.: ¿Cuándo llega mi pedido 1042?"
                   class="flex-1 rounded border border-slate-300 px-3 py-2">
            <button class="rounded bg-sky-700 px-4 py-2 text-white hover:bg-sky-800 disabled:opacity-50">Enviar</button>
        </form>
    </div>

    <script>
        (() => {
            const form = document.getElementById('form-asistente');
            const lista = document.getElementById('conversacion');
            const campo = document.getElementById('mensaje');
            const boton = form.querySelector('button');

            const agregar = (texto, quien) => {
                const li = document.createElement('li');
                li.className = quien === 'cliente'
                    ? 'ml-auto max-w-[85%] rounded bg-sky-50 px-3 py-2 text-sky-900'
                    : 'max-w-[85%] rounded bg-slate-100 px-3 py-2';
                li.textContent = texto; // texto plano: nunca HTML de la IA
                lista.append(li);
            };

            form.addEventListener('submit', async (evento) => {
                evento.preventDefault();
                const mensaje = campo.value.trim();
                if (!mensaje) return;

                agregar(mensaje, 'cliente');
                campo.value = '';
                boton.disabled = true;

                try {
                    const respuesta = await fetch(form.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                        },
                        body: JSON.stringify({ mensaje }),
                    });

                    if (respuesta.status === 429) {
                        agregar('Hiciste muchas consultas seguidas. Intenta de nuevo en un momento.', 'asistente');
                    } else if (!respuesta.ok) {
                        agregar('No pudimos procesar tu consulta. Intenta de nuevo.', 'asistente');
                    } else {
                        const datos = await respuesta.json();
                        agregar(datos.respuesta, 'asistente');
                    }
                } catch {
                    agregar('No hay conexión con el servidor. Intenta de nuevo.', 'asistente');
                } finally {
                    boton.disabled = false;
                    campo.focus();
                }
            });
        })();
    </script>
@endsection
