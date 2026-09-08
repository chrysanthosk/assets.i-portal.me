import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // zxcvbn's dictionaries are a deliberately separate, lazily loaded chunk
        chunkSizeWarningLimit: 1000,
    },
});
