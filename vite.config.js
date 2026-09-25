import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: '0.0.0.0',
        // drvfs (/mnt/c) tidak memancarkan event watcher → wajib polling di WSL.
        // Interval longgar + abaikan tree besar agar tidak membebani VM (vendor/ di drvfs sangat IO-berat).
        watch: {
            usePolling: true,
            interval: 2000,
            ignored: ['**/vendor/**', '**/storage/**', '**/node_modules/**', '**/.git/**', '**/tools/**', '**/out/**'],
        },
        hmr: {
            host: 'localhost',
        },
    },
});
