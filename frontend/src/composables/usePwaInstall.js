import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * PWA 설치 — beforeinstallprompt 이벤트를 포착해 앱 설치를 유도한다.
 * 이미 설치됐거나 브라우저가 미지원이면 이벤트가 오지 않아 canInstall이 false로 유지된다.
 */
export function usePwaInstall() {
    const canInstall = ref(false);
    const installing = ref(false);
    let deferredPrompt = null;

    const onBeforeInstall = (event) => {
        event.preventDefault(); // 브라우저 기본 설치 배너 대신 앱 내 항목으로 유도
        deferredPrompt = event;
        canInstall.value = true;
    };

    const onInstalled = () => {
        canInstall.value = false;
        deferredPrompt = null;
    };

    onMounted(() => {
        window.addEventListener('beforeinstallprompt', onBeforeInstall);
        window.addEventListener('appinstalled', onInstalled);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeinstallprompt', onBeforeInstall);
        window.removeEventListener('appinstalled', onInstalled);
    });

    const install = async () => {
        if (!deferredPrompt) {
            return false;
        }

        installing.value = true;
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;
        installing.value = false;

        if (outcome === 'accepted') {
            canInstall.value = false;
        }

        return outcome === 'accepted';
    };

    return { canInstall, installing, install };
}
