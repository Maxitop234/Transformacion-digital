/*
 * Interacción de la interfaz (T-028). Sin dependencias: tema claro/oscuro,
 * menús que se abren y cierran, y pequeños detalles de movimiento. Todo es
 * mejora progresiva: sin JavaScript las páginas siguen funcionando.
 */

const raiz = document.documentElement;

// --- Tema ---------------------------------------------------------------
// El tema inicial lo fija un script en <head> (layouts/_tema) para que no
// parpadee; aquí solo se conmuta y se recuerda la elección.

function aplicarTema(tema) {
    raiz.dataset.theme = tema;
    document.querySelectorAll('[data-tema-conmutar]').forEach((boton) => {
        boton.setAttribute('aria-pressed', String(tema === 'dark'));
        boton.setAttribute('title', tema === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    });
}

document.addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-tema-conmutar]');
    if (!boton) return;

    const tema = raiz.dataset.theme === 'dark' ? 'light' : 'dark';
    aplicarTema(tema);

    try {
        localStorage.setItem('cofrial.tema', tema);
    } catch {
        // Sin almacenamiento (modo privado): el tema dura lo que la página.
    }
});

// Si la persona no eligió tema, se sigue al sistema cuando este cambia.
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (evento) => {
    let elegido = null;
    try {
        elegido = localStorage.getItem('cofrial.tema');
    } catch {
        // Ignorado.
    }
    if (!elegido) aplicarTema(evento.matches ? 'dark' : 'light');
});

// --- Menús ----------------------------------------------------------------
// <button data-conmuta="id" aria-expanded="false"> abre y cierra el elemento
// con ese id (atributo data-abierto). Se cierra con Escape o al hacer clic
// fuera; los que llevan data-velo usan además un velo de fondo.

function cerrar(objetivo) {
    if (!objetivo.hasAttribute('data-abierto')) return;

    objetivo.removeAttribute('data-abierto');
    document.querySelectorAll(`[data-conmuta="${objetivo.id}"]`).forEach((b) => b.setAttribute('aria-expanded', 'false'));
    document.querySelectorAll(`[data-velo-de="${objetivo.id}"]`).forEach((v) => v.removeAttribute('data-abierto'));
}

function abrir(objetivo) {
    objetivo.setAttribute('data-abierto', '');
    document.querySelectorAll(`[data-conmuta="${objetivo.id}"]`).forEach((b) => b.setAttribute('aria-expanded', 'true'));
    document.querySelectorAll(`[data-velo-de="${objetivo.id}"]`).forEach((v) => v.setAttribute('data-abierto', ''));
}

document.addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-conmuta]');
    const velo = evento.target.closest('[data-velo-de]');

    if (velo) {
        const objetivo = document.getElementById(velo.dataset.veloDe);
        if (objetivo) cerrar(objetivo);

        return;
    }

    document.querySelectorAll('[data-abierto][data-cierra-fuera]').forEach((abierto) => {
        const suBoton = boton && boton.dataset.conmuta === abierto.id;
        if (!suBoton && !abierto.contains(evento.target)) cerrar(abierto);
    });

    if (!boton) return;

    const objetivo = document.getElementById(boton.dataset.conmuta);
    if (!objetivo) return;

    if (objetivo.hasAttribute('data-abierto')) {
        cerrar(objetivo);
    } else {
        abrir(objetivo);
    }
});

document.addEventListener('keydown', (evento) => {
    if (evento.key !== 'Escape') return;

    document.querySelectorAll('[data-abierto][id]').forEach((abierto) => {
        const boton = document.querySelector(`[data-conmuta="${abierto.id}"]`);
        cerrar(abierto);
        if (boton && abierto.contains(document.activeElement)) boton.focus();
    });
});

// --- Detalles de movimiento -------------------------------------------------

const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function iniciar() {
    aplicarTema(raiz.dataset.theme === 'dark' ? 'dark' : 'light');

    // Índice para la entrada escalonada de tarjetas (ver app.css).
    document.querySelectorAll('[data-productos], [data-bloque="indicadores"], [data-pipeline]').forEach((lista) => {
        [...lista.children].forEach((hijo, i) => hijo.style.setProperty('--i', i));
    });

    // Los indicadores enteros del panel cuentan hasta su valor.
    if (!sinMovimiento) {
        document.querySelectorAll('[data-indicador]').forEach((nodo) => {
            const texto = nodo.textContent.trim();
            if (!/^\d{1,6}$/.test(texto)) return;

            const final = Number(texto);
            if (final === 0) return;

            const inicio = performance.now();
            const duracion = 600;

            const paso = (ahora) => {
                const avance = Math.min((ahora - inicio) / duracion, 1);
                nodo.textContent = String(Math.round(final * (1 - (1 - avance) ** 3)));
                if (avance < 1) requestAnimationFrame(paso);
            };

            requestAnimationFrame(paso);
        });
    }

    // Un formulario enviado deshabilita su botón para evitar el doble envío.
    document.addEventListener('submit', (evento) => {
        if (evento.defaultPrevented) return;

        const boton = evento.submitter;
        if (!boton || boton.dataset.sinBloqueo !== undefined) return;

        // Se difiere para que el valor del botón alcance a viajar en el envío.
        setTimeout(() => {
            boton.disabled = true;
            boton.setAttribute('aria-busy', 'true');
        }, 0);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
} else {
    iniciar();
}

// Al volver con el botón «atrás», la página sale de la caché con los botones
// deshabilitados: se reactivan.
window.addEventListener('pageshow', (evento) => {
    if (!evento.persisted) return;

    document.querySelectorAll('button[aria-busy="true"]').forEach((boton) => {
        boton.disabled = false;
        boton.removeAttribute('aria-busy');
    });
});
