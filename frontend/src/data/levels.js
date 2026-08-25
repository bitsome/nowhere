// 레벨링 시스템 공용 데이터 — 백엔드 App\Support\Leveling\LevelTable과 동일 기준을 유지한다.
// 기준을 바꿀 때는 반드시 양쪽을 함께 수정한다.

// 레벨 구간별 아이콘 + 그라데이션
export const LEVEL_TIERS = [
    { min: 1, max: 2, icon: 'shield', gradient: 'linear-gradient(135deg, #8b9dc3, #5b6b8c)', label: '신입' },
    { min: 3, max: 4, icon: 'star', gradient: 'linear-gradient(135deg, #36adff, #1f7fd6)', label: '베테랑' },
    { min: 5, max: 6, icon: 'zap', gradient: 'linear-gradient(135deg, #4f6ef7, #2f54eb)', label: '스타' },
    { min: 7, max: 8, icon: 'crown', gradient: 'linear-gradient(135deg, #8b5cf6, #6d28d9)', label: '마스터' },
    { min: 9, max: 10, icon: 'diamond', gradient: 'linear-gradient(135deg, #f7b731, #f2994a)', label: '레전드' },
];

// 레벨 1~10 전체 목록 (백엔드 LevelTable과 동일)
export const LEVEL_LIST = [
    { level: 1, title: '신입 드라이버', minXp: 0 },
    { level: 2, title: '일반 드라이버', minXp: 100 },
    { level: 3, title: '인기 드라이버', minXp: 300 },
    { level: 4, title: '베테랑', minXp: 600 },
    { level: 5, title: '스타 드라이버', minXp: 1000 },
    { level: 6, title: '슈퍼 드라이버', minXp: 1500 },
    { level: 7, title: '마스터 드라이버', minXp: 2200 },
    { level: 8, title: '레전드', minXp: 3200 },
    { level: 9, title: '프리미엄 레전드', minXp: 4600 },
    { level: 10, title: 'VIP 마스터', minXp: 6400 },
];

// XP 획득 규칙 (백엔드 XP 부여 포인트와 동일)
export const XP_RULES = [
    { label: '운행 가져오기 (수락)', xp: 20 },
    { label: '운행 등록', xp: 10 },
    { label: '운행 완료', xp: 50 },
    { label: '정산 완료', xp: 30 },
    { label: '커뮤니티 글 작성', xp: 5 },
    { label: '커뮤니티 댓글 작성', xp: 2 },
    { label: '커뮤니티 글 좋아요 받기', xp: 1 },
];

export const tierForLevel = (level) =>
    LEVEL_TIERS.find((t) => level >= t.min && level <= t.max) ?? LEVEL_TIERS[0];

// 레벨 티어 아이콘 이름 — utils/icons.js 매핑의 키를 반환한다 (폰트아이콘 방식)
export const iconSvg = (icon) => icon;
