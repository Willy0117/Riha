import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: true, // これが重要！外部からの接続を許可
        port: 5173, // Viteのデフォルトポート
        hmr: {
            host: '54.178.173.155', // ここにあなたのEC2のPublic IP (例: 54.178.173.155) を設定
            // clientPort: 443, // HTTPSを使用している場合。HTTPの場合は不要か80。直接5173を開くなら不要
        },
    },    
});
