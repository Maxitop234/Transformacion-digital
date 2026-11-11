import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

/**
 * En `vite build --watch` (servicio `assets` de Docker) Vite solo vigila lo que
 * importan el CSS y el JS. Tailwind saca las clases de las vistas Blade, así
 * que también se vigila resources/views para recompilar al editarlas.
 */
function vigilarVistas() {
    return {
        name: 'cofrial:vigilar-vistas',
        buildStart() {
            if (this.meta.watchMode) {
                this.addWatchFile(path.resolve('resources/views'));
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vigilarVistas(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
