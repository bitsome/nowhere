import { computed, isRef } from 'vue';
import { getChatTimestamp, isSameDay, formatDayLabel } from '../utils/chatTime';

const TYPE_LABELS = {
    approval: '승인 요청',
    time_change: '시간 변경 요청',
    route_change: '경로 변경 요청',
    payment_change: '요금 협의 요청',
    cancel: '운행 취소 요청',
};

export const typeLabelOf = (type) => TYPE_LABELS[type] ?? '요청';

const minuteKeyOf = (msg) => {
    const ts = getChatTimestamp(msg.created_at_iso ?? msg.created_at);

    return ts ? `${ts.getFullYear()}-${ts.getMonth()}-${ts.getDate()}-${ts.getHours()}-${ts.getMinutes()}` : null;
};

/**
 * 채팅 메시지 목록 → 렌더 행 변환 (ChatThread·OrderDetailChat 공용).
 * 날짜 구분선, 요청 이벤트 유형 구분, 같은 상대·같은 분 연속 메시지 그룹
 * (아바타·이름·말풍선 꼬리 노출 규칙)을 계산한다.
 *
 * @param {import('vue').Ref<Array<object>> | Array<object>} messages 메시지 ref 또는 반응형 배열
 */
export function useChatRows(messages) {
    return computed(() => {
        const list = isRef(messages) ? messages.value : messages;
        const rows = [];
        let prevDayKey = null;
        let prevUserId = null;
        let prevMinuteKey = null;
        let prevTypeLabel = '';
        const now = new Date();

        for (let i = 0; i < list.length; i++) {
            const msg = list[i];
            const next = list[i + 1];
            const ts = getChatTimestamp(msg.created_at_iso ?? msg.created_at);
            const dayKey = ts ? `${ts.getFullYear()}-${ts.getMonth()}-${ts.getDate()}` : null;
            const minuteKey = minuteKeyOf(msg);
            const showSep = Boolean(ts && dayKey && dayKey !== prevDayKey);

            // 요청 이벤트는 말풍선 그룹에 섞이지 않도록 항상 단독 행으로 처리한다
            const isEvent = (msg.type ?? 'text') !== 'text';
            const typeLabel = isEvent ? typeLabelOf(msg.type) : '';
            const showTypeSep = isEvent && typeLabel !== prevTypeLabel;
            const isGroupStart = isEvent || !(msg.user_id === prevUserId && minuteKey && minuteKey === prevMinuteKey);
            const isGroupEnd = isEvent || !(next && next.user_id === msg.user_id && minuteKey && minuteKeyOf(next) === minuteKey);

            rows.push({
                msg,
                showSep,
                dayLabel: showSep ? (isSameDay(ts, now) ? '오늘' : formatDayLabel(ts)) : '',
                showTypeSep,
                typeLabel,
                isFirst: isGroupStart,
                isLast: isGroupEnd,
                isEvent,
            });

            if (dayKey) {
                prevDayKey = dayKey;
            }
            prevUserId = msg.user_id;
            prevMinuteKey = minuteKey;
            prevTypeLabel = typeLabel;
        }

        return rows;
    });
}
