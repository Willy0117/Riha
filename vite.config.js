import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    base: '/vite-dev/',
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    build: {
        minify: false,
        reportCompressedSize: false,
        sourcemap: false,
        rollupOptions: {
            output: {
                manualChunks: {
                    'lucide': ['lucide-vue-next'],
                    'vendor-ui':   ['radix-vue', '@vueuse/core', 'class-variance-authority', 'clsx', 'tailwind-merge'],
                }
            }
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'https://stage.vision-bridge.org',
        hmr: {
            host: 'stage.vision-bridge.org',
            clientPort: 443,
        },
        watch: {
            ignored: ['**/vendor/**', '**/node_modules/**'],
        },
    },
});