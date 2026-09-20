import { afterEach, describe, expect, it, vi } from 'vitest';
import { readCurrentPosition } from './geolocation';

/**
 * 운행 시작 위치는 '있으면 좋은' 기록이다. 권한 거부·미지원·무응답으로 운행 시작이 막히면
 * 기사는 운행 자체를 시작하지 못하므로, 어떤 경우에도 null 로 끝나야 한다.
 */
const stubGeolocation = (impl) => ({ getCurrentPosition: impl });

describe('readCurrentPosition — 운행 시작 위치 1회 조회', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('좌표를 받으면 소수 7자리로 반올림해 돌려준다', async () => {
        const geo = stubGeolocation((success) => {
            success({ coords: { latitude: 37.497941999, longitude: 127.027621001 } });
        });

        await expect(readCurrentPosition(geo)).resolves.toEqual({
            latitude: 37.497942,
            longitude: 127.027621,
        });
    });

    it('권한 거부·측위 실패면 null 을 돌려준다', async () => {
        const geo = stubGeolocation((_success, failure) => failure({ code: 1 }));

        await expect(readCurrentPosition(geo)).resolves.toBeNull();
    });

    it('브라우저가 위치를 지원하지 않아도 예외 없이 null 이다', async () => {
        await expect(readCurrentPosition(undefined)).resolves.toBeNull();
    });

    it('응답이 오지 않으면 시간 초과 뒤 null 로 끝난다', async () => {
        vi.useFakeTimers();
        const geo = stubGeolocation(() => {});

        const pending = readCurrentPosition(geo, { timeout: 1000 });

        await vi.advanceTimersByTimeAsync(1500);

        await expect(pending).resolves.toBeNull();
    });

    it('좌표가 숫자가 아니면 null 로 본다', async () => {
        const geo = stubGeolocation((success) => {
            success({ coords: { latitude: null, longitude: undefined } });
        });

        await expect(readCurrentPosition(geo)).resolves.toBeNull();
    });
});
