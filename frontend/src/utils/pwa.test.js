import { describe, expect, it } from 'vitest';
import { detectInstallGuide, isInAppBrowser, isIosDevice, isStandalone } from './pwa';

/**
 * 아이폰 Safari 는 beforeinstallprompt 를 보내지 않아 설치 버튼을 만들 수 없다.
 * 안내 방식이 틀리면 기사(아이폰 사용자)는 홈 화면 추가를 안내받지 못하고,
 * iOS 웹 푸시는 홈 화면에 추가한 뒤에만 동작하므로 알림 채널이 열리지 않는다.
 */
const IPHONE_SAFARI =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
const IPHONE_WECHAT = `${IPHONE_SAFARI} MicroMessenger/8.0.49(0x18003123)`;
const ANDROID_CHROME =
    'Mozilla/5.0 (Linux; Android 14; SM-S911N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36';
const IPADOS_DESKTOP_UA =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';

describe('isIosDevice — 아이폰·아이패드 판정', () => {
    it('아이폰 Safari UA 를 알아본다', () => {
        expect(isIosDevice(IPHONE_SAFARI)).toBe(true);
    });

    it('아이패드OS 13+ 는 Macintosh UA 라도 터치 지원이면 아이패드로 본다', () => {
        expect(isIosDevice(IPADOS_DESKTOP_UA, 5)).toBe(true);
        expect(isIosDevice(IPADOS_DESKTOP_UA, 0)).toBe(false);
    });

    it('안드로이드·데스크톱은 아이폰이 아니다', () => {
        expect(isIosDevice(ANDROID_CHROME)).toBe(false);
    });
});

describe('isInAppBrowser — 인앱 브라우저 판정', () => {
    it('위챗 인앱 브라우저를 알아본다', () => {
        expect(isInAppBrowser(IPHONE_WECHAT)).toBe(true);
    });

    it('일반 Safari 는 인앱이 아니다', () => {
        expect(isInAppBrowser(IPHONE_SAFARI)).toBe(false);
    });
});

describe('isStandalone — 이미 홈 화면 앱으로 실행 중인지', () => {
    it('iOS navigator.standalone 또는 display-mode 로 판정한다', () => {
        expect(isStandalone(true, false)).toBe(true);
        expect(isStandalone(false, true)).toBe(true);
        expect(isStandalone(false, false)).toBe(false);
    });
});

describe('detectInstallGuide — 안내 방식 분기', () => {
    it('아이폰 Safari 는 공유 안내(ios)를 쓴다', () => {
        expect(detectInstallGuide({ userAgent: IPHONE_SAFARI })).toBe('ios');
    });

    it('위챗 인앱 브라우저는 Safari 로 열기(inapp)를 안내한다', () => {
        expect(detectInstallGuide({ userAgent: IPHONE_WECHAT })).toBe('inapp');
    });

    it('설치 버튼을 쓸 수 있으면 prompt 를 쓴다', () => {
        expect(detectInstallGuide({ userAgent: ANDROID_CHROME, promptAvailable: true })).toBe('prompt');
    });

    it('설치 버튼이 없으면 안내하지 않는다', () => {
        expect(detectInstallGuide({ userAgent: ANDROID_CHROME, promptAvailable: false })).toBeNull();
    });

    it('이미 홈 화면 앱이면 아이폰이라도 안내하지 않는다', () => {
        expect(detectInstallGuide({ userAgent: IPHONE_SAFARI, standalone: true })).toBeNull();
    });
});
