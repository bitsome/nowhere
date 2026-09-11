import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { detectInstallGuide, isStandalone } from '../utils/pwa';

/**
 * PWA 설치 — 기기·브라우저에 맞는 안내 방식을 판정한다.
 *
 * 안드로이드·데스크톱 크롬은 beforeinstallprompt 이벤트로 설치 버튼을 띄울 수 있지만,
 * 아이폰 Safari 는 이 이벤트가 없어 공유 → 홈 화면에 추가를 직접 안내해야 한다.
 * iOS 웹 푸시도 홈 화면에 추가한 뒤에만 동작하므로 이 안내가 알림 수신의 전제다.
 */
export function usePwaInstall() {
    // beforeinstallprompt 도착 여부 (안드로이드·데스크톱 크롬)
    const canInstall = ref(false);
    const installing = ref(false);
    // 이미 홈 화면 앱으로 실행 중이면 어떤 안내도 필요 없다
    const installed = ref(
        isStandalone(
            window.navigator.standalone,
            window.matchMedia('(display-mode: standalone)').matches,
        ),
    );
    let deferredPrompt = null;

    const onBeforeInstall = (event) => {
        event.preventDefault(); // 브라우저 기본 설치 배너 대신 앱 내 항목으로 유도
        deferredPrompt = event;
        canInstall.value = true;
    };

    const onInstalled = () => {
        installed.value = true;
        canInstall.value = false;
        deferredPrompt = null;
    };

    // 어떤 안내를 보여줄지 — 기기·브라우저 판정은 순수 함수가 맡는다
    const guide = computed(() => detectInstallGuide({
        userAgent: window.navigator.userAgent,
        maxTouchPoints: window.navigator.maxTouchPoints ?? 0,
        standalone: installed.value,
        promptAvailable: canInstall.value,
    }));

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

    return { canInstall, installing, installed, guide, install };
}
