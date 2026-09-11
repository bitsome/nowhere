<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { apiMyPayouts, apiRequestPayout, apiSaveBankAccount, apiSettlementSummary } from '../api/settlement';
import { getApiErrorMessage } from '../api/client';
import BaseIcon from '../components/common/BaseIcon.vue';
import EmptyState from '../components/common/EmptyState.vue';
import UiSection from '../components/ui/UiSection.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SettlementView' });

const message = useMessage();

const summary = ref(null);
const payouts = ref([]);
const loading = ref(true);
const submitting = ref(false);
const accountBusy = ref(false);
const accountOpen = ref(false);

const form = reactive({ bank_name: '', account_number: '', account_holder: '' });

const formatWon = (v) => `${Number(v ?? 0).toLocaleString('ko-KR')}원`;

// 수수료 요율 라벨 — 5%, 7.5%처럼 정수면 소수점을 붙이지 않는다
const rateLabel = (rate) => {
    const pct = Number(rate ?? 0) * 100;

    return `${Number.isInteger(pct) ? pct : pct.toFixed(1)}%`;
};

// 현재 적용 중인 플랫폼 수수료율 (운영 중 조정 가능)
const feeRateLabel = computed(() => rateLabel(summary.value?.fee_rate ?? 0));

const load = async () => {
    loading.value = true;

    try {
        const [summaryRes, payoutRes] = await Promise.all([apiSettlementSummary(), apiMyPayouts()]);
        summary.value = summaryRes.data.data;
        payouts.value = payoutRes.data.data ?? [];

        // 계좌 정보를 폼에 미리 채운다 (수정 대비)
        const account = summary.value.account;

        if (account) {
            form.bank_name = account.bank_name ?? '';
            form.account_number = account.account_number ?? '';
            form.account_holder = account.account_holder ?? '';
        }
    } catch (e) {
        message.error(getApiErrorMessage(e, '정산 정보를 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

// 출금 계좌 등록/갱신
const saveAccount = async () => {
    if (!form.bank_name.trim() || !form.account_number.trim() || !form.account_holder.trim()) {
        message.warning('은행명·계좌번호·예금주를 모두 입력해 주세요.');
        return;
    }

    accountBusy.value = true;

    try {
        await apiSaveBankAccount({
            bank_name: form.bank_name.trim(),
            account_number: form.account_number.trim(),
            account_holder: form.account_holder.trim(),
        });
        message.success('출금 계좌가 등록되었습니다.');
        await load();
    } catch (e) {
        message.error(getApiErrorMessage(e, '계좌 등록에 실패했습니다.'));
    } finally {
        accountBusy.value = false;
    }
};

// 처리 대기 중인 출금 신청 여부 — 있으면 중복 신청을 막는다
const hasPendingPayout = computed(() => payouts.value.some((p) => p.status === 'pending'));

// 출금 신청 — 출금 가능 금액 전체
const requestPayout = async () => {
    if (!summary.value?.account) {
        message.warning('출금 계좌를 먼저 등록해 주세요.');
        return;
    }
    if (hasPendingPayout.value) {
        message.warning('이미 처리 대기 중인 출금 신청이 있습니다.');
        return;
    }
    if (!summary.value.pending_total) {
        message.info('출금할 정산 금액이 없습니다.');
        return;
    }

    submitting.value = true;

    try {
        await apiRequestPayout();
        message.success(`출금 신청 완료 — ${formatWon(summary.value.pending_total)} 지급 대기`);
        await load();
    } catch (e) {
        message.error(getApiErrorMessage(e, '출금 신청에 실패했습니다.'));
    } finally {
        submitting.value = false;
    }
};

const PAYOUT_STATUS = {
    pending: { label: '처리 대기', className: 'pending' },
    paid: { label: '지급 완료', className: 'paid' },
    rejected: { label: '거절됨', className: 'rejected' },
};

onMounted(load);
</script>

<template>
    <div class="settle-page page-shell">
        <div class="settle-head">
            <div>
                <h1 class="settle-head__title">정산</h1>
                <p class="settle-head__desc">정산된 운행의 금액과 출금을 관리합니다.</p>
            </div>
            <button type="button" class="settle-head__refresh" aria-label="새로고침" title="새로고침" @click="load">
                <BaseIcon name="refresh" :size="16" />
            </button>
        </div>

        <!-- 로딩 스켈레톤 -->
        <div v-if="loading" class="settle-list">
            <div v-for="n in 3" :key="n" class="settle-card settle-card--skeleton">
                <div class="sk-line" style="width: 50%;" />
                <div class="sk-line" style="width: 70%;" />
            </div>
        </div>

        <template v-else-if="summary">
            <!-- 출금 가능 — 이번 달 정산 요약 -->
            <section class="settle-hero">
                <div class="settle-hero__grid">
                    <div class="settle-hero__cell">
                        <span class="settle-hero__label">출금 가능</span>
                        <strong class="settle-hero__amount">{{ formatWon(summary.pending_total) }}</strong>
                        <span class="settle-hero__sub">정산 {{ summary.pending_count }}건 대기</span>
                    </div>
                    <div class="settle-hero__cell">
                        <span class="settle-hero__label">이번 달 정산</span>
                        <strong class="settle-hero__amount">{{ formatWon(summary.this_month.net) }}</strong>
                        <span class="settle-hero__sub">
                            {{ summary.this_month.count }}건 · 운행 {{ formatWon(summary.this_month.gross) }} · 수수료 {{ formatWon(summary.this_month.fee) }}
                        </span>
                    </div>
                </div>
                <p class="settle-hero__policy">
                    플랫폼 수수료 {{ feeRateLabel }} · 운행금액에서 차감한 금액이 지급됩니다.
                </p>
                <button
                    type="button"
                    class="settle-hero__cta"
                    :disabled="submitting || !summary.account || !summary.pending_total || hasPendingPayout"
                    @click="requestPayout"
                >
                    <BaseIcon name="coin" :size="15" />
                    {{ hasPendingPayout ? '출금 신청 처리 대기 중' : '출금하기' }}
                </button>
                <p v-if="!summary.account" class="settle-hero__hint">출금하려면 아래에서 출금 계좌를 먼저 등록해 주세요.</p>
            </section>

            <!-- 출금 계좌 -->
            <UiSection title="출금 계좌">
                <div v-if="summary.account" class="settle-account">
                    <dl class="settle-account__rows">
                        <div><dt>은행</dt><dd>{{ summary.account.bank_name }}</dd></div>
                        <div><dt>계좌번호</dt><dd>{{ summary.account.account_number }}</dd></div>
                        <div><dt>예금주</dt><dd>{{ summary.account.account_holder }}</dd></div>
                    </dl>
                    <button type="button" class="settle-account__edit" @click="accountOpen = !accountOpen">
                        {{ accountOpen ? '닫기' : '계좌 변경' }}
                    </button>
                    <div v-if="accountOpen" class="settle-account__form">
                        <input v-model="form.bank_name" class="settle-input" placeholder="은행명 (예: 국민은행)" />
                        <input v-model="form.account_number" class="settle-input" inputmode="numeric" placeholder="계좌번호" />
                        <input v-model="form.account_holder" class="settle-input" placeholder="예금주" />
                        <button type="button" class="settle-btn" :disabled="accountBusy" @click="saveAccount">
                            {{ accountBusy ? '저장 중...' : '계좌 저장' }}
                        </button>
                    </div>
                </div>
                <div v-else class="settle-account">
                    <p class="settle-account__empty">출금을 받을 계좌를 등록해 주세요.</p>
                    <div class="settle-account__form">
                        <input v-model="form.bank_name" class="settle-input" placeholder="은행명 (예: 국민은행)" />
                        <input v-model="form.account_number" class="settle-input" inputmode="numeric" placeholder="계좌번호" />
                        <input v-model="form.account_holder" class="settle-input" placeholder="예금주" />
                        <button type="button" class="settle-btn" :disabled="accountBusy" @click="saveAccount">
                            {{ accountBusy ? '저장 중...' : '계좌 등록' }}
                        </button>
                    </div>
                </div>
            </UiSection>

            <!-- 출금 내역 -->
            <UiSection title="출금 내역">
                <div v-if="payouts.length" class="settle-payouts">
                    <div v-for="payout in payouts" :key="payout.id" class="settle-row">
                        <span class="settle-row__dot" :class="`settle-row__dot--${PAYOUT_STATUS[payout.status]?.className ?? 'pending'}`" />
                        <div class="settle-row__body">
                            <p class="settle-row__title">
                                {{ formatWon(payout.amount) }}
                                <em v-if="payout.note" class="settle-row__note">{{ payout.note }}</em>
                            </p>
                            <p class="settle-row__meta">
                                {{ PAYOUT_STATUS[payout.status]?.label ?? payout.status }}
                                · {{ payout.bank_name }} {{ payout.account_number }}
                                · {{ new Date(payout.created_at_iso).toLocaleDateString('ko-KR') }}
                            </p>
                        </div>
                    </div>
                </div>
                <EmptyState v-else icon="coin" title="출금 내역이 없습니다" hint="출금 신청을 하면 여기에 기록됩니다" />
            </UiSection>

            <!-- 최근 정산 내역 -->
            <UiSection title="정산 내역">
                <div v-if="summary.recent.length" class="settle-recents">
                    <div v-for="item in summary.recent" :key="item.id" class="settle-recent">
                        <div class="settle-recent__head">
                            <strong class="settle-recent__route">{{ item.route || '운행' }}</strong>
                            <span
                                class="settle-recent__status"
                                :class="item.status === 'paid' ? 'settle-recent__status--paid' : 'settle-recent__status--pending'"
                            >
                                {{ item.status === 'paid' ? '지급 완료' : '출금 대기' }}
                            </span>
                        </div>
                        <p class="settle-recent__meta">
                            {{ item.service_date }} {{ item.service_time || '' }}
                            · 정산 {{ new Date(item.created_at_iso).toLocaleDateString('ko-KR') }}
                        </p>
                        <div class="settle-recent__amounts">
                            <span>운행 {{ formatWon(item.gross_amount) }}</span>
                            <span>수수료 {{ formatWon(item.fee_amount) }} ({{ rateLabel(item.fee_rate) }})</span>
                            <strong>{{ formatWon(item.net_amount) }}</strong>
                        </div>
                    </div>
                </div>
                <EmptyState v-else icon="inbox" title="정산 내역이 없습니다" hint="정산된 운행이 있으면 여기에 표시됩니다" />
            </UiSection>
        </template>
    </div>
</template>

<style scoped>
.settle-page {
    padding-bottom: 24px;
}

.settle-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-top: 6px;
}
.settle-head__title {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -0.5px;
}
.settle-head__desc {
    margin: 3px 0 0;
    font-size: 11px;
    color: var(--text-muted);
}
.settle-head__refresh {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
}

.settle-list {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
    margin-top: 16px;
}
.settle-card--skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-height: 80px;
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}

/* 출금 가능 히어로 */
.settle-hero {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 16px;
    padding: 18px var(--card-pad) 16px;
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}
.settle-hero__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.settle-hero__cell {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}
.settle-hero__label {
    font-size: 10px;
    font-weight: 700;
    color: var(--text-muted);
}
.settle-hero__amount {
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: var(--text);
    white-space: nowrap;
}
.settle-hero__sub {
    font-size: 10px;
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.settle-hero__policy {
    margin: 0;
    font-size: 10px;
    color: var(--text-muted);
}
.settle-hero__cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 44px;
    border: 0;
    border-radius: 12px;
    background: var(--brand);
    color: #07120e;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
.settle-hero__cta:disabled {
    opacity: 0.55;
    cursor: default;
}
.settle-hero__hint {
    margin: -6px 0 0;
    font-size: 10px;
    color: var(--text-muted);
    text-align: center;
}

/* 계좌 */
.settle-account {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.settle-account__rows {
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.settle-account__rows > div {
    display: flex;
    gap: 10px;
}
.settle-account__rows dt {
    flex-shrink: 0;
    width: 56px;
    font-size: 11px;
    color: var(--text-muted);
}
.settle-account__rows dd {
    margin: 0;
    font-size: 11px;
    font-weight: 600;
}
.settle-account__edit {
    align-self: flex-start;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--brand);
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}
.settle-account__empty {
    margin: 0;
    font-size: 11px;
    color: var(--text-muted);
}
.settle-account__form {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.settle-input {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 11px 14px;
    font-size: 11px;
    background: var(--bg);
    color: var(--text);
    outline: none;
    font-family: inherit;
}
.settle-input:focus {
    border-color: var(--brand);
}
.settle-btn {
    padding: 11px 0;
    border: 0;
    border-radius: 12px;
    background: var(--text);
    color: var(--bg);
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}
html.dark .settle-btn {
    background: var(--brand);
    color: #07120e;
}
.settle-btn:disabled {
    opacity: 0.55;
    cursor: default;
}

/* 출금 내역 행 */
.settle-payouts {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.settle-row {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 10px 2px;
}
.settle-row + .settle-row {
    border-top: 1px solid var(--border);
}
.settle-row__dot {
    width: 8px;
    height: 8px;
    margin-top: 5px;
    border-radius: 50%;
    flex-shrink: 0;
}
.settle-row__dot--pending {
    background: color-mix(in srgb, var(--brand) 45%, transparent);
}
.settle-row__dot--paid {
    background: var(--status-settled);
}
.settle-row__dot--rejected {
    background: var(--danger);
}
.settle-row__body {
    flex: 1;
    min-width: 0;
}
.settle-row__title {
    margin: 0;
    font-size: 11px;
    font-weight: 700;
}
.settle-row__note {
    font-style: normal;
    font-weight: 400;
    color: var(--text-muted);
}
.settle-row__meta {
    margin: 3px 0 0;
    font-size: 10px;
    color: var(--text-muted);
}

/* 최근 정산 */
.settle-recents {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
}
.settle-recent {
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.settle-recent__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.settle-recent__route {
    font-size: 11px;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.settle-recent__status {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
}
.settle-recent__status--pending {
    background: color-mix(in srgb, var(--brand) 14%, transparent);
    color: var(--brand);
}
.settle-recent__status--paid {
    background: color-mix(in srgb, var(--status-settled) 14%, transparent);
    color: var(--status-settled);
}
.settle-recent__meta {
    margin: 0;
    font-size: 10px;
    color: var(--text-muted);
}
.settle-recent__amounts {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.settle-recent__amounts span {
    font-size: 10px;
    color: var(--text-muted);
}
.settle-recent__amounts strong {
    margin-left: auto;
    font-size: 12px;
    font-weight: 800;
}
</style>
