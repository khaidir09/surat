import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // statistik.js berdiri sebagai entry sendiri, bukan digabung ke
            // app.js: Chart.js hanya dipakai satu halaman, dan halaman antrean
            // yang dibuka staf berkali-kali sehari tidak boleh ikut menanggung
            // unduhannya. Dimuat lewat @push('skrip') di statistik/index.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/statistik.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
