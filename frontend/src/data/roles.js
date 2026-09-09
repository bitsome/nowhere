// 역할(Role) 공용 데이터 — 백엔드 App\Models\User 상수·roleLabels()와 동일 기준을 유지한다.
// 기준을 바꿀 때는 반드시 양쪽을 함께 수정한다. (Q-4 역할/권한 정리)

export const ROLE_SUPER_ADMIN = 'Super Admin';
export const ROLE_ADMIN = 'Admin';
export const ROLE_OPERATOR = 'Operator';
export const ROLE_DRIVER = 'Driver';
export const ROLE_CUSTOMER = 'Customer'; // 운행 등록자(마켓 이용)

// 관리자(운영) 전용 화면·기능에 접근할 수 있는 역할
export const ADMIN_ROLES = [ROLE_ADMIN, ROLE_SUPER_ADMIN];

// 역할 한글 라벨 — 백엔드 User::roleLabels()와 동일
export const ROLE_LABELS = {
    [ROLE_SUPER_ADMIN]: '최고 관리자',
    [ROLE_ADMIN]: '관리자',
    [ROLE_OPERATOR]: '운영자',
    [ROLE_DRIVER]: '기사',
    [ROLE_CUSTOMER]: '등록자',
};

// 역할 라벨 조회 — 알 수 없는 값은 원문 그대로 반환
export const roleLabel = (role) => ROLE_LABELS[role] ?? role ?? '-';
