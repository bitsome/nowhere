/**
 * 카드 일시 가독성 — 오늘/내일/모레는 상대 라벨로, 그 이후는 원래 날짜 텍스트를 유지한다.
 *
 * @param {string} [dateText]  백엔드 포맷 "n/j(요일)" (예: 9/2(수))
 * @param {string} [sortDate]  ISO 날짜 "YYYY-MM-DD" (상대 계산용, 없으면 원본 유지)
 * @returns {string}
 */
export function relativeDateLabel(dateText, sortDate) {
    if (!sortDate) {
        return dateText ?? '';
    }

    const target = new Date(`${sortDate}T00:00:00`);
    if (Number.isNaN(target.getTime())) {
        return dateText ?? '';
    }

    const now = new Date();
    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const dayDiff = Math.round((target - todayStart) / 86400000);

    if (dayDiff === 0) {
        return '오늘';
    }
    if (dayDiff === 1) {
        return '내일';
    }
    if (dayDiff === 2) {
        return '모레';
    }

    return dateText ?? '';
}
