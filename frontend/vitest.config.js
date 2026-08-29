import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// 프론트 단위 테스트 전용 설정 (빌드용 vite.config.js와 분리).
// 순수 로직·스토어·컴포저블 테스트만 대상으로 하며, naive-ui 리졸버는 로드하지 않는다.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'happy-dom',
        include: ['src/**/*.{test,spec}.js'],
    },
});
