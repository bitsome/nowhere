<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { apiPublicOrder } from '../api/orders';
import { useAuthStore } from '../stores/auth';

// 공개 공유 화면 — 등록자가 외부(카카오 오픈채팅·카페)에 뿌린 링크로 들어온 사람이 보는 화면.
// 로그인 없이 접근하며, 아직 가입하지 않은 기사도 운행 내용을 확인하고 가입까지 이어지게 한다.
const route = useRoute();
const auth = useAuthStore();

const loading = ref(true);
const notFound = ref(false);
const data = ref(null);

const token = computed(() => String(route.params.token ?? ''));

const row = computed(() => data.value?.row ?? null);
const available = computed(() => data.value?.available === true);

onMounted(async () => {
    try {
        const { data: res } = await apiPublicOrder(token.value);

        data.value = res.data;
    } catch {
        notFound.value = true;
    } finally {
        loading.value = false;
    }
});

// 기사 판단에 필요한 조건만 추린다 — 값이 없는 항목은 카드에서 빠진다
const conditions = computed(() => {
    if (row.value === null) {
        return [];
    }

    const distance = data.value?.distance_km;
    const minutes = data.value?.estimated_duration_minutes;

    return [
        row.value.vehicle && row.value.vehicle !== '-' ? `차량 ${row.value.vehicle}` : null,
        row.value.passengerCount > 0 ? `승객 ${row.value.passengerCount}명` : null,
        row.value.luggageCount > 0 ? `캐리어 ${row.value.luggageCount}` : null,
        row.value.flightNumber ? `항공편 ${row.value.flightNumber}` : null,
        distance ? `약 ${Number(distance)}km` : null,
        minutes ? `약 ${Math.round(minutes / 60 * 10) / 10}시간` : null,
    ].filter(Boolean);
});

// 가입·로그인 후 이 운행으로 돌아오게 한다 — 돌아오지 못하면 기사는 위챗방으로
// 되돌아가 링크를 다시 찾아야 하고, 그 사이에 이탈한다.
// 이 화면의 쿼리에는 의미가 없으므로 경로만 쓴다 (redirect 가 자기 자신에 겹쳐 붙는 것을 막는다)
const returnPath = computed(() => route.path);

// 다음 행동 하나만 크게 — 상황(마감 여부·로그인 여부)에 따라 목적지가 달라진다
const action = computed(() => {
    if (!available.value) {
        return auth.isAuthenticated
            ? { label: '마켓에서 다른 운행 보기', to: { name: 'market' } }
            : { label: '다른 운행 보러 가기', to: { name: 'welcome' } };
    }

    return auth.isAuthenticated
        ? { label: '운행 확인하고 신청하기', to: { name: 'order-detail', params: { id: row.value.id } } }
        : { label: '기사로 가입하고 신청하기', to: { name: 'register', query: { redirect: returnPath.value } } };
});
</script>

<template>
    <div class="sp">
        <header class="sp-nav">
            <router-link class="sp-brand" :to="{ name: auth.isAuthenticated ? 'home' : 'welcome' }">
                <span class="sp-mark">N</span>
                <span class="sp-brand__name">NoWhere</span>
            </router-link>
            <router-link
                v-if="!auth.isAuthenticated"
                class="sp-nav__login"
                :to="{ name: 'login', query: { redirect: returnPath } }"
            >
                로그인
            </router-link>
        </header>

        <main class="sp-body">
            <div v-if="loading" class="sp-card">
                <div class="sk-line sk-line--lg" />
                <div class="sk-line sk-line--md" style="margin-top: 14px" />
                <div class="sk-line sk-line--sm" style="margin-top: 10px" />
            </div>

            <div v-else-if="notFound" class="sp-empty">
                <h1 class="sp-empty__title">운행을 찾을 수 없습니다</h1>
                <p class="sp-empty__desc">
                    링크가 만료되었거나 삭제된 운행입니다.<br />
                    다른 운행을 확인해 보세요.
                </p>
                <router-link
                    class="sp-btn sp-btn--primary"
                    :to="{ name: auth.isAuthenticated ? 'market' : 'welcome' }"
                >
                    {{ auth.isAuthenticated ? '마켓으로 가기' : 'NoWhere 둘러보기' }}
                </router-link>
            </div>

            <template v-else-if="row">
                <p class="sp-eyebrow" :class="{ 'sp-eyebrow--closed': !available }">
                    {{ available ? '기사님 구합니다' : '마감된 운행입니다' }}
                </p>

                <h1 class="sp-route">{{ row.route }}</h1>

                <p class="sp-when">
                    {{ row.pickupDateTime || '일시 미정' }}
                </p>

                <p v-if="row.amount && row.amount !== '-'" class="sp-amount">{{ row.amount }}</p>

                <ul v-if="conditions.length > 0" class="sp-conditions">
                    <li v-for="c in conditions" :key="c" class="sp-conditions__item">{{ c }}</li>
                </ul>

                <div v-if="row.tags && row.tags.length > 0" class="sp-tags">
                    <span v-for="t in row.tags" :key="t" class="sp-tag">#{{ t }}</span>
                </div>

                <p v-if="data.registrant_company" class="sp-company">
                    등록 {{ data.registrant_company }}
                </p>

                <p v-if="available" class="sp-notice">
                    고객 정보(실명·연락처)는 배차 승인 후에 확인할 수 있습니다.
                </p>

                <router-link class="sp-btn sp-btn--primary" :to="action.to">
                    {{ action.label }}
                </router-link>

                <p v-if="!auth.isAuthenticated && available" class="sp-notice">
                    가입하거나 로그인하면 이 운행으로 바로 돌아옵니다.
                </p>

                <router-link
                    v-if="!auth.isAuthenticated"
                    class="sp-btn sp-btn--ghost"
                    :to="{ name: 'welcome' }"
                >
                    NoWhere는 어떤 서비스인가요?
                </router-link>
            </template>
        </main>

        <footer class="sp-footer">
            <span class="sp-mark sp-mark--sm">N</span>
            <span>오늘 받을 운행을 앱이 먼저 골라주는 운행 최적화 플랫폼</span>
        </footer>
    </div>
</template>

<style scoped>
.sp {
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    background: var(--bg);
    color: var(--text);
}

.sp-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    padding: 18px 20px;
}

.sp-brand {
    display: flex;
    align-items: center;
    gap: 9px;
    text-decoration: none;
    color: inherit;
}

.sp-mark {
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
html.dark .sp-mark {
    color: #07120e;
}

.sp-mark--sm {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    font-size: 10px;
}

.sp-brand__name {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: -0.3px;
}

.sp-nav__login {
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    padding: 7px 12px;
    border-radius: 8px;
}

.sp-nav__login:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
    color: var(--text);
}

.sp-body {
    flex: 1;
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    padding: 12px 20px 40px;
    text-align: center;
}

.sp-card {
    padding: 20px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
    text-align: left;
}

.sp-eyebrow {
    margin: 0;
    color: var(--brand);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.sp-eyebrow--closed {
    color: var(--text-muted);
}

.sp-route {
    margin: 12px 0 0;
    font-size: 26px;
    line-height: 1.35;
    font-weight: 700;
    letter-spacing: -0.8px;
}

.sp-when {
    margin: 10px 0 0;
    color: var(--text-muted);
    font-size: 14px;
}

.sp-amount {
    margin: 18px 0 0;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -0.6px;
}

.sp-conditions {
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin: 20px 0 0;
    padding: 18px 0 0;
    border-top: 1px dashed var(--border);
}

.sp-conditions__item {
    padding: 5px 11px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 12px;
}

.sp-tags {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin-top: 10px;
}

.sp-tag {
    color: var(--brand);
    font-size: 12px;
}

.sp-company {
    margin: 16px 0 0;
    color: var(--text-muted);
    font-size: 12px;
}

.sp-notice {
    margin: 14px 0 0;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.7;
}

.sp-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 12px;
    padding: 15px 24px;
    border-radius: 12px;
    border: 1px solid transparent;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition:
        transform 0.12s ease,
        opacity 0.15s ease;
}

.sp-btn:active {
    transform: scale(0.985);
}

.sp-btn--primary {
    margin-top: 24px;
    background: var(--accent);
    border-color: var(--accent);
    color: #ffffff;
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.14);
}

.sp-btn--primary:hover {
    opacity: 0.9;
}

/* 다크 모드 — --accent가 밝은 틸(#63e2b7)로 바뀌어 흰 글자 대비가 ≈1.6:1로 떨어진다.
   앱의 다른 Primary CTA(홈 히어로·등록자 홈·리뷰 탭·랜딩)와 동일하게 어두운 글자로 예외 처리. */
html.dark .sp-btn--primary {
    color: #07120e;
}

.sp-btn--ghost {
    color: var(--text-muted);
    font-weight: 600;
}

.sp-btn--ghost:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
    color: var(--text);
}

.sp-empty {
    padding-top: 60px;
}

.sp-empty__title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
}

.sp-empty__desc {
    margin: 12px 0 0;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.8;
}

.sp-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    padding: 20px;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 11px;
}

@media (max-width: 480px) {
    .sp-route {
        font-size: 22px;
    }
}
</style>
