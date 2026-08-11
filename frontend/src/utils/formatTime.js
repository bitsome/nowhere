// 채팅 시간 포맷 — API가 영문 상대시간("10 minutes ago")을 반환하므로 한글로 변환
export function formatTime(val) {
    if (!val) return '';

    const s = String(val);

    const map = [
        [/^just now$/i, '방금'],
        [/^(\d+) second(s)? ago$/i, (_, n) => `${n}초 전`],
        [/^(\d+) minute(s)? ago$/i, (_, n) => `${n}분 전`],
        [/^(\d+) hour(s)? ago$/i, (_, n) => `${n}시간 전`],
        [/^(\d+) day(s)? ago$/i, (_, n) => `${n}일 전`],
        [/^(\d+) week(s)? ago$/i, (_, n) => `${n}주 전`],
        [/^(\d+) month(s)? ago$/i, (_, n) => `${n}개월 전`],
    ];

    for (const [pattern, replacement] of map) {
        const match = s.match(pattern);
        if (match) {
            return typeof replacement === 'function' ? replacement(...match) : replacement;
        }
    }

    // ISO 날짜 대응
    const d = new Date(val);
    if (!isNaN(d.getTime())) {
        return `${d.getMonth() + 1}월 ${d.getDate()}일`;
    }

    return s;
}
