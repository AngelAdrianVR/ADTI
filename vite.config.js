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
    // comentar los siguientes bloques si no se usa el dominio adti.test
    // server: {
    //     detectTls: 'adti.test',
    //     // 💡 Añade este bloque hmr para corregir el WebSocket de Vite:
    //     hmr: {
    //         host: 'adti.test',
    //         protocol: 'wss',
    //     },
    // },
});
