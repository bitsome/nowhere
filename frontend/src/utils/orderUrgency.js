/**
 * 운행 임박/긴급 판정 — 서버(OrderListRowBuilder)와 같은 규칙을 상세 화면에서도 쓴다.
 *
 * 목록 카드는 서버가 내려준 isUrgent·isPriority 를 그대로 쓰지만, 상세는 운행 한 건만 받아
 * 직접 판정한다. 규칙이 갈라지면 같은 운행이 목록과 상세에서 다르게 보인다.
 *   - 임박: 오늘 운행인데 서비스 시각이 아직 지나지 않음 (남은 시간 상한 없음)
 *   - 긴급: 등록자가 긴급으로 올렸거나, 아직 기사가 없는 공개/거래중/승인대기 운행의 시각이 지남
 */

// 아직 기사가 정해지지 않은 상태 — 지난 시각이 비정상 흐름인 경우
const UNMATCHED_STATUSES = ['published', 'trading', 'acceptance_pending'];

/**
 * 서비스 일시(로컬) — 날짜·시각 중 하나라도 없거나 읽지 못하면 null.
 */
export function serviceDateTime(serviceDate, serviceTime) {
    if (!serviceDate || !serviceTime) {
        return null;
    }

    const [year, month, day] = String(serviceDate).split('-').map(Number);
    const [hour, minute] = String(serviceTime).split(':').map(Number);

    if ([year, month, day, hour, minute].some((value) => Number.isNaN(value))) {
        return null;
    }

    return new Date(year, month - 1, day, hour, minute, 0, 0);
}

function isSameDay(a, b) {
    return a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();
}

/**
 * 임박 여부 — 오늘 서비스인데 아직 시각이 지나지 않은 운행.
 */
export function isUrgentOrder(order, now = new Date()) {
    const service = serviceDateTime(order?.service_date, order?.service_time);

    return Boolean(service && isSameDay(service, now) && service.getTime() > now.getTime());
}

/**
 * 긴급 여부 — 등록자가 긴급으로 올렸거나, 아직 매칭되지 않은 운행의 서비스 시각이 지난 경우.
 */
export function isPriorityOrder(order, now = new Date()) {
    if (order?.is_priority) {
        return true;
    }

    const service = serviceDateTime(order?.service_date, order?.service_time);

    return Boolean(
        service
        && UNMATCHED_STATUSES.includes(order?.status)
        && isSameDay(service, now)
        && service.getTime() <= now.getTime(),
    );
}
