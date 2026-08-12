// 대화방 시간·날짜 표시 헬퍼
// 서버 created_at은 diffForHumans(상대시간, "5 minutes ago")이고,
// 배포 후에는 created_at_iso(ISO)가 추가로 온다 — 둘 다 지원한다.

const RELATIVE = [
    [/^just now$/i, 0],
    [/^(\d+) seconds? ago$/i, (n) => Number(n) / 3600],
    [/^(\d+) minutes? ago$/i, (n) => Number(n) / 60],
    [/^(\d+) hours? ago$/i, (n) => Number(n)],
    [/^(\d+) days? ago$/i, (n) => Number(n) * 24],
    [/^(\d+) weeks? ago$/i, (n) => Number(n) * 24 * 7],
    [/^(\d+) months? ago$/i, (n) => Number(n) * 24 * 30],
];

// ISO/상대시간 → Date (상대시간은 대략 추정)
export function getChatTimestamp(val) {
    if (!val) return null;

    const parsed = new Date(val);

    if (!isNaN(parsed.getTime())) {
        return parsed;
    }

    const s = String(val);

    for (const [pattern, toHours] of RELATIVE) {
        const match = s.match(pattern);

        if (match) {
            const hours = typeof toHours === 'function' ? toHours(match[1]) : toHours;

            return new Date(Date.now() - hours * 3600 * 1000);
        }
    }

    return null;
}

export function isSameDay(a, b) {
    return a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();
}

// "오전 9:30" / "오후 2:30"
export function formatClock(ts) {
    if (!ts) return '';

    const hour = ts.getHours();
    const minutes = String(ts.getMinutes()).padStart(2, '0');
    const display = hour % 12 === 0 ? 12 : hour % 12;

    return `${hour < 12 ? '오전' : '오후'} ${display}:${minutes}`;
}

// "어제" / "8월 12일" / (다른 해면 "2025년 12월 31일")
export function formatDayLabel(ts) {
    if (!ts) return '';

    const now = new Date();
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    if (isSameDay(ts, yesterday)) {
        return '어제';
    }

    if (ts.getFullYear() === now.getFullYear()) {
        return `${ts.getMonth() + 1}월 ${ts.getDate()}일`;
    }

    return `${ts.getFullYear()}년 ${ts.getMonth() + 1}월 ${ts.getDate()}일`;
}
