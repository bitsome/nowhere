<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { ROLE_CUSTOMER } from '../data/roles';

// 공개 랜딩 — 비로그인 방문자가 제품을 처음 만나는 화면.
// 홍보 링크가 가리키는 곳이므로 '무엇을 해주는 서비스인지'와 '다음 행동'만 남긴다.
const steps = [
    { k: '추천', d: '앱이 오늘 받을 운행을 먼저 골라줍니다' },
    { k: '신청', d: '마음에 들면 바로 신청' },
    { k: '승인', d: '등록자가 승인하면 운행 확정' },
    { k: '운행', d: '채팅으로 소통하며 운행' },
    { k: '정산', d: '완료 후 수수료 차감하고 지급' },
];

const reasons = [
    '차량 조건 일치',
    '현재 위치에서 가까움',
    '다음 운행과 동선 좋음',
    '시간 여유 충분',
];

// 랜딩은 뷰포트 1장이 아니라 스크롤되는 페이지다 — 스크롤 잠금이 남아 있으면 풀어준다.
onMounted(() => {
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
});

onBeforeUnmount(() => {
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="lp">
        <header class="lp-nav">
            <div class="lp-brand">
                <span class="lp-mark">N</span>
                <span class="lp-brand__name">NoWhere</span>
            </div>
            <router-link class="lp-nav__login" :to="{ name: 'login' }">로그인</router-link>
        </header>

        <section class="lp-hero">
            <p class="lp-eyebrow">운행 최적화 플랫폼</p>
            <h1 class="lp-title">
                오늘 받을 운행,<br />
                고민하지 마세요
            </h1>
            <p class="lp-sub">
                조건·동선·선호도로 <strong>앱이 먼저 골라</strong> 드립니다.<br />
                운행을 많이 보여주지 않습니다. 필요한 것만 보여드립니다.
            </p>

            <div class="lp-cta">
                <router-link class="lp-btn lp-btn--primary" :to="{ name: 'register' }">
                    기사로 무료 시작하기
                </router-link>
                <router-link class="lp-btn lp-btn--ghost" :to="{ name: 'login' }">로그인</router-link>
            </div>
            <p class="lp-note">기사님에게는 비용을 받지 않습니다</p>
        </section>

        <section class="lp-demo">
            <div class="lp-card">
                <div class="lp-card__head">
                    <span class="lp-score">조건 96%</span>
                    <span class="lp-card__time">오늘 13:30</span>
                </div>

                <p class="lp-route">인천공항 T1 <span class="lp-arrow">→</span> 강남</p>
                <p class="lp-card__meta">3명 · 캐리어 2 · 약 62km</p>

                <ul class="lp-reasons">
                    <li v-for="r in reasons" :key="r" class="lp-reasons__item">
                        <svg class="lp-check" viewBox="0 0 16 16" aria-hidden="true">
                            <path
                                d="M3.5 8.5l3 3 6-7"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        {{ r }}
                    </li>
                </ul>

                <div class="lp-card__action">운행 확인하고 신청하기</div>
            </div>
            <p class="lp-demo__caption">추천 카드에는 조건 일치율과 근거가 함께 표시됩니다</p>
        </section>

        <section class="lp-flow">
            <h2 class="lp-h2">이렇게 흐릅니다</h2>
            <ol class="lp-steps">
                <li v-for="(s, i) in steps" :key="s.k" class="lp-step">
                    <span class="lp-step__num">{{ i + 1 }}</span>
                    <span class="lp-step__body">
                        <span class="lp-step__k">{{ s.k }}</span>
                        <span class="lp-step__d">{{ s.d }}</span>
                    </span>
                </li>
            </ol>
        </section>

        <section class="lp-why">
            <h2 class="lp-h2">왜 다른가</h2>
            <div class="lp-why__grid">
                <div class="lp-why__item">
                    <h3>고르는 일을 줄입니다</h3>
                    <p>수십 건을 비교하게 하지 않고, 지금 기사님께 맞는 운행만 먼저 보여드립니다.</p>
                </div>
                <div class="lp-why__item">
                    <h3>근거를 함께 보여줍니다</h3>
                    <p>왜 이 운행인지 조건 일치율과 체크 항목으로 확인하고 판단하실 수 있습니다.</p>
                </div>
                <div class="lp-why__item">
                    <h3>정산까지 한 곳에서</h3>
                    <p>신청·승인·운행·완료·정산이 한 흐름으로 이어집니다. 기록도 남습니다.</p>
                </div>
            </div>
        </section>

        <section class="lp-registrant">
            <h2 class="lp-h2">운행을 맡기시는 분께</h2>
            <p class="lp-registrant__desc">
                운행을 등록하시면 조건에 맞는 기사님이 신청합니다. 승인부터 정산까지 투명하게 관리하세요.
            </p>
            <!-- 등록자 후보가 기사로 잘못 가입하지 않도록 역할을 함께 넘긴다 -->
            <router-link class="lp-btn lp-btn--outline" :to="{ name: 'register', query: { role: ROLE_CUSTOMER } }">
                운행 등록하러 가기
            </router-link>
        </section>

        <footer class="lp-footer">
            <span class="lp-mark lp-mark--sm">N</span>
            <span>NoWhere — 오늘 받을 운행을 앱이 먼저 골라주는 운행 최적화 플랫폼</span>
        </footer>
    </div>
</template>

<style scoped>
.lp {
    min-height: 100dvh;
    background: var(--bg);
    color: var(--text);
    padding-bottom: 48px;
    overflow-x: hidden;
}

/* ── 상단 ── */
.lp-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 880px;
    margin: 0 auto;
    padding: 18px 20px;
}

.lp-brand {
    display: flex;
    align-items: center;
    gap: 9px;
}

.lp-mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 9px;
    background: var(--brand-gradient);
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
}

/* 다크 — 그라디언트가 밝은 틸로 바뀌어 흰 글자 대비가 ≈1.6:1로 떨어짐 → 앱 표준 어두운 글자 */
html.dark .lp-mark {
    color: #07120e;
}

.lp-mark--sm {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    font-size: 10px;
}

.lp-brand__name {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: -0.3px;
}

.lp-nav__login {
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    padding: 7px 12px;
    border-radius: 8px;
}

.lp-nav__login:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
    color: var(--text);
}

/* ── 히어로 ── */
.lp-hero {
    max-width: 880px;
    margin: 0 auto;
    padding: 40px 20px 52px;
    text-align: center;
}

.lp-eyebrow {
    margin: 0;
    color: var(--brand);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.lp-title {
    margin: 16px 0 0;
    font-size: 40px;
    line-height: 1.28;
    font-weight: 700;
    letter-spacing: -1.2px;
}

.lp-sub {
    margin: 18px 0 0;
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.75;
}

.lp-sub strong {
    color: var(--text);
    font-weight: 700;
}

.lp-cta {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    margin-top: 32px;
}

.lp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 240px;
    padding: 15px 26px;
    border-radius: 12px;
    border: 1px solid transparent;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition:
        transform 0.12s ease,
        opacity 0.15s ease,
        background 0.15s ease;
}

.lp-btn:active {
    transform: scale(0.985);
}

.lp-btn--primary {
    background: var(--accent);
    border-color: var(--accent);
    color: #ffffff;
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.14);
}

.lp-btn--primary:hover {
    opacity: 0.9;
}

.lp-btn--ghost {
    color: var(--text-muted);
    font-weight: 600;
}

.lp-btn--ghost:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
    color: var(--text);
}

.lp-btn--outline {
    border-color: var(--border-strong);
    background: var(--surface);
    color: var(--text);
}

.lp-btn--outline:hover {
    border-color: var(--text);
}

.lp-note {
    margin: 16px 0 0;
    color: var(--text-muted);
    font-size: 12px;
}

/* ── 추천 카드 미리보기 ── */
.lp-demo {
    max-width: 420px;
    margin: 0 auto;
    padding: 0 20px;
}

.lp-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 18px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.lp-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.lp-score {
    padding: 2px 8px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 11px;
    font-weight: 700;
}

.lp-card__time {
    color: var(--text-muted);
    font-size: 11px;
}

.lp-route {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -0.4px;
}

.lp-arrow {
    margin: 0 6px;
    color: var(--text-muted);
    font-weight: 400;
}

.lp-card__meta {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-size: 12px;
}

.lp-reasons {
    list-style: none;
    margin: 14px 0 0;
    padding: 14px 0 0;
    border-top: 1px dashed var(--border);
    display: grid;
    gap: 7px;
}

.lp-reasons__item {
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--text-muted);
    font-size: 12px;
}

.lp-check {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
    color: var(--status-completed);
}

.lp-card__action {
    margin-top: 16px;
    padding: 13px;
    border-radius: 10px;
    background: var(--accent);
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
}

.lp-demo__caption {
    margin: 12px 0 0;
    color: var(--text-muted);
    font-size: 11px;
    text-align: center;
}

/* 다크 모드 — --accent가 밝은 틸(#63e2b7)로 바뀌어 흰 글자 대비가 ≈1.6:1로 떨어진다.
   앱의 다른 Primary CTA(홈 히어로·등록자 홈·리뷰 탭)와 동일하게 어두운 글자로 예외 처리. */
html.dark .lp-btn--primary,
html.dark .lp-card__action {
    color: #07120e;
}

/* ── 공통 섹션 ── */
.lp-h2 {
    margin: 0 0 18px;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.lp-flow,
.lp-why,
.lp-registrant {
    max-width: 880px;
    margin: 0 auto;
    padding: 56px 20px 0;
}

/* ── 흐름 ── */
.lp-steps {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 10px;
}

.lp-step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
}

.lp-step__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    border-radius: 50%;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 11px;
    font-weight: 700;
}

.lp-step__body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.lp-step__k {
    font-size: 13px;
    font-weight: 700;
}

.lp-step__d {
    color: var(--text-muted);
    font-size: 12px;
}

/* ── 왜 다른가 ── */
.lp-why__grid {
    display: grid;
    gap: var(--card-gap);
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.lp-why__item {
    padding: 18px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
}

.lp-why__item h3 {
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 700;
}

.lp-why__item p {
    margin: 0;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.7;
}

/* ── 등록자 ── */
.lp-registrant__desc {
    margin: 0 0 20px;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.75;
}

/* ── 하단 ── */
.lp-footer {
    display: flex;
    align-items: center;
    gap: 9px;
    max-width: 880px;
    margin: 64px auto 0;
    padding: 22px 20px 0;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 11px;
}

@media (max-width: 480px) {
    .lp-title {
        font-size: 30px;
    }

    .lp-hero {
        padding-top: 28px;
        padding-bottom: 40px;
    }

    .lp-btn {
        width: 100%;
        min-width: 0;
    }
}
</style>
