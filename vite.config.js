import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = new URL(env.APP_URL || 'http://localhost');
    const devHost = env.VITE_DEV_SERVER_HOST || appUrl.hostname || 'localhost';
    const devPort = Number(env.VITE_DEV_SERVER_PORT || 5173);
    const devProtocol = appUrl.protocol === 'https:' ? 'https' : 'http';

    return {
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
            vue(),
        ],
        server: {
            cors: {
                origin: appUrl.origin,
            },
            host: '0.0.0.0',
            hmr: {
                clientPort: devPort,
                host: devHost,
                protocol: devProtocol === 'https' ? 'wss' : 'ws',
            },
            origin: `${devProtocol}://${devHost}:${devPort}`,
            port: devPort,
            strictPort: true,
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
