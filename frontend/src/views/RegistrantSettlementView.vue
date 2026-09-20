<script setup>
import { computed, onMounted, ref } from 'vue';
import { useMessage } from 'naive-ui';
import { apiMyPayables } from '../api/settlement';
import { getApiErrorMessage } from '../api/client';
import BaseIcon from '../components/common/BaseIcon.vue';
import EmptyState from '../components/common/EmptyState.vue';
import UiSection from '../components/ui/UiSection.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'RegistrantSettlementView' });

const message = useMessage();

const payables = ref(null);
const loading = ref(true);

const formatWon = (v) => `${Number(v ?? 0).toLocaleString('ko-KR')}원`;

const load = async () => {
    loading.value = true;

    try {
        const { data } = await apiMyPayables();
        payables.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '입금 내역을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const account = computed(() => payables.value?.platform_account ?? {});

// 수금 상태 라벨 — 자기 수행(not_required)은 수금 자체가 없으므로 입금 대기로 보이면 안 된다
const COLLECTION_LABELS = { paid: '입금 확인', pending: '입금 대기', not_required: '자기 수행' };
const collectionLabel = (status) => COLLECTION_LABELS[status] ?? '입금 대기';
const collectionModifier = (status) => (status === 'pending' ? 'pending' : status === 'paid' ? 'paid' : 'self');

onMounted(load);
</script>

<template>
    <div class="reg-settle-page page-shell">
        <div class="settle-head">
            <div>
                <h1 class="settle-head__title">정산·입금</h1>
                <p class="settle-head__desc">운행 대금을 입금하고, 입금 확인 상태를 확인합니다.</p>
            </div>
            <button type="button" class="settle-head__refresh" aria-label="새로고침" title="새로고침" @click="load">
                <BaseIcon name="refresh" :size="16" />
            </button>
        </div>

        <div v-if="loading" class="settle-list">
            <div v-for="n in 3" :key="n" class="settle-card settle-card--skeleton">
                <div class="sk-line" style="width: 50%;" />
                <div class="sk-line" style="width: 70%;" />
            </div>
        </div>

        <template v-else-if="payables">
            <section class="settle-hero">
                <div class="settle-hero__cell">
                    <span class="settle-hero__label">입금 대기</span>
                    <strong class="settle-hero__amount">{{ formatWon(payables.unpaid_total) }}</strong>
                    <span class="settle-hero__sub">정산 {{ payables.unpaid_count }}건</span>
                </div>
            </section>

            <UiSection title="입금 계좌">
                <div v-if="account.bank_name && account.account_number" class="reg-account">
                    <dl class="reg-account__rows">
                        <div><dt>은행</dt><dd>{{ account.bank_name }}</dd></div>
                        <div><dt>계좌번호</dt><dd>{{ account.account_number }}</dd></div>
                        <div v-if="account.account_holder"><dt>예금주</dt><dd>{{ account.account_holder }}</dd></div>
                    </dl>
                    <p class="reg-account__hint">운행 대금을 위 계좌로 입금해 주세요. 입금 확인 후 기사에게 지급됩니다.</p>
                </div>
                <p v-else class="reg-account__empty">입금 계좌는 공지에서 확인해 주세요.</p>
            </UiSection>

            <UiSection title="정산 내역">
                <div v-if="payables.recent.length" class="settle-recents">
                    <div v-for="item in payables.recent" :key="item.id" class="settle-recent">
                        <div class="settle-recent__head">
                            <strong class="settle-recent__route">{{ item.route || '운행' }}</strong>
                            <span
                                class="settle-recent__status"
                                :class="`settle-recent__status--${collectionModifier(item.collection_status)}`"
                            >
                                {{ collectionLabel(item.collection_status) }}
                            </span>
                        </div>
                        <p class="settle-recent__meta">
                            {{ item.service_date }} {{ item.service_time || '' }}
                            · 정산 {{ new Date(item.created_at_iso).toLocaleDateString('ko-KR') }}
                        </p>
                        <div class="settle-recent__amounts">
                            <span>운행 대금 {{ formatWon(item.gross_amount) }}</span>
                            <span>수수료 {{ formatWon(item.fee_amount) }}</span>
                        </div>
                    </div>
                </div>
                <EmptyState v-else icon="inbox" title="정산 내역이 없습니다" hint="정산된 운행이 있으면 여기에 표시됩니다" />
            </UiSection>
        </template>
    </div>
</template>

<style scoped>
.reg-settle-page {
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
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: var(--text);
    white-space: nowrap;
}
.settle-hero__sub {
    font-size: 10px;
    color: var(--text-muted);
}

.reg-account {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.reg-account__rows {
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.reg-account__rows > div {
    display: flex;
    gap: 10px;
}
.reg-account__rows dt {
    flex-shrink: 0;
    width: 56px;
    font-size: 11px;
    color: var(--text-muted);
}
.reg-account__rows dd {
    margin: 0;
    font-size: 11px;
    font-weight: 600;
}
.reg-account__hint {
    margin: 0;
    font-size: 10px;
    color: var(--text-muted);
}
.reg-account__empty {
    margin: 0;
    font-size: 11px;
    color: var(--text-muted);
}

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
/* 자기 수행 — 수금할 것이 없는 상태라 중립(그레이)으로 둔다 */
.settle-recent__status--self {
    background: color-mix(in srgb, var(--text-muted) 14%, transparent);
    color: var(--text-muted);
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
</style>
