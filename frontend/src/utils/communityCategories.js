// 커뮤니티 카테고리 — 디자인(v7 커뮤니티) 기준. icon은 utils/icons.js의 아이콘 이름.
export const COMMUNITY_CATEGORIES = [
    { key: 'free', label: '자유', icon: 'chat' },
    { key: 'airport', label: '공항정보', icon: 'airplane' },
    { key: 'route', label: '운행정보', icon: 'map' },
    { key: 'region', label: '지역게시판', icon: 'location' },
    { key: 'car', label: '차량정보', icon: 'car' },
    { key: 'money', label: '수익·노하우', icon: 'cash' },
    { key: 'shop', label: '쇼핑·중고거래', icon: 'cart' },
    { key: 'survey', label: '설문조사', icon: 'survey' },
    { key: 'food', label: '여행지 맛집', icon: 'food' },
];

export const categoryOf = (key) => COMMUNITY_CATEGORIES.find((c) => c.key === key) ?? COMMUNITY_CATEGORIES[0];
