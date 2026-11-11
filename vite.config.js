import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ command }) => ({
    // Relative URLs in the build, so lazy-loaded chunks resolve from wherever the page is served:
    // XAMPP's /optics/public/ subfolder as well as `php artisan serve` at the domain root.
    base: command === 'build' ? './' : undefined,
    plugins: [
        laravel({
            // app.js: Alpine storefront. inertia.tsx: React pages (product page).
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/inertia.tsx', 'resources/js/admin-images.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
}));
