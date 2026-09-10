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

// 개발 단계에서는 캐시를 쓰지 않는다 — 이전에 등록된 서비스 워커가 남아 있으면 해제해
// 구버전 자산이 노출되지 않게 한다 (개발 서버·프리뷰 모두 항상 최신 소스 사용)
if (!import.meta.env.PROD && 'serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then((registrations) => {
        registrations.forEach((registration) => registration.unregister());
    });
}

const app = createApp(App);

app.use(createPinia()).use(router).mount('#app');
