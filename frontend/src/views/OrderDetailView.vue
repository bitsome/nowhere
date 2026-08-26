<script setup>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useChatsStore } from '../stores/chats';
import { useMessage as useNaiveMessage } from 'naive-ui';
import { useOrderDetail } from '../composables/useOrderDetail';
import { useOrderMap } from '../composables/useOrderMap';
import BaseIcon from '../components/common/BaseIcon.vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const chats = useChatsStore();
const naiveMessage = useNaiveMessage();

// ── 모듈: 운행 상세(로드·파생·상태변경·리뷰·취소·분리) / 경로 지도 ──
const detail = useOrderDetail({ route, router, auth, chats, naiveMessage });
const map = useOrderMap({ order: detail.order });

const {
    order, group, statusOptions, nextTransitions, loading, error, acting, message, messageType,
    currentStep, isCancelled, isPriority, serviceTime, minutesToService, isUrgent, isToday, isTomorrow,
    serviceCountdownLabel, amountLabel, serviceDatetimeLabel, isClaimable, isMine, isRegistrant, isPerformer,
    statusLabel, canReview,
    reviewOpen, reviewRating, reviewContent, reviewSubmitting, openReview, submitReview,
    canChat, hasRegistrantChat, canEdit, goEdit, isClaimPending, isRegistrantPending, isClaimantPending,
    isWaitingClaims, confirmState, closeConfirm, doConfirm, approveClaim, rejectClaim, withdrawClaim, openChat, goUserPage,
    primaryAction, primaryActionStatus, statusButtonColor, groupOrderRows, groupTotalAmount, stepStyle,
    lineItems, statusTagType, refresh, claim, transition, cancelOpen, cancelReason, requestTransition,
    confirmCancel, completionOpen, completionRevenue, confirmComplete, detach,
    offers, offersLoading, canOffer, myPendingOffer,
    offerOpen, offerAmount, offerMessage, offerSubmitting, openOffer, submitOffer,
    acceptOffer, rejectOffer, withdrawOffer,
    SERVICE_LABELS, STATUS_FLOW,
} = detail;

const {
    mapTarget, mapOpen, mapQueryLabel, mapQuery, mapEmbedUrl, mapGoogleUrl, mapNaverUrl, mapKakaoUrl,
} = map;

onMounted(refresh);
</script>

<template>
    <div
        class="detail-page"
        :class="{ 'detail-page--bar': order && (canChat || canEdit || primaryAction || isRegistrantPending) }"
    >
        <div class="detail-hero">
            <div class="detail-hero__body">
                <p class="detail-hero__eyebrow">{{ order?.order_number || '운행 상세' }}</p>
                <div class="detail-hero__route">
                    <span class="detail-hero__loc">{{ order?.pickup_location || '-' }}</span>
                    <span class="detail-hero__arrow">→</span>
                    <span class="detail-hero__loc">{{ order?.dropoff_location || '-' }}</span>
                </div>
                <div class="detail-hero__badges">
                    <span v-if="isMine" class="hero-badge hero-badge--mine">내 운행</span>
                    <span v-if="isWaitingClaims" class="hero-badge hero-badge--waiting">요청 대기중</span>
                    <span v-if="isPriority" class="hero-badge hero-badge--priority">긴급</span>
                    <span v-if="isUrgent" class="hero-badge hero-badge--urgent">임박</span>
                    <span v-else-if="isToday" class="hero-badge hero-badge--today">오늘</span>
                    <span v-else-if="isTomorrow" class="hero-badge hero-badge--tomorrow">내일</span>
                </div>
                <p class="detail-hero__meta">{{ serviceDatetimeLabel }}</p>
            </div>
            <div class="detail-hero__side">
                <div class="detail-hero__amount">{{ amountLabel }}</div>
                <n-tag size="large" round :type="statusTagType">
                    {{ statusLabel }}
                </n-tag>
            </div>
        </div>

        <n-alert v-if="error" type="error" :show-icon="true" class="detail-block">
            {{ error }}
        </n-alert>

        <div v-if="loading" class="detail-skeleton">
            <div class="sk-card detail-skeleton__hero" />
            <div class="sk-card detail-skeleton__card">
                <div class="sk-line sk-line--md" style="width: 30%" />
                <div class="sk-line" style="margin-top: 12px" />
                <div class="sk-line" style="margin-top: 8px; width: 75%" />
                <div class="sk-line" style="margin-top: 8px; width: 55%" />
            </div>
            <div class="sk-card detail-skeleton__card">
                <div class="sk-line sk-line--md" style="width: 25%" />
                <div class="sk-line" style="margin-top: 12px" />
                <div class="sk-line" style="margin-top: 8px; width: 65%" />
            </div>
        </div>

        <template v-else>
            <div v-if="order" class="detail-body">
                <n-alert v-if="isCancelled" type="error" :show-icon="true" class="detail-block">
                    취소된 운행입니다.
                </n-alert>

                <n-alert v-if="isClaimantPending" type="warning" :show-icon="true" class="detail-block">
                    가져오기 요청이 등록자에게 전달되었습니다. 등록자가 승인하면 운행을 진행할 수 있습니다.
                </n-alert>

                <n-alert v-if="isRegistrantPending" type="info" :show-icon="true" class="detail-block">
                    드라이버가 이 운행을 가져오기 요청했습니다. 승인하면 운행이 넘어가고, 거절하면 마켓에 그대로 남습니다.
                </n-alert>

                <n-card v-else-if="!isCancelled" :bordered="true" class="detail-block">
                    <template #header>
                        <n-space align="center" :size="10">
                            <span>진행상태</span>
                            <n-tag size="small" round :type="statusTagType">
                                {{ statusLabel }}
                            </n-tag>
                        </n-space>
                    </template>
                    <div class="status-flow">
                        <div
                            v-for="(status, index) in STATUS_FLOW"
                            :key="status"
                            class="status-flow__item"
                            :class="{
                                'status-flow__item--done': index < currentStep,
                                'status-flow__item--active': index === currentStep,
                            }"
                        >
                            <div class="status-flow__rail">
                                <span class="status-flow__dot" :style="stepStyle(status, index)">
                                    <span v-if="index < currentStep" class="status-flow__dot-icon"><BaseIcon name="check" :size="11" /></span>
                                    <span v-else-if="index === currentStep" class="status-flow__dot-icon"><BaseIcon name="ellipse" :size="10" /></span>
                                </span>
                                <span
                                    v-if="index < STATUS_FLOW.length - 1"
                                    class="status-flow__line"
                                    :class="{ 'status-flow__line--done': index < currentStep }"
                                />
                            </div>
                            <div class="status-flow__label">
                                <strong>{{ statusOptions[status] ?? status }}</strong>
                                <small v-if="index === currentStep">현재 진행 중</small>
                            </div>
                        </div>
                    </div>
                    <p class="status-flow__progress">진행 {{ currentStep + 1 }} / {{ STATUS_FLOW.length }} 단계</p>
                </n-card>

                <n-card :bordered="true" class="detail-block">
                    <template #header>운행 정보</template>

                    <p class="detail-group-title">서비스</p>
                    <div class="detail-rows">
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
                        <div
                            v-if="['completed', 'settled'].includes(order.status) && order.actual_revenue != null"
                            class="detail-row"
                        >
                            <span>실제 수익</span>
                            <strong>{{ Number(order.actual_revenue).toLocaleString() }}원</strong>
                        </div>
                        <div class="detail-row">
                            <span>서비스까지</span>
                            <strong :class="{ 'detail-text--urgent': isUrgent }">{{ serviceCountdownLabel }}</strong>
                        </div>
                    </div>

                    <p class="detail-group-title">예약</p>
                    <div class="detail-rows">
                        <div class="detail-row">
                            <span>운행번호</span>
                            <strong>{{ order.order_number }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>고객명</span>
                            <strong>{{ order.customer_name || '-' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>예약처</span>
                            <strong>{{ order.reservation_company || '-' }} · {{ order.reservation_channel || '-' }}</strong>
                        </div>
                    </div>

                    <p class="detail-group-title">기타</p>
                    <div class="detail-rows">
                        <div class="detail-row">
                            <span>긴급</span>
                            <strong>{{ isPriority ? '긴급 운행' : '일반' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>등록자</span>
                            <strong
                                v-if="order.user?.id"
                                class="detail-link"
                                @click="goUserPage(order.user.id)"
                            >
                                {{ order.user?.name || '-' }}
                            </strong>
                            <strong v-else>{{ order.user?.name || '-' }}</strong>
                        </div>
                    </div>
                </n-card>

                <n-card v-if="order.pickup_location || order.dropoff_location" :bordered="true" class="detail-block">
                    <template #header>
                        <n-space align="center" :size="10">
                            <span>경로 지도</span>
                            <n-radio-group v-model:value="mapTarget" size="small">
                                <n-radio-button value="pickup">출발지</n-radio-button>
                                <n-radio-button value="dropoff">도착지</n-radio-button>
                            </n-radio-group>
                        </n-space>
                    </template>
                    <div class="detail-map">
                        <iframe
                            v-if="mapOpen && mapEmbedUrl"
                            :src="mapEmbedUrl"
                            class="detail-map__frame"
                            title="지도"
                            loading="lazy"
                        />
                        <div v-else class="detail-map__placeholder">
                            <span class="detail-map__label">{{ mapQueryLabel }} · {{ mapQuery || '-' }}</span>
                            <n-button size="small" secondary @click="mapOpen = true">
                                지도 보기
                            </n-button>
                        </div>
                    </div>
                    <div class="detail-map__links">
                        <span class="detail-map__hint">앱으로 열기</span>
                        <a :href="mapGoogleUrl" target="_blank" rel="noopener">Google</a>
                        <a :href="mapNaverUrl" target="_blank" rel="noopener">네이버 지도</a>
                        <a :href="mapKakaoUrl" target="_blank" rel="noopener">카카오 지도</a>
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

                    <n-alert
                        v-if="message"
                        :type="messageType"
                        :show-icon="true"
                        class="detail-message"
                    >
                        {{ message }}
                    </n-alert>

                    <!-- 주 동작(가져오기/첫 전이)은 하단 바에서, 여기선 나머지 전이/사유/리뷰 -->
                    <!-- 승인 대기 상태의 요청자는 여기서 전이를 직접 하지 않고 하단 바에서 '철회'만 한다 -->
                    <p
                        v-if="nextTransitions.length && !(order?.status === 'completed' && !isRegistrant) && !(order?.status === 'acceptance_pending' && !isRegistrant)"
                        class="detail-next-hint"
                    >
                        다음 단계:
                        <strong>{{ statusOptions[nextTransitions[0]] ?? nextTransitions[0] }}</strong>
                        <template v-if="nextTransitions.length > 1">
                            외 {{ nextTransitions.length - 1 }}건
                        </template>
                    </p>
                    <n-space
                        v-if="nextTransitions.slice(1).length && !(order?.status === 'completed' && !isRegistrant) && !(order?.status === 'acceptance_pending' && !isRegistrant)"
                        wrap
                    >
                        <n-button
                            v-for="next in nextTransitions.slice(1)"
                            :key="next"
                            size="large"
                            :color="statusButtonColor(next)"
                            :loading="acting"
                            @click="requestTransition(next)"
                        >
                            → {{ statusOptions[next] ?? next }}
                        </n-button>
                    </n-space>

                    <!-- 완료 후 정산 대기 — 진행자는 정산을 할 수 없고 등록자가 처리 -->
                    <n-alert
                        v-if="order?.status === 'completed' && !isRegistrant"
                        type="info"
                        :show-icon="true"
                        class="detail-message"
                    >
                        운행이 완료되었습니다. 등록자가 정산을 처리하면 '정산 완료'로 전환됩니다.
                    </n-alert>

                    <n-alert
                        v-if="order?.status === 'cancelled' && order.cancel_reason"
                        type="warning"
                        :show-icon="true"
                        class="detail-message"
                    >
                        취소 사유: {{ order.cancel_reason }}
                    </n-alert>

                    <n-empty
                        v-if="!nextTransitions.length && !isClaimable && order?.status !== 'cancelled'"
                        description="진행할 수 있는 상태 전이가 없습니다."
                        :show-description="true"
                    />

                    <n-button
                        v-if="canReview && !(primaryAction && primaryAction.label === '리뷰 남기기')"
                        type="warning"
                        size="large"
                        class="detail-review-btn"
                        @click="openReview"
                    >
                        리뷰 남기기
                    </n-button>

                    <div v-else-if="myReview" class="detail-review-done">
                        <n-tag size="large" round color="#ffa940">
                            <BaseIcon name="star" :size="14" />
                            {{ myReview.rating }} 리뷰 완료
                        </n-tag>
                    </div>
                </n-card>

                <!-- 요금 제안(오퍼) — 등록자는 전체 제안 관리, 기사는 제안/철회 -->
                <n-card
                    v-if="order && ['published', 'trading'].includes(order.status) && (canOffer || order.user_id === auth.user?.id)"
                    :bordered="true"
                    class="detail-block"
                >
                    <template #header>
                        <n-space align="center" :size="10">
                            <span>요금 제안</span>
                            <n-tag v-if="offers.length" size="small" round type="warning">
                                {{ offers.length }}
                            </n-tag>
                        </n-space>
                    </template>

                    <!-- 기사 — 아직 제안 전이면 제안하기 버튼 -->
                    <template v-if="canOffer && !myPendingOffer">
                        <p class="offer-hint">
                            이 운행의 운임을 직접 제안해 보세요. 등록자가 제안을 비교한 뒤 수락하면 운행이 넘어옵니다.
                        </p>
                        <n-button type="primary" size="large" class="offer-propose-btn" @click="openOffer">
                            요금 제안하기
                        </n-button>
                    </template>

                    <!-- 기사 — 보낸 제안이 수락 대기 중이면 철회 가능 -->
                    <div v-else-if="myPendingOffer" class="offer-item">
                        <div class="offer-item__info">
                            <strong>{{ Number(myPendingOffer.amount).toLocaleString() }}원</strong>
                            <n-tag size="small" round type="warning">수락 대기</n-tag>
                            <span v-if="myPendingOffer.message" class="offer-item__msg">{{ myPendingOffer.message }}</span>
                        </div>
                        <n-button size="small" secondary type="warning" :loading="acting" @click="withdrawOffer(myPendingOffer)">
                            철회
                        </n-button>
                    </div>

                    <!-- 등록자 — 모든 제안을 비교하고 수락/거절 -->
                    <template v-else-if="order.user_id === auth.user?.id">
                        <n-empty
                            v-if="!offers.length"
                            description="아직 요금 제안이 없습니다."
                            :show-description="true"
                        />
                        <div v-else class="offer-list">
                            <div
                                v-for="offer in offers"
                                :key="offer.id"
                                class="offer-item"
                                :class="`offer-item--${offer.status}`"
                            >
                                <div class="offer-item__info">
                                    <strong>{{ Number(offer.amount).toLocaleString() }}원</strong>
                                    <span class="offer-item__driver">{{ offer.driver?.name || '기사' }}</span>
                                    <span v-if="offer.driver?.rating" class="offer-item__rating">
                                        ★ {{ offer.driver.rating }} ({{ offer.driver.review_count }})
                                    </span>
                                    <n-tag size="small" round>{{ offer.status_label }}</n-tag>
                                    <span v-if="offer.message" class="offer-item__msg">{{ offer.message }}</span>
                                </div>
                                <div v-if="offer.status === 'pending'" class="offer-item__actions">
                                    <n-button size="small" secondary type="error" :loading="acting" @click="rejectOffer(offer)">
                                        거절
                                    </n-button>
                                    <n-button size="small" type="primary" :loading="acting" @click="acceptOffer(offer)">
                                        수락
                                    </n-button>
                                </div>
                            </div>
                        </div>
                    </template>
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
                    title="운행 취소"
                    :style="{ maxWidth: '400px' }"
                >
                    <p class="cancel-modal__desc">운행을 취소합니다. 취소 사유를 입력해 주세요. (선택)</p>
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
                                운행 취소
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 운행 완료 모달 — 실제 수익 입력 (입력 없으면 기대 금액 유지) -->
                <n-modal
                    v-model:show="completionOpen"
                    preset="card"
                    title="운행 완료"
                    :style="{ maxWidth: '400px' }"
                >
                    <p class="cancel-modal__desc">운행이 완료되었습니다. 실제 수익을 입력해 주세요. (입력하지 않으면 기대 금액으로 기록됩니다)</p>
                    <n-input-number
                        v-model:value="completionRevenue"
                        :min="0"
                        placeholder="실제 수익 (원)"
                        class="completion-revenue"
                    />
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="completionOpen = false">닫기</n-button>
                            <n-button type="primary" :loading="acting" @click="confirmComplete">
                                운행 완료
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 요금 제안 작성 모달 — 기사가 운임과 메모를 입력 -->
                <n-modal
                    v-model:show="offerOpen"
                    preset="card"
                    title="요금 제안"
                    :style="{ maxWidth: '400px' }"
                >
                    <div class="offer-modal">
                        <p class="cancel-modal__desc">
                            이 운행의 운임을 제안하세요. 등록자가 여러 제안을 비교한 뒤 수락하면 운행이 넘어옵니다. (수락 전까지 기사 연락처는 비공개)
                        </p>
                        <n-input-number
                            v-model:value="offerAmount"
                            :min="1000"
                            :step="1000"
                            placeholder="제안 금액 (원)"
                            class="offer-amount"
                        />
                        <n-input
                            v-model:value="offerMessage"
                            type="textarea"
                            placeholder="메모 (선택) — 예) 오전 출발 가능합니다"
                            :maxlength="500"
                            :rows="3"
                        />
                    </div>
                    <template #footer>
                        <div class="filter-footer">
                            <n-button @click="offerOpen = false">취소</n-button>
                            <n-button type="primary" :loading="offerSubmitting" @click="submitOffer">
                                제안 보내기
                            </n-button>
                        </div>
                    </template>
                </n-modal>

                <!-- 공용 확인 다이얼로그 — 모든 상태 변경 전 확인 -->
                <ConfirmDialog
                    v-model:open="confirmState.open"
                    :title="confirmState.title"
                    :message="confirmState.message"
                    :confirm-text="confirmState.confirmText"
                    :type="confirmState.type"
                    :loading="acting"
                    @confirm="doConfirm"
                    @cancel="closeConfirm"
                />
            </div>
        </template>

        <!-- 하단 액션 바 — 주 동작을 항상 손이 닿는 곳에 -->
        <div
            v-if="order && (canChat || canEdit || primaryAction || isRegistrantPending)"
            class="detail-actionbar"
        >
            <div v-if="canChat" class="detail-chat-wrap">
                <n-button size="large" secondary @click="openChat">
                    채팅
                </n-button>
                <span v-if="hasRegistrantChat" class="detail-chat-wrap__dot" />
            </div>
            <n-button v-if="canEdit" size="large" secondary @click="goEdit">
                수정
            </n-button>
            <n-button v-if="canOffer && !myPendingOffer" size="large" secondary @click="openOffer">
                요금 제안
            </n-button>
            <template v-if="isRegistrantPending">
                <n-button size="large" secondary type="error" :loading="acting" @click="rejectClaim">
                    거절
                </n-button>
                <n-button
                    type="primary"
                    size="large"
                    :loading="acting"
                    class="detail-actionbar__primary"
                    @click="approveClaim"
                >
                    승인
                </n-button>
            </template>
            <n-button
                v-else-if="primaryAction"
                type="primary"
                size="large"
                :loading="acting"
                :color="primaryActionStatus ? statusButtonColor(primaryActionStatus) : undefined"
                class="detail-actionbar__primary"
                @click="primaryAction.handler"
            >
                {{ primaryAction.label }}
            </n-button>
        </div>
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

/* 로딩 스켈레톤 — 히어로 + 정보 카드 골격 */
.detail-skeleton {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 16px;
}

.detail-skeleton__hero {
    height: 120px;
}

.detail-skeleton__card {
    display: flex;
    flex-direction: column;
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

.detail-review-done {
    margin-top: 16px;
    display: flex;
    justify-content: center;
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

.completion-revenue {
    width: 100%;
}

/* ── 요금 제안(오퍼) ── */
.offer-hint {
    margin: 0 0 12px;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.6;
}

.offer-propose-btn {
    width: 100%;
}

.offer-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.offer-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
}

.offer-item--accepted {
    border-color: var(--status-accepted);
    background: color-mix(in srgb, var(--status-accepted) 6%, transparent);
}

.offer-item--rejected,
.offer-item--cancelled {
    opacity: 0.55;
}

.offer-item__info {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    min-width: 0;
}

.offer-item__info strong {
    font-size: 15px;
}

.offer-item__driver {
    font-size: 13px;
    font-weight: 600;
}

.offer-item__rating {
    color: #ffa940;
    font-size: 12px;
    font-weight: 700;
}

.offer-item__msg {
    width: 100%;
    color: var(--text-muted);
    font-size: 12px;
}

.offer-item__actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.offer-modal {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.offer-amount {
    width: 100%;
}

/* 진행상태 — 세로 타임라인: 완료(✓)·현재(●)·대기(○)를 연결선으로 표현 */
.status-flow {
    display: flex;
    flex-direction: column;
}

.status-flow__item {
    display: flex;
    gap: 12px;
    min-height: 46px;
}

.status-flow__rail {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 24px;
    flex-shrink: 0;
}

.status-flow__dot {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 800;
    z-index: 1;
}

.status-flow__dot-icon {
    line-height: 1;
}

.status-flow__line {
    flex: 1;
    width: 2px;
    margin: 2px 0;
    border-radius: 2px;
    background: var(--border);
    transition: background 0.3s;
}

.status-flow__line--done {
    background: var(--brand);
}

.status-flow__label {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    padding: 3px 0 15px;
}

.status-flow__label strong {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
}

.status-flow__item--active .status-flow__label strong {
    color: var(--text);
    font-weight: 800;
}

.status-flow__label small {
    font-size: 12px;
    font-weight: 700;
    color: var(--brand);
}

.status-flow__progress {
    margin: 2px 0 0;
    padding-top: 12px;
    border-top: 1px dashed var(--border);
    color: var(--text-muted);
    font-size: 12px;
    text-align: right;
}

/* 상태 관리 — 다음 단계 힌트 */
.detail-next-hint {
    margin: 0 0 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--brand) 8%, transparent);
    border: 1px solid color-mix(in srgb, var(--brand) 25%, transparent);
    color: var(--text-muted);
    font-size: 13px;
}

.detail-next-hint strong {
    color: var(--brand);
    font-weight: 800;
}

/* 운행 정보 — 라벨/값 행 (심플 카드) */
/* 운행 정보 그룹 제목 — 서비스/예약/기타 구분 */
.detail-group-title {
    margin: 16px 0 4px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--text-muted);
}

.detail-group-title:first-child {
    margin-top: 0;
}

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
    border-color: color-mix(in srgb, var(--brand) 50%, transparent);
    background: color-mix(in srgb, var(--brand) 5%, transparent);
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

/* ── 히어로 헤더 ── */
.detail-page {
    position: relative;
}

.detail-page--bar {
    padding-bottom: 92px;
}

.detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 18px 18px 16px;
    margin-bottom: 16px;
    border-radius: 18px;
    background: linear-gradient(135deg, var(--brand), color-mix(in srgb, var(--brand) 35%, transparent) 60%, color-mix(in srgb, var(--status-settled) 18%, transparent));
    color: #ffffff;
}

.detail-hero__eyebrow {
    margin: 0 0 8px;
    font-size: 12px;
    letter-spacing: 0.3px;
    opacity: 0.85;
}

.detail-hero__route {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 19px;
    font-weight: 800;
    line-height: 1.3;
}

.detail-hero__loc {
    max-width: 42vw;
    word-break: keep-all;
}

.detail-hero__arrow {
    color: rgba(255, 255, 255, 0.75);
    font-size: 20px;
}

.detail-hero__badges {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.hero-badge {
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #ffffff;
}

.hero-badge--mine {
    background: rgba(0, 0, 0, 0.28);
    border: 1px solid rgba(255, 255, 255, 0.4);
}

.hero-badge--waiting {
    background: var(--brand);
    border: 1px solid rgba(255, 255, 255, 0.4);
}

.hero-badge--priority {
    background: #722ed1;
}

.hero-badge--urgent {
    background: #e5484d;
    animation: hero-pulse 1.6s ease-in-out infinite;
}

.hero-badge--today {
    background: rgba(255, 255, 255, 0.25);
}

.hero-badge--tomorrow {
    background: rgba(255, 255, 255, 0.18);
}

@keyframes hero-pulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(229, 72, 77, 0.55);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(229, 72, 77, 0);
    }
}

.detail-hero__meta {
    margin: 8px 0 0;
    font-size: 13px;
    opacity: 0.9;
}

.detail-hero__side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}

.detail-hero__amount {
    font-size: 22px;
    font-weight: 900;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
}

/* 운행 정보 — 등록자 링크 / 긴급 카운트다운 */
.detail-link {
    color: var(--accent);
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.detail-text--urgent {
    color: var(--danger);
}

/* ── 경로 지도 ── */
.detail-map {
    margin: 4px 0 10px;
}

.detail-map__frame {
    display: block;
    width: 100%;
    height: 220px;
    border: 0;
    border-radius: 12px;
}

.detail-map__placeholder {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 18px 16px;
    border: 1px dashed var(--border);
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.02);
}

.detail-map__label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
}

.detail-map__links {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
}

.detail-map__links a {
    color: var(--accent);
    font-weight: 600;
    text-decoration: none;
}

.detail-map__hint {
    color: var(--text-muted);
    font-size: 12px;
}

/* ── 하단 액션 바 ── */
.detail-actionbar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px calc(10px + env(safe-area-inset-bottom));
    background: color-mix(in srgb, var(--surface) 92%, transparent);
    backdrop-filter: blur(12px);
    border-top: 1px solid var(--border);
}

.detail-actionbar__primary {
    flex: 1;
    min-width: 0;
}

/* 채팅 버튼 — 등록자에게 안 읽은 채팅이 오면 빨간점 표시 */
.detail-chat-wrap {
    position: relative;
    display: inline-flex;
}

.detail-chat-wrap__dot {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ff4d4f;
    border: 2px solid var(--surface);
}
</style>