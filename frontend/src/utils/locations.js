// 출발지·도착지 선택 옵션과 API 파라미터 변환 — 마켓 필터와 빠른 매칭(홈)이 같은 목록을 쓴다.
// 값은 Order.pickup_location / dropoff_location 문자열과 비교되므로 표기를 함부로 바꾸지 말 것.

export const CITY_OPTIONS = [
    { label: '전체', value: '' },
    { label: '공항', value: 'airport' },
    { label: '서울', value: 'seoul' },
    { label: '인천', value: 'incheon' },
    { label: '경기도', value: 'gyeonggi' },
];

export const CITY_LABELS = { seoul: '서울', incheon: '인천', gyeonggi: '경기도', airport: '공항' };

export const SEOUL_DISTRICTS = ['강남구', '강동구', '강북구', '강서구', '관악구', '광진구', '구로구', '금천구', '노원구', '도봉구', '동대문구', '동작구', '마포구', '서대문구', '서초구', '성동구', '성북구', '송파구', '양천구', '영등포구', '용산구', '은평구', '종로구', '중구', '중랑구'];
export const INCHEON_DISTRICTS = ['중구', '미추홀구', '연수구', '남동구', '부평구', '계양구', '서구', '동구'];
export const GYEONGGI_DISTRICTS = ['수원', '성남', '분당', '판교', '고양', '일산', '부천', '안양', '용인', '화성', '평택', '남양주', '김포', '파주', '의정부', '시흥', '구리', '하남'];
export const AIRPORTS = ['인천공항', '김포공항'];

// 시 선택 시 표시할 구 목록 (전체 포함)
export const districtSelectOptions = (city) => {
    const list = city === 'seoul' ? SEOUL_DISTRICTS
        : city === 'incheon' ? INCHEON_DISTRICTS
        : city === 'gyeonggi' ? GYEONGGI_DISTRICTS
        : city === 'airport' ? AIRPORTS
        : [];
    return [
        { label: '전체', value: '' },
        ...list.map((d) => ({ label: d, value: d })),
    ];
};

// 공항 3단 세부(구) 목록 — 인천공항: T1/T2, 김포공항: 국내/국제
export const detailSelectOptions = (city, district) => {
    if (city !== 'airport') {
        return [];
    }
    const details = district === '인천공항' ? ['T1', 'T2'] : district === '김포공항' ? ['국내', '국제'] : [];
    return [
        { label: '전체', value: '' },
        ...details.map((d) => ({ label: d, value: d })),
    ];
};

// 출발/도착 API 파라미터 — 시/구(세부) 선택에 따라 매칭 문자열 생성
export const locationParam = (city, district, detail = '') => {
    if (city === 'airport') {
        if (!district) {
            return '공항';
        }
        if (district === '인천공항') {
            // 인천공항 전체는 '인천공항 T1/T2'·'인천국제공항' 모두 잡도록 와일드카드 사용
            return detail ? `인천공항 ${detail}` : '인천%공항';
        }
        return detail ? `김포공항 ${detail}` : '김포공항';
    }
    if (district) {
        return district;
    }
    return CITY_LABELS[city] ?? '';
};

// 저장된 지역 값 표시용 — '인천%공항' 와일드카드를 보기 좋게 되돌린다
export const displayLocation = (value) => String(value ?? '').replace('%', '');
