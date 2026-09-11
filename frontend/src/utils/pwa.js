/**
 * PWA 설치 안내 판정 — 기기·브라우저에 따라 안내 방식이 다르다.
 *
 * 아이폰 Safari 는 beforeinstallprompt 이벤트를 보내지 않아 자동 설치 버튼을 만들 수 없다.
 * 공유 → 홈 화면에 추가를 직접 안내해야 하며, iOS 웹 푸시도 홈 화면에 추가한 뒤에만
 * 동작하므로 이 안내가 곧 알림 수신의 전제가 된다.
 * 위챗·카카오 같은 인앱 브라우저에서는 홈 화면 추가 자체가 막혀 Safari 로 열어야 한다.
 */

const IOS_PATTERN = /iPad|iPhone|iPod/;
const IN_APP_PATTERN = /MicroMessenger|KAKAOTALK|KAKAO|NAVER\(inapp|Instagram|FBAN|FBAV|Line\//i;

/** 아이폰·아이패드 여부 — iPadOS 13+ 는 데스크톱(Macintosh) UA 로 오므로 터치 지원으로 함께 판정한다 */
export const isIosDevice = (userAgent = '', maxTouchPoints = 0) =>
    IOS_PATTERN.test(userAgent) || (/Macintosh/.test(userAgent) && maxTouchPoints > 1);

/** 인앱 브라우저(위챗·카카오 등) 여부 — 여기서는 홈 화면에 추가할 수 없다 */
export const isInAppBrowser = (userAgent = '') => IN_APP_PATTERN.test(userAgent);

/** 홈 화면 앱으로 이미 실행 중인지 */
export const isStandalone = (navigatorStandalone = false, displayModeStandalone = false) =>
    navigatorStandalone === true || displayModeStandalone === true;

/**
 * 어떤 설치 안내를 보여줄지 정한다.
 *
 * @returns {'prompt'|'ios'|'inapp'|null}
 *  - prompt: 설치 버튼을 바로 띄울 수 있음(안드로이드·데스크톱 크롬)
 *  - ios: 공유 → 홈 화면에 추가 순서를 안내해야 함
 *  - inapp: Safari 로 열어야 함
 *  - null: 이미 설치됐거나 안내할 것이 없음
 */
export const detectInstallGuide = ({
    userAgent = '',
    maxTouchPoints = 0,
    standalone = false,
    promptAvailable = false,
} = {}) => {
    if (standalone) {
        return null;
    }

    if (isInAppBrowser(userAgent)) {
        return 'inapp';
    }

    if (isIosDevice(userAgent, maxTouchPoints)) {
        return 'ios';
    }

    return promptAvailable ? 'prompt' : null;
};
