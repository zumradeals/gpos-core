import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fontsource } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Fontsource ships the font files as npm packages: pas d'appel CDN au build ni
                // à l'exécution, entièrement auto-hébergé. Même famille que DG Afrique/ZUMRA
                // (identité visuelle GAMAD commune), voir docs/product/DESIGN-DIRECTION.md §7.
                fontsource('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                    optimizedFallbacks: false,
                }),
                fontsource('Instrument Serif', {
                    weights: [400],
                    styles: ['normal'],
                    optimizedFallbacks: false,
                }),
                fontsource('IBM Plex Mono', {
                    weights: [400, 500],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
