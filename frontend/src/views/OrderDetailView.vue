<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiClaimOrder, apiDetachOrder, apiDuplicateOrder, apiOrder, apiReviewOrder, apiTransitionOrder } from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import { useAuthStore } from '../stores/auth';
import { useChatsStore } from '../stores/chats';
import { useMessage as useNaiveMessage } from 'naive-ui';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const naiveMessage = useNaiveMessage();

const order = ref(null);
const group = ref(null);
const statusOptions = ref({});
const nextTransitions = ref([]);
const loading = ref(true);
const error = ref('');
const acting = ref(false);
const message = ref('');
const messageType = ref('success');

const CLAIMABLE_STATUSES = ['published', 'trading', 'acceptance_pending'];

const SERVICE_LABELS = { pickup: '픽업', sending: '공항샌딩', landing: '공항랜딩' };

// 상태 진행 순서 (취소는 흐름 밖)
const STATUS_FLOW = ['draft', 'published', 'trading', 'accepted', 'driving', 'completed', 'settled'];

const currentStep = computed(() => {
    const index = STATUS_FLOW.indexOf(order.value?.status ?? '');

    return index === -1 ? 0 : index;
});

const isCancelled = computed(() => order.value?.status === 'cancelled');

// 서비스 일시 표시: "YYYY-MM-DD (요일) HH:MM" 형태
const serviceDatetimeLabel = computed(() => {
    const orderData = order.value;
    const date = orderData?.service_date ?? '';
    const time = orderData?.service_time ?? '';

    if (orderData?.service_datetime) {
        return orderData.service_datetime;
    }
    if (!date) {
        return '-';
    }

    const weekday = /^\d{4}-\d{2}-\d{2}$/.test(date)
        ? ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][new Date(`${date}T00:00:00`).getDay()]
        : '';

    return `${date}${weekday ? ` (${weekday})` : ''}${time ? ` ${time}` : ''}`;
});

const isClaimable = computed(() => Boolean(order.value && CLAIMABLE_STATUSES.includes(order.value.status)));

// 리뷰 — 운행 완료/정산 후 작성 가능
const canReview = computed(() => Boolean(order.value && ['completed', 'settled'].includes(order.value.status)));
const reviewOpen = ref(false);
const reviewRating = ref(5);
const reviewContent = ref('');
const reviewSubmitting = ref(false);

const openReview = () => {
    reviewRating.value = 5;
    reviewContent.value = '';
    reviewOpen.value = true;
};

const submitReview = async () => {
    if (!reviewContent.value.trim()) {
        naiveMessage.warning('리뷰 내용을 입력해주세요.');

        return;
    }

    reviewSubmitting.value = true;

    try {
        await apiReviewOrder(order.value.id, {
            rating: reviewRating.value,
            content: reviewContent.value.trim(),
        });
        naiveMessage.success('리뷰를 남겼습니다.');
        reviewOpen.value = false;
    } catch (e) {
        naiveMessage.error(getApiErrorMessage(e, '리뷰 작성에 실패했습니다.'));
    } finally {
        reviewSubmitting.value = false;
    }
};

// 채팅: 등록자(타인)와 대화 가능할 때만
const canChat = computed(() => Boolean(order.value?.user_id && order.value.user_id !== auth.user?.id));

// 수정: 초안/공개 상태에서만
const canEdit = computed(() => Boolean(order.value && ['draft', 'published'].includes(order.value.status)));

const goEdit = () => router.push({ name: 'order-edit', params: { id: order.value.id } });

// 오더 복제 — 동일 내용을 초안으로 새로 만들어 수정 화면으로 이동
const duplicate = async () => {
    acting.value = true;
    message.value = '';

    try {
        const { data } = await apiDuplicateOrder(order.value.id);
        message.value = '오더가 복제되었습니다. 내용을 확인하고 수정하세요.';
        messageType.value = 'success';
        router.push({ name: 'order-edit', params: { id: data.data.id } });
    } catch (e) {
        message.value = getApiErrorMessage(e, '오더 복제에 실패했습니다.');
        messageType.value = 'error';
    } finally {
        acting.value = false;
    }
};

const openChat = async () => {
    try {
        await useChatsStore().openWith(order.value.user_id, order.value.id);
        router.push({ name: 'chat' });
    } catch (e) {
        message.value = getApiErrorMessage(e, '채팅을 시작하지 못했습니다.');
        messageType.value = 'error';
    }
};

// 그룹 일정 행으로 변환 (셋트 오더일 때 그룹 내 모든 오더)
const groupOrderRows = computed(() =>
    (group.value?.orders ?? [])
        .map((sibling) => {
            const date = sibling.service_date ?? '-';
            const weekday = /^\d{4}-\d{2}-\d{2}$/.test(date)
                ? ['일요일', '월요일', '화요일', '수요일', '목요일', '금요일', '토요일'][new Date(`${date}T00:00:00`).getDay()]
                : '';

            return {
                ...sibling,
                displayDate: `${date} ${weekday}`,
                isCurrent: sibling.id === order.value?.id,
                displayAmount: sibling.expected_revenue ?? sibling.amount_value ?? null,
            };
        })
        .sort((a, b) => (a.service_date + a.service_time).localeCompare(b.service_date + b.service_time)),
);

// 셋트 그룹 합계 (금액이 있는 일정만 합산)
const groupTotalAmount = computed(() =>
    groupOrderRows.value.reduce((sum, row) => sum + (Number(row.displayAmount) || 0), 0),
);

// 상태별 색상 (오더 카드와 동일 팔레트)
const STATUS_COLORS = {
    draft: '#909399',
    published: '#36adff',
    trading: '#ffa940',
    accepted: '#2f54eb',
    driving: '#13c2c2',
    completed: '#18a058',
    settled: '#722ed1',
};

// 진행 단계별 스타일: 지난 단계=해당 색, 현재=채움, 진행 전=회색
const stepStyle = (status, index) => {
    const color = STATUS_COLORS[status] ?? '#909399';

    if (index === currentStep.value) {
        return { background: color, borderColor: color, color: '#ffffff' };
    }
    if (index < currentStep.value) {
        return { borderColor: color, color };
    }

    return {};
};

const lineItems = computed(() =>
    (order.value?.line_items ?? []).map((item) => ({
        scheduled_time: item.scheduled_time || '-',
        service_type: SERVICE_LABELS[item.service_type] ?? (item.service_type || '-'),
        pickup_location: item.pickup_location || '-',
        dropoff_location: item.dropoff_location || '-',
        flight_number: item.flight_number || '-',
        service_date: item.service_date || '',
        service_weekday: item.service_weekday || '',
    })),
);

const statusTagType = computed(() => {
    if (isClaimable.value) {
        return 'warning';
    }

    return order.value?.status === 'cancelled' ? 'default' : 'success';
});

const load = async () => {
    const { data } = await apiOrder(route.params.id);

    order.value = data.data.order;
        group.value = data.data.group;
        statusOptions.value = data.data.statusOptions;
    nextTransitions.value = data.data.nextTransitions;
};

const refresh = async () => {
    loading.value = true;
    error.value = '';

    try {
        await load();
    } catch (e) {
        error.value = getApiErrorMessage(e, '오더를 불러오지 못했습니다.');
    } finally {
        loading.value = false;
    }
};

const run = async (action, successText) => {
    acting.value = true;
    message.value = '';
    messageType.value = 'success';

    try {
        await action();
        await load();
        message.value = successText;
    } catch (e) {
        messageType.value = 'error';
        message.value = getApiErrorMessage(e, '요청에 실패했습니다.');
    } finally {
        acting.value = false;
    }
};

const claim = () => run(() => apiClaimOrder(order.value.id), '오더를 내 오더로 가져왔습니다.');
const transition = (status) =>
    run(() => apiTransitionOrder(order.value.id, status), `상태가 "${statusOptions.value[status] ?? status}"로 변경되었습니다.`);

// ── 취소 사유 입력 ──
const cancelOpen = ref(false);
const cancelReason = ref('');

const requestTransition = (status) => {
    if (status === 'cancelled') {
        cancelReason.value = '';
        cancelOpen.value = true;
    } else {
        transition(status);
    }
};

const confirmCancel = async () => {
    cancelOpen.value = false;
    await run(
        () => apiTransitionOrder(order.value.id, 'cancelled', cancelReason.value),
        '오더가 취소되었습니다.',
    );
};

// 셋트 그룹에서 개별 오더 분리
const detach = (siblingId) =>
    run(() => apiDetachOrder(siblingId), '셋트 그룹에서 분리되었습니다.');

onMounted(refresh);
</script>

<template>
    <div>
        <div class="page-head">
            <div>
                <p class="page-head__eyebrow">{{ order?.order_number || 'Order Detail' }}</p>
                <h1 class="page-head__title">{{ order?.pickup_location || '-' }} → {{ order?.dropoff_location || '-' }}</h1>
                <p class="page-head__desc">{{ serviceDatetimeLabel }}</p>
            </div>
            <n-tag size="large" round :type="statusTagType">
                {{ statusOptions[order?.status] ?? order?.status ?? '-' }}
            </n-tag>
        </div>

        <n-alert v-if="error" type="error" :show-icon="true" class="detail-block">
            {{ error }}
        </n-alert>

        <n-spin :show="loading" class="detail-body">
            <template v-if="order">
                <n-alert v-if="isCancelled" type="error" :show-icon="true" class="detail-block">
                    취소된 오더입니다.
                </n-alert>

                <n-card v-else :bordered="true" class="detail-block">
                    <template #header>
                        <n-space align="center" :size="10">
                            <span>진행상태</span>
                            <n-tag size="small" round :type="statusTagType">
                                {{ statusOptions[order?.status] ?? order?.status }}
                            </n-tag>
                        </n-space>
                    </template>
                    <div class="status-flow">
                        <span
                            v-for="(status, index) in STATUS_FLOW"
                            :key="status"
                            class="status-flow__step"
                            :class="{ 'status-flow__step--active': index === currentStep }"
                            :style="stepStyle(status, index)"
                        >
                            <template v-if="index < currentStep">✓ </template>
                            {{ statusOptions[status] ?? status }}
                        </span>
                    </div>
                </n-card>

                <n-card :bordered="true" class="detail-block">
                    <template #header>오더 정보</template>
                    <div class="detail-rows">
                        <div class="detail-row">
                            <span>오더번호</span>
                            <strong>{{ order.order_number }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>노선</span>
                            <strong>{{ order.pickup_location || '-' }} → {{ order.dropoff_location || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>서비스 일시</span>
                            <strong>{{ serviceDatetimeLabel }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>차량</span>
                            <strong>{{ order.vehicle_type || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>항공편</span>
                            <strong>{{ order.flight_number || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>인원 · 짐</span>
                            <strong>{{ order.passenger_count || 0 }}명 · {{ order.luggage_count || 0 }}개</strong>
                        </div>
                        <div class="detail-row">
                            <span>금액</span>
                            <strong>{{ (order.expected_revenue ?? order.amount_value)?.toLocaleString() ?? '-' }}원</strong>
                        </div>
                        <div class="detail-row">
                            <span>예약처</span>
                            <strong>{{ order.reservation_company || '-' }} · {{ order.reservation_channel || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>등록자</span>
                            <strong>{{ order.user?.name || '-' }}</strong>
                        </div>
                    </div>
                </n-card>

                <n-card
                    v-if="group"
                    :bordered="true"
                    class="detail-block"
                >
                    <template #header>
                        <n-space align="center" :size="10">
                            <span>셋트 그룹</span>
                            <n-tag size="small" round>{{ group.name }}</n-tag>
                            <n-tag v-if="groupTotalAmount > 0" size="small" round type="warning">
                                총 {{ Number(groupTotalAmount).toLocaleString() }}원
                            </n-tag>
                        </n-space>
                    </template>
                    <div class="group-schedule-list">
                        <div
                            v-for="sibling in groupOrderRows"
                            :key="sibling.id"
                            class="group-schedule-item"
                            :class="{ 'group-schedule-item--current': sibling.isCurrent }"
                        >
                            <div class="group-schedule-item__head">
                                <strong>{{ sibling.scheduled_time || sibling.service_time || '-' }}</strong>
                                <n-tag size="small" round>
                                    {{ SERVICE_LABELS[sibling.service_type] ?? (sibling.service_type || '-') }}
                                </n-tag>
                                <span v-if="sibling.displayAmount" class="group-schedule-item__amount">
                                    {{ Number(sibling.displayAmount).toLocaleString() }}원
                                </span>
                                <n-button
                                    v-if="!sibling.isCurrent"
                                    size="tiny"
                                    quaternary
                                    type="warning"
                                    :loading="acting"
                                    class="group-schedule-item__detach"
                                    @click.stop="detach(sibling.id)"
                                >
                                    분리
                                </n-button>
                            </div>
                            <div class="group-schedule-item__route">
                                {{ sibling.pickup_location || '-' }} → {{ sibling.dropoff_location || '-' }}
                            </div>
                            <div class="group-schedule-item__meta">
                                {{ sibling.displayDate }}
                                <template v-if="sibling.flight_number">
                                     · 항공편: {{ sibling.flight_number }}
                                </template>
                                <template v-if="sibling.passenger_count">
                                     · {{ sibling.passenger_count }}명
                                </template>
                            </div>
                        </div>
                    </div>
                </n-card>

                <n-card v-if="lineItems.length" :bordered="true" class="detail-block">
                    <template #header>일정</template>
                    <div class="schedule-list">
                        <n-card
                            v-for="(item, index) in lineItems"
                            :key="index"
                            size="small"
                            class="schedule-card"
                        >
                            <template #header>
                                <div class="schedule-card__head">
                                    <n-space align="center" :size="8">
                                        <strong class="schedule-time">{{ item.scheduled_time }}</strong>
                                        <n-tag size="small" round>{{ item.service_type }}</n-tag>
                                    </n-space>
                                    <span class="schedule-date">{{ item.service_date }} {{ item.service_weekday }}</span>
                                </div>
                            </template>
                            <div class="schedule-card__route">
                                {{ item.pickup_location }}
                                <span class="schedule-card__arrow">→</span>
                                {{ item.dropoff_location }}
                            </div>
                            <div
                                v-if="item.flight_number && item.flight_number !== '-'"
                                class="schedule-card__meta"
                            >
                                항공편: {{ item.flight_number }}
                            </div>
                        </n-card>
                    </div>
                </n-card>

                <n-card :bordered="true" class="detail-block">
                    <template #header>상태 관리</template>

                    <n-space wrap class="detail-message">
                        <n-button v-if="canChat" size="large" secondary @click="openChat">
                            채팅하기
                        </n-button>
                        <n-button v-if="canEdit" size="large" secondary @click="goEdit">
                            오더 수정
                        </n-button>
                        <n-button size="large" secondary :loading="acting" @click="duplicate">
                            오더 복사
                        </n-button>
                    </n-space>

                    <n-alert
                        v-if="message"
                        :type="messageType"
                        :show-icon="true"
                        class="detail-message"
                    >
                        {{ message }}
                    </n-alert>

                    <n-space v-if="isClaimable">
                        <n-button type="primary" size="large" :loading="acting" @click="claim">
                            내 오더로 가져오기
                        </n-button>
                    </n-space>

                    <n-space v-else-if="nextTransitions.length" wrap>
                        <n-button
                            v-for="next in nextTransitions"
                            :key="next"
                            size="large"
                            :loading="acting"
                            @click="requestTransition(next)"
                        >
                            → {{ statusOptions[next] ?? next }}
                        </n-button>
                    </n-space>

                    <n-alert
                        v-if="order?.status === 'cancelled' && order.cancel_reason"
                        type="warning"
                        :show-icon="true"
                        class="detail-message"
                    >
                        취소 사유: {{ order.cancel_reason }}
                    </n-alert>

                    <n-empty
                        v-else
                        description="진행할 수 있는 상태 전이가 없습니다."
                        :show-description="true"
                    />

                    <n-button
                        v-if="canReview"
                        type="warning"
                        size="large"
                        class="detail-review-btn"
                        @click="openReview"
                    >
                        ⭐ 리뷰 남기기
                    </n-button>
                </n-card>

                <!-- 리뷰 작성 모달 -->
                <n-modal
                    v-model:show="reviewOpen"
                    preset="card"
                    title="리뷰 남기기"
                    :style="{ maxWidth: '400px' }"
                >
                    <div class="review-modal">
                        <n-rate v-model:value="reviewRating" size="large" color="#ffa940" />
                        <n-input
                            v-model:value="reviewContent"
                            type="textarea"
                            placeholder="운행 서비스는 어땠나요? (500자 이내)"
                            :maxlength="500"
                            :rows="4"
                        />
                    </div>
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="reviewOpen = false">취소</n-button>
                            <n-button type="primary" :loading="reviewSubmitting" @click="submitReview">
                                등록
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 취소 사유 모달 -->
                <n-modal
                    v-model:show="cancelOpen"
                    preset="card"
                    title="오더 취소"
                    :style="{ maxWidth: '400px' }"
                >
                    <p class="cancel-modal__desc">오더를 취소합니다. 취소 사유를 입력해 주세요. (선택)</p>
                    <n-input
                        v-model:value="cancelReason"
                        type="textarea"
                        placeholder="예) 차량 수리로 운행 불가"
                        :maxlength="500"
                        :rows="3"
                    />
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="cancelOpen = false">닫기</n-button>
                            <n-button type="error" :loading="acting" @click="confirmCancel">
                                오더 취소
                            </n-button>
                        </div>
                    </template>
                </n-modal>
            </template>
        </n-spin>
    </div>
</template>

<style scoped>
.cancel-modal__desc {
    margin: 0 0 12px;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.6;
}
.detail-body {
    display: block;
    min-height: 200px;
}

.detail-block {
    margin-bottom: 16px;
    border-radius: 16px;
}

.detail-message {
    margin-bottom: 14px;
}

/* 리뷰 작성 */
.detail-review-btn {
    margin-top: 16px;
    width: 100%;
}

.review-modal {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.filter-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

/* 진행상태 — 작은 알약들로 줄바꿈하며 표시 (화면 밖으로 안 나감) */
.status-flow {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.status-flow__step {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted); /* 진행 전 단계는 회색 */
    font-size: 12px;
    white-space: nowrap;
}

.status-flow__step--active {
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

/* 오더 정보 — 라벨/값 행 (심플 카드) */
.detail-rows {
    display: flex;
    flex-direction: column;
}

.detail-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row span {
    flex-shrink: 0;
    color: var(--text-muted);
    font-size: 13px;
}

.detail-row strong {
    text-align: right;
    font-size: 14px;
}

/* 일정 카드 */
.schedule-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.schedule-card {
    border-radius: 10px;
}

.schedule-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
}

.schedule-time {
    font-size: 15px;
}

.schedule-date {
    color: var(--text-muted);
    font-size: 12px;
}

.schedule-card__route {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
}

.schedule-card__arrow {
    color: var(--accent);
    font-size: 16px;
    font-weight: 700;
}

.schedule-card__meta {
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 12px;
}

/* 셋트 그룹 일정 리스트 */
.group-schedule-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.group-schedule-item {
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
}

.group-schedule-item--current {
    border-color: rgba(54, 173, 255, 0.5);
    background: rgba(54, 173, 255, 0.05);
}

/* 셋트 일정 금액 */
.group-schedule-item__amount {
    margin-left: auto;
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
}

.group-schedule-item__head {
    display: flex;
    align-items: center;
    gap: 8px;
}

.group-schedule-item__head strong {
    font-size: 15px;
}

.group-schedule-item__detach {
    margin-left: auto;
}

.group-schedule-item__route {
    margin-top: 6px;
    font-size: 14px;
    font-weight: 600;
}

.group-schedule-item__meta {
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 12px;
}
</style>
