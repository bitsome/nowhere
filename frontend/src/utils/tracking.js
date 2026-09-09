import { apiTrackEvents } from '../api/behavior';

/**
 * 행동 신호(노출·클릭) 수집기 — 개인화 추천의 원료.
 *
 * - 카드가 실제로 보일 때(impression) 한 번, 클릭으로 상세에 진입할 때(click) 한 번 보낸다.
 * - 노출은 세션당 같은 카드·같은 자리에서 중복 전송하지 않아 폴링/탭 전환 시 무한 누적을 막는다.
 * - 요청은 짧게 모아 보내고(디바운스), 실패해도 사용자 흐름을 방해하지 않는다(분석 신호).
 */

let queue = [];
let timer = null;

const FLUSH_DELAY_MS = 1200;
const FLUSH_BATCH_SIZE = 40;

// 같은 자리의 같은 운행은 세션 동안 한 번만 노출로 친다 (scope:section:orderId)
const sentImpressionKeys = new Set();

function isAuthed() {
    return Boolean(localStorage.getItem('auth_token'));
}

function flush() {
    if (queue.length === 0) return;

    const batch = queue;
    queue = [];

    if (!isAuthed()) return;

    apiTrackEvents(batch).catch(() => {
        // 분석 신호는 부가 데이터 — 실패해도 앱 흐름을 막지 않는다
    });
}

function scheduleFlush() {
    if (timer !== null) return;

    timer = setTimeout(() => {
        timer = null;
        flush();
    }, FLUSH_DELAY_MS);
}

/**
 * 노출 신호 — 같은 (scope, section, orderId)는 세션 동안 한 번만 전송한다.
 */
export function trackImpression({ orderId = null, scope = '', section = '', rank = null, meta = {} } = {}) {
    if (!isAuthed()) return;

    const key = `${scope}:${section}:${orderId ?? 'none'}`;

    if (sentImpressionKeys.has(key)) return;

    sentImpressionKeys.add(key);

    queue.push({
        event: 'impression',
        order_id: orderId,
        meta: { scope, section, rank, ...meta },
    });

    if (queue.length >= FLUSH_BATCH_SIZE) {
        flush();

        return;
    }

    scheduleFlush();
}

/**
 * 클릭 신호 — 카드 → 상세 진입 시 전송 (중복 제한 없음).
 */
export function trackClick({ orderId = null, scope = '', section = '', rank = null, meta = {} } = {}) {
    if (!isAuthed()) return;

    queue.push({
        event: 'click',
        order_id: orderId,
        meta: { scope, section, rank, ...meta },
    });

    if (queue.length >= FLUSH_BATCH_SIZE) {
        flush();

        return;
    }

    scheduleFlush();
}

// 브라우저를 떠나기 직전 남은 신호를 마저 보낸다
if (typeof window !== 'undefined') {
    window.addEventListener('pagehide', () => {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }

        flush();
    });
}
