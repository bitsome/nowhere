<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../stores/auth';
import { useDriverStore } from '../stores/driver';
import { useProfileSettings } from '../composables/useProfileSettings';
import { useDriverWorkspace } from '../composables/useDriverWorkspace';
import LevelBadge from '../components/common/LevelBadge.vue';
import BaseIcon from '../components/common/BaseIcon.vue';
import { formatTime } from '../utils/formatTime';

defineOptions({ name: 'ProfileView' });

const auth = useAuthStore();
const router = useRouter();
const message = useMessage();

// ── 모듈: 프로필 설정(회원정보·알림·인증·실적) / 기사 운영(상태·통계·차량) ──
const settings = useProfileSettings({ auth, router, message });
const driver = useDriverStore();
const workspace = useDriverWorkspace({ message, driver });

const isDriver = computed(() => auth.user?.role === 'Driver');

// 프로필 탭 — 첫 화면은 '요약'(실적·바로가기)부터 시작
const profileTab = ref('summary');

const {
    error, success, myStats, loadMyStats, form,
    roleLabel, initials, level, formatWon, isCustomer,
} = settings;

const {
    todayStats, toggleDriverStatus, loadTodayStats, formatDuration,
} = workspace;

onMounted(() => {
    form.name = auth.user?.name ?? '';
    form.phone = auth.user?.phone ?? '';

    // 기사 운영 — 상태/오늘 통계 (차량 설정은 설정 페이지에서 관리)
    if (auth.user?.role === 'Driver') {
        driver.load().then(() => {
            loadTodayStats();
        });
    }
});

// fetchMe 완료 전에 마운트되면 auth.user가 늦게 세팅된다 — 실적 로드를 보장한다
watch(
    () => auth.user?.id,
    (id) => {
        if (id) {
            loadMyStats();
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="profile-page page-shell">
        <n-alert v-if="error" type="error" :show-icon="true" class="profile-block">
            {{ error }}
        </n-alert>
        <n-alert v-if="success" type="success" :show-icon="true" class="profile-block">
            {{ success }}
        </n-alert>

        <!-- 프로필 히어로 + XP -->
        <n-card :bordered="true" class="profile-block">
            <div class="profile-hero">
                <div class="profile-hero__top">
                    <span class="profile-hero__avatar">{{ initials }}</span>
                    <div class="profile-hero__info">
                        <div class="profile-hero__name-row">
                            <strong class="profile-hero__name">{{ auth.user?.name }}</strong>
                            <LevelBadge v-if="level" :level="level.level" size="sm" />
                            <n-tag v-if="auth.user?.is_vip" size="small" round type="warning">VIP</n-tag>
                        </div>
                        <div class="profile-hero__meta">
                            <span class="profile-hero__meta-line">
                                <BaseIcon name="mail" :size="13" />
                                {{ auth.user?.email }}
                            </span>
                            <span v-if="auth.user?.phone" class="profile-hero__meta-line">
                                <BaseIcon name="call" :size="13" />
                                {{ auth.user?.phone }}
                            </span>
                        </div>
                        <div class="profile-hero__badges">
                            <span v-if="auth.user?.is_vehicle_verified" class="verify-badge" title="차량 인증 완료">차량 인증</span>
                            <span v-if="auth.user?.is_license_verified" class="verify-badge" title="면허 인증 완료">면허 인증</span>
                            <n-tag size="small" round>{{ roleLabel }}</n-tag>
                        </div>
                    </div>
                </div>

                <!-- XP 진행바 -->
                <div v-if="level" class="profile-hero__xp">
                    <div class="profile-hero__xp-head">
                        <span>{{ level.title }}</span>
                        <span>누적 XP {{ formatWon(auth.user?.xp ?? 0) }}점</span>
                    </div>
                    <div class="profile-hero__bar">
                        <div class="profile-hero__bar-fill" :style="{ width: `${level.progress}%` }" />
                    </div>
                </div>
            </div>
        </n-card>

        <n-tabs
            v-model:value="profileTab"
            type="segment"
            justify-content="center"
            size="medium"
            class="profile-tabs"
        >
            <!-- 요약 — 내 실적 + 자주 쓰는 메뉴 -->
            <n-tab-pane name="summary" tab="요약">
                <div v-if="myStats" class="profile-stats">
                    <div class="profile-stats__card">
                        <span class="profile-stats__icon profile-stats__icon--green">
                            <BaseIcon name="car" :size="18" />
                        </span>
                        <div class="profile-stats__text">
                            <span class="profile-stats__label">완료 운행</span>
                            <strong class="profile-stats__value">{{ myStats.stats.completed_orders }}<small>건</small></strong>
                        </div>
                    </div>
                    <div class="profile-stats__card">
                        <span class="profile-stats__icon profile-stats__icon--brand">
                            <BaseIcon name="cash" :size="18" />
                        </span>
                        <div class="profile-stats__text">
                            <span class="profile-stats__label">누적 매출</span>
                            <strong class="profile-stats__value">{{ formatWon(myStats.stats.total_revenue) }}<small>원</small></strong>
                        </div>
                    </div>
                    <div class="profile-stats__card">
                        <span class="profile-stats__icon profile-stats__icon--amber">
                            <BaseIcon name="star" :size="18" />
                        </span>
                        <div class="profile-stats__text">
                            <span class="profile-stats__label">받은 평점</span>
                            <strong class="profile-stats__value" :class="{ 'profile-stats__value--muted': myStats.reviewSummary.count === 0 }">
                                {{ myStats.reviewSummary.count > 0 ? myStats.reviewSummary.avg : '-' }}<small>{{ myStats.reviewSummary.count > 0 ? `점 / ${myStats.reviewSummary.count}개` : '' }}</small>
                            </strong>
                        </div>
                    </div>
                </div>

                <!-- 내 차량·인증 — 기사 전용 상태 요약 (관리는 설정에서) -->
                <n-card v-if="isDriver" :bordered="true" class="profile-block">
                    <div class="verify-head">
                        <strong>차량·인증</strong>
                        <span class="verify-hint">인증 신청·차량 관리는 설정에서</span>
                    </div>
                    <div class="verify-row">
                        <span class="verify-row__label">
                            차량 인증
                            <span v-if="auth.user?.is_vehicle_verified" class="verify-row__done">완료</span>
                            <span v-else class="verify-row__pending">미인증</span>
                        </span>
                        <button type="button" class="verify-row__link" @click="router.push({ name: 'settings-verification' })">관리 <BaseIcon name="arrow-forward" :size="11" /></button>
                    </div>
                    <div class="verify-row">
                        <span class="verify-row__label">
                            면허 인증
                            <span v-if="auth.user?.is_license_verified" class="verify-row__done">완료</span>
                            <span v-else class="verify-row__pending">미인증</span>
                        </span>
                        <button type="button" class="verify-row__link" @click="router.push({ name: 'settings-verification' })">관리 <BaseIcon name="arrow-forward" :size="11" /></button>
                    </div>
                    <div v-if="auth.user?.vehicle_info" class="verify-row">
                        <span class="verify-row__label">등록 차량</span>
                        <span class="verify-row__value">{{ auth.user?.vehicle_info }}</span>
                    </div>
                </n-card>

                <!-- 업체 인증 — 등록자 전용 상태 요약 (Q-4, 관리는 설정에서) -->
                <n-card v-if="isCustomer" :bordered="true" class="profile-block">
                    <div class="verify-head">
                        <strong>업체 인증</strong>
                        <span class="verify-hint">인증 신청·관리는 설정에서</span>
                    </div>
                    <div class="verify-row">
                        <span class="verify-row__label">
                            사업자 인증
                            <span v-if="auth.user?.is_business_verified" class="verify-row__done">완료</span>
                            <span v-else class="verify-row__pending">미인증</span>
                        </span>
                        <button type="button" class="verify-row__link" @click="router.push({ name: 'settings-verification' })">관리 <BaseIcon name="arrow-forward" :size="11" /></button>
                    </div>
                    <div class="verify-row">
                        <span class="verify-row__label">
                            대표 계좌 인증
                            <span v-if="auth.user?.is_account_verified" class="verify-row__done">완료</span>
                            <span v-else class="verify-row__pending">미인증</span>
                        </span>
                        <button type="button" class="verify-row__link" @click="router.push({ name: 'settings-verification' })">관리 <BaseIcon name="arrow-forward" :size="11" /></button>
                    </div>
                </n-card>

                <n-card :bordered="true" class="profile-block">
                    <div class="verify-head">
                        <strong>바로가기</strong>
                        <span class="verify-hint">자주 쓰는 메뉴를 모아뒀어요</span>
                    </div>
                    <button type="button" class="community-entry" @click="router.push({ name: 'my-market' })">
                        <span class="community-entry__icon">
                            <BaseIcon name="cart" :size="22" />
                        </span>
                        <span class="community-entry__text">
                            <strong>내 마켓</strong>
                            <small>내가 등록한 운행 관리 · 가져오기 요청 확인</small>
                        </span>
                        <span class="community-entry__arrow"><BaseIcon name="arrow-forward" :size="16" /></span>
                    </button>
                    <button type="button" class="community-entry" @click="router.push({ name: 'dashboard' })">
                        <span class="community-entry__icon">
                            <BaseIcon name="grid" :size="22" />
                        </span>
                        <span class="community-entry__text">
                            <strong>대시보드</strong>
                            <small>매출·건수 통계와 오늘/내일 운행 일정</small>
                        </span>
                        <span class="community-entry__arrow"><BaseIcon name="arrow-forward" :size="16" /></span>
                    </button>
                    <button type="button" class="community-entry" @click="router.push({ name: 'community' })">
                        <span class="community-entry__icon">
                            <BaseIcon name="community" :size="22" />
                        </span>
                        <span class="community-entry__text">
                            <strong>사용자 커뮤니티</strong>
                            <small>기사·운영진과 일상을 공유하는 피드</small>
                        </span>
                        <span class="community-entry__arrow"><BaseIcon name="arrow-forward" :size="16" /></span>
                    </button>
                    <button
                        type="button"
                        class="community-entry"
                        @click="router.push({ name: 'user-page', params: { id: auth.user?.id } })"
                    >
                        <span class="community-entry__icon">
                            <BaseIcon name="my-posts" :size="22" />
                        </span>
                        <span class="community-entry__text">
                            <strong>내가 올린 글</strong>
                            <small>내 프로필·글·운행을 다른 사용자에게 보여주는 공개 페이지</small>
                        </span>
                        <span class="community-entry__arrow"><BaseIcon name="arrow-forward" :size="16" /></span>
                    </button>
                    <button type="button" class="community-entry" @click="router.push({ name: 'settings' })">
                        <span class="community-entry__icon">
                            <BaseIcon name="settings" :size="22" />
                        </span>
                        <span class="community-entry__text">
                            <strong>설정</strong>
                            <small>차량·알림·프로필 등 계정 설정 관리</small>
                        </span>
                        <span class="community-entry__arrow"><BaseIcon name="arrow-forward" :size="16" /></span>
                    </button>
                </n-card>
            </n-tab-pane>

            <!-- 근무 — 기사 전용 (차량 설정은 '설정' 탭에) -->
            <n-tab-pane v-if="isDriver" name="driver" tab="근무">
                <n-card :bordered="true" class="profile-block">
                    <div class="verify-head">
                        <strong>근무 상태</strong>
                        <span class="verify-hint">온라인이면 마켓에서 새 운행을 빠르게 확인할 수 있습니다</span>
                    </div>

                    <div class="driver-status">
                        <div class="driver-status__info">
                            <span class="driver-status__label" :class="`driver-status__label--${driver.status}`">
                                <span class="driver-status__dot" />
                                {{ driver.statusLabel }}
                            </span>
                            <span class="driver-status__meta">
                                오늘 {{ todayStats ? formatDuration(todayStats.online_seconds) : '-' }} 온라인
                                · {{ todayStats?.today_completed ?? '-' }}건 완료
                                · {{ todayStats ? formatWon(todayStats.today_income) : '-' }}원
                            </span>
                        </div>
                        <n-switch
                            :value="driver.isOnline"
                            :disabled="driver.status === 'on_trip'"
                            :loading="!driver.loaded"
                            @update:value="toggleDriverStatus"
                        />
                    </div>
                </n-card>
            </n-tab-pane>

            <!-- 활동 — 리뷰 / 레벨 -->
            <n-tab-pane name="activity" tab="활동">
                <n-card v-if="myStats" :bordered="true" class="profile-block">
                    <div class="verify-head">
                        <strong>받은 리뷰</strong>
                        <span class="verify-hint">완료된 운행 후 상대방이 남긴 리뷰입니다</span>
                    </div>
                    <n-empty
                        v-if="!myStats.reviews.length"
                        description="아직 받은 리뷰가 없습니다."
                        :image-size="60"
                        class="profile-reviews-empty"
                    />
                    <div v-else class="profile-review-list">
                        <article v-for="review in myStats.reviews" :key="review.id" class="profile-review">
                            <div class="profile-review__head">
                                <span class="profile-review__author">{{ review.reviewer?.name }}</span>
                                <n-rate :value="review.rating" readonly size="small" color="#ffa940" />
                            </div>
                            <p class="profile-review__content" v-text="review.content" />
                            <span class="profile-review__time">{{ formatTime(review.created_at) }}</span>
                        </article>
                    </div>
                </n-card>

                <n-card v-if="level" :bordered="true" class="profile-block">
                    <div class="level-head">
                        <LevelBadge :level="level.level" size="lg" />
                        <div class="level-head__text">
                            <strong>{{ level.title }}</strong>
                            <span>누적 XP {{ formatWon(auth.user?.xp ?? 0) }}점</span>
                        </div>
                    </div>

                    <div class="level-bar">
                        <div class="level-bar__fill" :style="{ width: `${level.progress}%` }" />
                    </div>

                    <p class="level-hint">
                        <template v-if="level.next_xp !== null">
                            다음 레벨까지 <strong>{{ formatWon(level.next_xp - (auth.user?.xp ?? 0)) }}점</strong> 남았습니다
                            ({{ level.min_xp }} → {{ level.next_xp }}점).
                        </template>
                        <template v-else>
                            최고 레벨에 도달했습니다!
                        </template>
                    </p>

                    <!-- 레벨 혜택 -->
                    <div v-if="level.benefits?.length" class="level-benefits">
                        <span
                            v-for="benefit in level.benefits"
                            :key="benefit"
                            class="level-benefit"
                        >
                            {{ benefit }}
                        </span>
                    </div>

                    <div v-if="auth.user?.recent_xp_events?.length" class="xp-events">
                        <div
                            v-for="event in auth.user.recent_xp_events"
                            :key="event.created_at"
                            class="xp-event"
                        >
                            <span>{{ event.label }}</span>
                            <strong>+{{ event.xp }}</strong>
                        </div>
                    </div>
                </n-card>
            </n-tab-pane>

            <!-- 설정 — 별도 페이지(/settings)로 이동 -->
        </n-tabs>
    </div>
</template>

<style scoped>
.profile-page {
    /* 상단 시작은 공용 .page-shell 기준으로 통일 — 하단 여백만 페이지가 관리한다 */
    padding-bottom: 24px;
}

.profile-block {
    margin-bottom: 14px;
    border-radius: var(--card-radius);
}

/* ── 프로필 히어로 ── */
.profile-hero {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.profile-hero__top {
    display: flex;
    align-items: center;
    gap: 16px;
}

.profile-hero__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--brand-gradient);
    color: #ffffff;
    font-size: 20px;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--brand) 30%, transparent);
}

.profile-hero__name-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.profile-hero__name {
    font-size: 14px;
    font-weight: 700;
}

.profile-hero__info {
    min-width: 0;
    flex: 1;
}

.profile-hero__meta {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-top: 6px;
}

.profile-hero__meta-line {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--text-muted);
    font-size: 11px;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-hero__meta-line svg {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
    opacity: 0.75;
}

.profile-hero__badges {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    flex-wrap: wrap;
}

/* XP 바 */
.profile-hero__xp {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

.profile-hero__xp-head {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
}

.profile-hero__bar {
    height: 8px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--brand) 14%, transparent);
    overflow: hidden;
}

.profile-hero__bar-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--brand), color-mix(in srgb, var(--brand) 55%, #fff));
    transition: width 0.3s ease;
}

.profile-tabs {
    margin-bottom: 16px;
}

.profile-tabs :deep(.n-tabs-nav) {
    margin-bottom: 14px;
}

/* 세그먼트 탭 — 활성 탭 색상 명시 (다크모드에서도 동일하게 보이도록) */
/* brand는 라이트(#36adff)·다크(#63e2b7) 모두 밝아 흰 글자 대비가 약함 — 앱 표준 #07120e를 두 모드 공통 사용 */
.profile-tabs :deep(.n-tabs-nav--segment-type .n-tabs-tab.n-tabs-tab--active) {
    background: var(--brand);
    color: #07120e;
    box-shadow: none;
    font-weight: 700;
}

.verify-hint {
    color: var(--text-muted);
    font-size: 11px;
}

/* 내 실적 카드 */
.profile-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.profile-stats__card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.profile-stats__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    flex-shrink: 0;
}

.profile-stats__icon svg {
    width: 18px;
    height: 18px;
}

.profile-stats__icon--green {
    background: rgba(46, 160, 67, 0.12);
    color: #2ea043;
}

.profile-stats__icon--brand {
    background: var(--brand-soft);
    color: var(--brand);
}

.profile-stats__icon--amber {
    background: rgba(255, 169, 64, 0.15);
    color: #ffa940;
}

.profile-stats__text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.profile-stats__label {
    color: var(--text-muted);
    font-size: 11.5px;
    white-space: nowrap;
}

.profile-stats__value {
    font-size: 13px;
    font-weight: 800;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.profile-stats__value small {
    font-size: 10.5px;
    font-weight: 600;
    color: var(--text-muted);
    margin-left: 2px;
}

.profile-stats__value--muted {
    color: var(--text-muted);
}

/* ── 레벨 ── */
.level-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.level-head__text {
    display: flex;
    flex-direction: column;
}

.level-head__text strong {
    font-size: 12px;
}

.level-head__text span {
    color: var(--text-muted);
    font-size: 11px;
    margin-top: 2px;
}

.level-bar {
    height: 10px;
    border-radius: 5px;
    background: rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

html.dark .level-bar {
    background: rgba(255, 255, 255, 0.1);
}

.level-bar__fill {
    display: block;
    height: 100%;
    border-radius: 5px;
    background: var(--brand-gradient);
    transition: width 0.4s ease;
}

.level-hint {
    margin: 10px 0 0;
    color: var(--text-muted);
    font-size: 11px;
}

.level-hint strong {
    color: var(--text);
}

/* 레벨 혜택 — 칩 행 */
.level-benefits {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}

.level-benefit {
    padding: 3px 10px;
    border-radius: 999px;
    background: var(--brand-soft);
    color: var(--brand);
    font-size: 11px;
    font-weight: 600;
}

.xp-events {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 16px;
    padding: 14px;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.02);
}

html.dark .xp-events {
    background: rgba(255, 255, 255, 0.03);
}

.xp-event {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11px;
}

.xp-event span {
    color: var(--text);
}

.xp-event strong {
    color: var(--status-accepted);
    font-size: 11px;
}

/* ── 바로가기 ── */
.community-entry {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 8px 4px;
    border: 0;
    background: none;
    color: var(--text);
    cursor: pointer;
    text-align: left;
    border-radius: 12px;
    transition: background 0.12s ease;
}

.community-entry:hover {
    background: color-mix(in srgb, var(--brand) 5%, transparent);
}

.community-entry + .community-entry {
    border-top: 1px solid var(--border);
    border-radius: 0;
    margin-top: 4px;
    padding-top: 12px;
}

.community-entry__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--brand-gradient);
    color: #ffffff;
    flex-shrink: 0;
}

.community-entry__icon svg {
    width: 22px;
    height: 22px;
}

.community-entry__text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.community-entry__text strong {
    font-size: 11px;
}

.community-entry__text small {
    color: var(--text-muted);
    font-size: 11px;
    margin-top: 2px;
}

.community-entry__arrow {
    color: var(--text-muted);
    font-size: 20px;
    font-weight: 300;
}

/* ── 기사 운영 — 근무 상태 ── */
.driver-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 4px 0;
}

.driver-status__info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.driver-status__label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    font-weight: 700;
}

.driver-status__dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--text-muted);
}

.driver-status__label--online .driver-status__dot,
.driver-status__label--on_trip .driver-status__dot {
    background: var(--status-completed);
}

.driver-status__label--online,
.driver-status__label--on_trip {
    color: var(--status-completed);
}

.driver-status__label--rest .driver-status__dot {
    background: var(--warn);
}

.driver-status__label--rest {
    color: var(--warn);
}

.driver-status__meta {
    color: var(--text-muted);
    font-size: 11px;
}
</style>
