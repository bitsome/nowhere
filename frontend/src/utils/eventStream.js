/**
 * SSE(EventSource) 연결 헬퍼 — 백엔드 실시간 신호를 받아 콜백을 호출한다.
 *
 * 백엔드: GET /api/events (App\Http\Controllers\Api\StreamController)
 * - EventSource는 Authorization 헤더를 보낼 수 없어 토큰을 쿼리로 전달한다.
 * - 서버는 20초 후 스트림을 닫고 retry:10000으로 재연결을 유도한다.
 */

export function connectEventStream(onRefresh) {
    const token = localStorage.getItem('auth_token');

    if (!token || !('EventSource' in window)) {
        return null;
    }

    // once=1: 서버가 상태 스냅샷만 보내고 즉시 연결을 닫는다.
    // Windows의 PHP 내장 서버는 멀티 워커(포크)를 지원하지 않아,
    // 오래 유지되는 SSE가 단일 워커를 점유해 다른 요청을 블로킹하는 문제를 방지한다.
    // EventSource가 5초 뒤 자동 재연결하며, onerror의 재연결과 함께 실시간성을 유지한다.
    const source = new EventSource(`/api/events?token=${encodeURIComponent(token)}&once=1`);

    // 알림/채팅 안 읽음 수 변화 이벤트 — 어느 쪽이든 전체 체크에 재사용
    source.addEventListener('notification', () => onRefresh());
    source.addEventListener('message', () => onRefresh());
    source.addEventListener('open', () => onRefresh());

    // 기사 상태 변경 이벤트 — 운영자/관리자 화면 갱신용
    source.addEventListener('driver', () => {
        window.dispatchEvent(new CustomEvent('app:drivers-refresh'));
    });

    source.onerror = () => {
        source.close();
        setTimeout(() => {
            connectEventStream(onRefresh);
        }, 5000);
    };

    return source;
}
