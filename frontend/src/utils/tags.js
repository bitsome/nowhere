/**
 * 운행 어필·매칭용 공용 태그 프리셋.
 * - 운행 등록: 등록자가 운행을 설명하는 태그를 붙인다 (더욱 다양한 어필).
 * - 빠른 매칭: 기사가 원하는 운행 태그를 다중 선택한다 (운행 태그와 겹치면 매칭).
 */
export const ORDER_TAGS = [
    // 공항/지역
    { label: '인천공항', value: '인천공항' },
    { label: '김포공항', value: '김포공항' },
    { label: '강남', value: '강남' },
    { label: '홍대', value: '홍대' },
    { label: '서울역', value: '서울역' },
    { label: '잠실', value: '잠실' },
    { label: '판교', value: '판교' },
    { label: '수원', value: '수원' },
    // 시간 특성
    { label: '야간', value: '야간' },
    { label: '새벽', value: '새벽' },
    { label: '주말', value: '주말' },
    // 운행 특성
    { label: '장거리', value: '장거리' },
    { label: '왕복', value: '왕복' },
    { label: '비즈니스', value: '비즈니스' },
    { label: '여행', value: '여행' },
    { label: '가족', value: '가족' },
    { label: '단체', value: '단체' },
    { label: '반려동물', value: '반려동물' },
    { label: '캐리어 많음', value: '캐리어 많음' },
    // 고객/편의
    { label: '카시트', value: '카시트' },
    { label: '와이파이', value: '와이파이' },
    { label: '무료대기', value: '무료대기' },
    { label: '계산서', value: '계산서' },
    { label: '현금영수증', value: '현금영수증' },
];
