<script setup>
/**
 * CustomerHome — 등록자(사업자)용 홈.
 * 기사 홈(추천/가져오기) 대신 운행 등록·승인·정산 관리로 바로 이어준다.
 */
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import UiCard from '../../components/ui/UiCard.vue';
import BaseIcon from '../../components/common/BaseIcon.vue';

const router = useRouter();
const auth = useAuthStore();

const MENU = [
    { label: '등록한 운행', desc: '등록한 운행 · 승인 · 정산', icon: 'my-market', name: 'my-market' },
    { label: '처리할 일', desc: '가져오기 승인 · 요금 제안 · 요청', icon: 'inbox', name: 'actions' },
    { label: '마켓 둘러보기', desc: '등록된 운행과 시세 확인', icon: 'market', name: 'market' },
    { label: '커뮤니티', desc: '기사 정보 · 거래', icon: 'community', name: 'community' },
];
</script>

<template>
    <div class="customer-home">
        <section class="customer-hero">
            <span class="customer-hero__eyebrow">{{ auth.user?.name }}님</span>
            <h1 class="customer-hero__title">등록한 운행을<br />관리하세요</h1>
            <p class="customer-hero__desc">
                운행을 등록하면 조건에 맞는 기사에게 추천되고, 가져오기 신청·요금 제안을 여기에서 승인할 수 있어요.
            </p>
            <button type="button" class="customer-hero__cta" @click="router.push({ name: 'order-create', query: { form: '1' } })">
                + 새 운행 등록
                <BaseIcon name="arrow-forward" :size="15" />
            </button>
        </section>

        <div class="customer-menu">
            <UiCard
                v-for="item in MENU"
                :key="item.name"
                tag="a"
                hover
                class="customer-menu__item"
                @click.prevent="router.push({ name: item.name })"
            >
                <b class="customer-menu__icon"><BaseIcon :name="item.icon" :size="22" /></b>
                <span class="customer-menu__text">
                    <strong>{{ item.label }}</strong>
                    <em>{{ item.desc }}</em>
                </span>
                <BaseIcon name="chevron-down" :size="16" class="customer-menu__arrow" />
            </UiCard>
        </div>

        <p class="customer-note">
            가져오기(운행 수행)·요금 제안·매칭 설정은 기사 계정에서만 가능합니다.
        </p>
    </div>
</template>

<style scoped>
/* 등록자 홈 — 기사 홈과 동일한 여백 규칙(칩/콘텐츠 8px)을 따른다 */
.customer-home {
    display: flex;
    flex-direction: column;
}

/* 히어로 — 오늘 받을 운행 없음 상태와 같은 규격 */
.customer-hero {
    padding: 18px 0 6px;
}
.customer-hero__eyebrow {
    font-size: 11px;
    font-weight: 700;
    color: var(--brand);
}
.customer-hero__title {
    margin: 6px 0 0;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.35;
}
.customer-hero__desc {
    margin: 8px 0 0;
    font-size: 11px;
    line-height: 1.7;
    color: var(--text-muted);
}
.customer-hero__cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 14px;
    padding: 0 18px;
    height: 44px;
    border: 0;
    border-radius: 12px;
    background: var(--accent);
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
}

/* 다크 모드 — --accent가 밝은 틸(#63e2b7)로 바뀌어 흰 글자 대비가 약해짐.
   라이트는 어두운(#1f1f1f) 채움이라 흰 글자 유지, 다크만 채움 위 어두운 글자(#07120e) 표준 적용 */
html.dark .customer-hero__cta {
    color: #07120e;
}

/* 빠른 메뉴 — 한 줄에 하나씩 (홈 빠른 메뉴의 확장형) */
.customer-menu {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 16px;
}
.customer-menu__item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px var(--card-pad);
}
.customer-menu__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: var(--brand-soft);
    color: var(--brand);
    flex-shrink: 0;
}
.customer-menu__text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    flex: 1;
}
.customer-menu__text strong {
    font-size: 12px;
    font-weight: 700;
}
.customer-menu__text em {
    font-style: normal;
    font-size: 10px;
    color: var(--text-muted);
}
.customer-menu__arrow {
    transform: rotate(-90deg);
    color: var(--text-muted);
    flex-shrink: 0;
}

/* 안내 — 회색 소문 */
.customer-note {
    margin: 18px 0 0;
    padding: 10px 2px;
    font-size: 10px;
    color: var(--text-muted);
}
</style>
