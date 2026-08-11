import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { NaiveUiResolver } from 'unplugin-vue-components/resolvers';
import { cpSync, rmSync } from 'node:fs';

/**
 * 독립 프론트엔드 (M2).
 * 개발 중에는 /api 요청을 Laravel 백엔드(기본 8000)로 프록시한다.
 * cloudflared 터널 등 외부 호스트 접근을 허용한다.
 *
 * preview는 빌드 산출물(dist)을 서빙한다 — 터널/외부 노출용.
 * 빌드 완료 후 dist를 Laravel public/에도 동기화한다 (php artisan serve에서 동일 화면).
 *
 * unplugin-vue-components(NaiveUiResolver):
 * naive-ui 컴포넌트를 사용한 곳만 번들에 포함해 번들 크기를 크게 줄인다.
 */
const apiProxy = {
    '/api': {
        target: process.env.VITE_API_PROXY_TARGET || 'http://localhost:8000',
        changeOrigin: true,
    },
};

// 빌드 후 dist → Laravel public/ 동기화 (index.php 등은 유지)
const syncPublic = () => ({
    name: 'sync-public',
    closeBundle() {
        rmSync('../public/assets', { recursive: true, force: true });
        cpSync('dist', '../public', { recursive: true, force: true });
    },
});

export default defineConfig({
    plugins: [
        vue(),
        Components({
            resolvers: [NaiveUiResolver()],
            dts: false,
        }),
        syncPublic(),
    ],
    server: {
        port: 5174,
        host: '0.0.0.0',
        allowedHosts: ['.trycloudflare.com'],
        proxy: apiProxy,
    },
    preview: {
        port: 4173,
        host: '0.0.0.0',
        allowedHosts: ['.trycloudflare.com'],
        proxy: apiProxy,
    },
});
