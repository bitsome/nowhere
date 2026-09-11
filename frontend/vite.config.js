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
        // 개발은 로컬 Laravel API(`php artisan serve`, 기본 8000)로 프록시한다 — 로컬 DB 사용.
        // 서버 API를 임시로 보고 싶을 때는 VITE_API_PROXY_TARGET=https://114.132.240.52 로 덮어쓴다.
        target: process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false,
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

/**
 * 정적 index.html의 OG·canonical 주소 채우기.
 *
 * 위챗·카카오 미리보기 카드는 OG 이미지·URL을 읽는데, 이 값이 상대 경로면 카드가 뜨지 않는다.
 * 도메인 확정 전에는 VITE_SITE_URL이 비어 있어 종전과 같은 상대 경로로 남고,
 * 도메인이 정해지면 `VITE_SITE_URL=https://example.com npm run build` 한 줄로 절대 URL이 된다.
 */
const htmlSiteUrl = () => {
    const siteUrl = (process.env.VITE_SITE_URL || '').replace(/\/+$/, '');

    return {
        name: 'html-site-url',
        transformIndexHtml: (html) => html.replaceAll('%SITE_URL%', siteUrl),
    };
};

export default defineConfig({
    // 서버 배포 시 하위 경로(/spa 등)에 두려면 VITE_BASE 환경변수로 지정한다.
    // 예) VITE_BASE=/spa/ npm run build
    base: process.env.VITE_BASE || '/',
    plugins: [
        vue(),
        Components({
            resolvers: [NaiveUiResolver()],
            dts: false,
        }),
        syncPublic(),
        htmlSiteUrl(),
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
