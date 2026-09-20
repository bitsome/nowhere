<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useUiStore } from '../stores/ui';
import { apiDriverStats } from '../api/driver';
import { roleLabel, ROLE_CUSTOMER } from '../data/roles';
import UiCard from '../components/ui/UiCard.vue';
import UiSection from '../components/ui/UiSection.vue';
import UiListRow from '../components/ui/UiListRow.vue';
import LevelBadge from '../components/common/LevelBadge.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'MoreView' });

const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();

// 프로필 카드 — 이름·역할·레벨 표시 (라벨은 data/roles.js 단일 소스)
const roleLabelText = computed(() => roleLabel(auth.user?.role));

const initial = computed(() => (auth.user?.name ?? 'N').charAt(0));
const level = computed(() => auth.user?.level ?? null);

// 기사 전용 — 오늘 통계·차량 정보는 드라이버에게만 노출
const isDriver = computed(() => auth.user?.role === 'Driver');
const isCustomer = computed(() => auth.user?.role === ROLE_CUSTOMER);

// 오늘 통계 — 기사 통계 API (진행중/완료/수익)
const todayStats = ref(null);

const loadStats = async () => {
    try {
        const { data } = await apiDriverStats();
        todayStats.value = data.data;
    } catch {
        todayStats.value = null;
    }
};

onMounted(() => {
    if (isDriver.value) {
        loadStats();
    }
});

const formatWon = (v) => Number(v ?? 0).toLocaleString('ko-KR');

const go = (target) => {
    if (target === 'more:my-posts') {
        ui.communityMyPostsOnly = true;
        router.push({ name: 'community' });
        ui.emitAction('community:reload');
        return;
    }

    // 기사용 — '내 마켓' 화면에서 기사가 실제로 쓰는 탭(보낸 요청)을 바로 연다
    if (target === 'my-market:sent') {
        router.push({ name: 'my-market', query: { tab: '요청', cat: 'sent' } });
        return;
    }

    router.push({ name: target });
};

const logout = async () => {
    await auth.logout();
    router.push({ name: 'login' });
};
</script>

<template>
    <div class="more-page page-shell">
        <!-- 프로필 히어로 -->
        <div class="more-hero">
            <div class="more-hero__top">
                <div class="more-hero__avatar">{{ initial }}</div>
                <div class="more-hero__who">
                    <div class="more-hero__name-row">
                        <strong>{{ auth.user?.name }}</strong>
                        <LevelBadge v-if="level" :level="level.level" size="sm" />
                        <span v-if="auth.user?.is_vip" class="more-hero__vip">VIP</span>
                    </div>
                    <div class="more-hero__role">{{ roleLabelText }}</div>
                </div>
                <button type="button" class="more-hero__edit" @click="go('profile')">회원정보</button>
            </div>

            <div v-if="level" class="more-hero__xp">
                <div class="more-hero__xp-head">
                    <span>{{ level.title }}</span>
                    <span>누적 XP {{ formatWon(auth.user?.xp ?? 0) }}점</span>
                </div>
                <div class="more-hero__bar">
                    <div class="more-hero__bar-fill" :style="{ width: `${level.progress}%` }" />
                </div>
            </div>
        </div>

        <!-- 오늘 통계 — 기사 전용 데이터(드라이버 통계 API)이므로 기사에게만 노출 -->
        <div v-if="isDriver" class="more-stats">
            <div class="more-stats__card">
                <span class="more-stats__label">진행 중</span>
                <span class="more-stats__value">{{ todayStats?.active_count ?? 0 }}</span>
                <span class="more-stats__unit">건</span>
            </div>
            <div class="more-stats__card">
                <span class="more-stats__label">오늘 완료</span>
                <span class="more-stats__value">{{ todayStats?.today_completed ?? 0 }}</span>
                <span class="more-stats__unit">건</span>
            </div>
            <div class="more-stats__card">
                <span class="more-stats__label">오늘 수익</span>
                <span class="more-stats__value">{{ formatWon(todayStats?.today_income ?? 0) }}</span>
                <span class="more-stats__unit">원</span>
            </div>
        </div>

        <!-- 운행 관리 — 역할에 따라 나눈다. 기사는 '받은 운행'(수행), 그 외는 '등록한 운행'(등록) -->
        <UiSection title="운행 관리">
            <UiCard :padded="false" class="more-list">
                <UiListRow v-if="isDriver" tag="button" icon="order-create" arrow @click="go('order-create')">받은 운행</UiListRow>
                <UiListRow v-if="isDriver" tag="button" icon="my-market" arrow @click="go('my-market:sent')">보낸 요청</UiListRow>
                <UiListRow v-if="!isDriver" tag="button" icon="my-market" arrow @click="go('my-market')">등록한 운행</UiListRow>
                <UiListRow tag="button" icon="history" arrow @click="go('history')">지난 운행</UiListRow>
                <UiListRow tag="button" icon="heart" arrow @click="go('order-favorites')">찜한 운행</UiListRow>
                <UiListRow v-if="isDriver" tag="button" icon="coin" arrow @click="go('settlement')">정산</UiListRow>
                <UiListRow v-if="isCustomer" tag="button" icon="cash" arrow @click="go('registrant-settlement')">정산·입금</UiListRow>
                <UiListRow tag="button" icon="cash" arrow @click="go('actions')">처리할 일</UiListRow>
            </UiCard>
        </UiSection>

        <!-- 커뮤니티 — 내가 등록한 글 등 커뮤니티 관련 항목 (커뮤니티 탭은 하단 메뉴로 이동) -->
        <UiSection title="커뮤니티">
            <UiCard :padded="false" class="more-list">
                <UiListRow tag="button" icon="my-posts" arrow @click="go('more:my-posts')">내가 등록한 글</UiListRow>
            </UiCard>
        </UiSection>

        <!-- 운영 — 관리자 전용 -->
        <UiSection v-if="auth.isAdmin" title="운영">
            <UiCard :padded="false" class="more-list">
                <UiListRow tag="button" icon="grid" arrow @click="go('admin')">운영 관리</UiListRow>
            </UiCard>
        </UiSection>

        <!-- 설정 — 상세 설정은 별도 페이지(/settings)에서 관리 -->
        <UiSection title="설정">
            <UiCard :padded="false" class="more-list">
                <UiListRow tag="button" icon="settings" arrow @click="go('settings')">설정 관리</UiListRow>
            </UiCard>
        </UiSection>

        <!-- 고객지원 — 공지·FAQ·1:1 문의 -->
        <UiSection title="고객지원">
            <UiCard :padded="false" class="more-list">
                <UiListRow tag="button" icon="help" arrow @click="go('support')">공지·FAQ·1:1 문의</UiListRow>
            </UiCard>
        </UiSection>

        <!-- 계정 — 로그인/탈퇴 관련 -->
        <UiSection title="계정">
            <UiCard :padded="false" class="more-list">
                <UiListRow tag="button" icon="logout" danger @click="logout">로그아웃</UiListRow>
            </UiCard>
        </UiSection>
    </div>
</template>

<style scoped>
.more-page {
    /* 상단 시작은 공용 .page-shell 기준으로 통일 — 하단 여백만 페이지가 관리한다 */
    padding-bottom: 24px;
}

/* ── 프로필 히어로 ── */
.more-hero {
    padding: 18px 16px 16px;
    border-radius: 18px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 22%, var(--surface)), color-mix(in srgb, var(--brand) 6%, var(--surface)));
    border: 1px solid color-mix(in srgb, var(--brand) 26%, transparent);
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.more-hero__top {
    display: flex;
    align-items: center;
    gap: 12px;
}

.more-hero__avatar {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--brand), color-mix(in srgb, var(--brand) 60%, #fff));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 900;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--brand) 30%, transparent);
}

.more-hero__who {
    flex: 1;
    min-width: 0;
}

.more-hero__name-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    min-width: 0;
}

.more-hero__name-row strong {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.more-hero__vip {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    background: linear-gradient(135deg, #f7b731, #f2994a);
    color: #fff;
    font-size: 10px;
    font-weight: 400;
}

.more-hero__role {
    margin-top: 3px;
    font-size: 11px;
    color: var(--text-muted);
}

.more-hero__edit {
    flex-shrink: 0;
    border: 1px solid color-mix(in srgb, var(--brand) 45%, transparent);
    border-radius: 999px;
    padding: 7px 13px;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s ease;
}

.more-hero__edit:hover {
    background: color-mix(in srgb, var(--brand) 20%, transparent);
}

/* XP */
.more-hero__xp {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.more-hero__xp-head {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
}

.more-hero__bar {
    height: 7px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 16%, transparent);
    overflow: hidden;
}

.more-hero__bar-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--brand), color-mix(in srgb, var(--brand) 55%, #fff));
    transition: width 0.3s ease;
}

/* ── 오늘 통계 ── */
.more-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 12px;
}

.more-stats__card {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 13px 12px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
}

.more-stats__label {
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 600;
}

.more-stats__value {
    font-size: 15px;
    font-weight: 900;
    color: var(--text);
    line-height: 1.2;
}

.more-stats__unit {
    font-size: 11px;
    color: var(--text-muted);
}

.more-list {
    overflow: hidden;
}
</style>
