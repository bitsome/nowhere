import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import './assets/base.css';

// PWA — 프로덕션 빌드에서만 서비스 워커를 등록한다 (오프라인 셸 캐시 + 설치 지원).
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(`${import.meta.env.BASE_URL}sw.js`).catch(() => {});
    });
}

createApp(App).use(createPinia()).use(router).mount('#app');
