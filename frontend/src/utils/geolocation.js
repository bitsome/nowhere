/**
 * 운행 시작 시 기사 기기 위치를 1회 읽는다.
 *
 * 위치 수집은 운행 시작을 막지 않는다 — 권한 거부·미지원·시간 초과는 모두 null 로 돌려주고,
 * 호출한 쪽은 좌표 없이 그대로 진행한다.
 */
const DEFAULT_TIMEOUT_MS = 5000;

// 소수 7자리 = 약 1cm. 서버 컬럼(decimal(10,7)) 정밀도에 맞춘다.
const round7 = (value) => Number(value.toFixed(7));

export const readCurrentPosition = (
    geolocation = globalThis.navigator?.geolocation,
    { timeout = DEFAULT_TIMEOUT_MS } = {},
) => {
    if (!geolocation?.getCurrentPosition) {
        return Promise.resolve(null);
    }

    return new Promise((resolve) => {
        let settled = false;
        let timer = null;

        const finish = (value) => {
            if (settled) {
                return;
            }
            settled = true;
            if (timer !== null) {
                clearTimeout(timer);
            }
            resolve(value);
        };

        // 브라우저가 콜백을 보내지 않는 경우(권한 팝업 방치 등)까지 대비한 자체 시간 제한
        timer = setTimeout(() => finish(null), timeout + 500);

        geolocation.getCurrentPosition(
            (position) => {
                const latitude = position?.coords?.latitude;
                const longitude = position?.coords?.longitude;

                finish(
                    Number.isFinite(latitude) && Number.isFinite(longitude)
                        ? { latitude: round7(latitude), longitude: round7(longitude) }
                        : null,
                );
            },
            () => finish(null),
            { enableHighAccuracy: true, timeout, maximumAge: 0 },
        );
    });
};
