import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/**
 * Three entry points, so a technician never downloads what only a manager
 * sees (Frontend 10.1).
 *
 *   app        Bootstrap 5, CoreUI, Axios, Day.js, Tom Select. Every page.
 *   mobile     Execution and reporting screens, offline queue, camera.
 *   analytics  Chart.js and Flatpickr. Dashboard and report routes only.
 *
 * Chart.js is roughly 60 KB gzipped that the execution screen would never use,
 * which is why it is not in the shared bundle.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/js/mobile.js',
                'resources/js/analytics.js',
            ],
            refresh: [
                'app/Modules/**/Resources/views/**',
                'resources/views/**',
                'routes/**',
                'lang/**',
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/vendor/**'],
        },
    },
    build: {
        // A budget breach should be visible in the build output, not
        // discovered in production (Frontend 10.3).
        chunkSizeWarningLimit: 250,
    },
});
