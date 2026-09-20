import { computed, ref } from 'vue';
import {
    apiAcceptOffer, apiApproveClaim, apiClaimOrder, apiCreateOffer, apiDeleteOffer, apiDetachOrder,
    apiOrder, apiOrderOffers, apiRejectClaim, apiRejectOffer, apiRequestOrderDetails, apiReviewOrder,
    apiToggleFavorite, apiTransitionOrder,
} from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import { statusColorVar } from '../utils/colors';
import { isPriorityOrder, isUrgentOrder } from '../utils/orderUrgency';

const SERVICE_LABELS = { pickup: '픽업', sending: '공항샌딩', point: '시내', landing: '공항랜딩' };
// 가져오기(claim) 대상 상태 — 승인 대기 중이어도 다른 드라이버는 추가 신청할 수 있다 (멀티 신청)
const CLAIMABLE_STATUSES = ['published', 'trading', 'acceptance_pending'];

// 상태 진행 순서 (취소는 흐름 밖, 거래중은 과거 데이터용으로 흐름에서 제외)
const STATUS_FLOW = ['draft', 'published', 'accepted', 'driving', 'completed', 'settled'];

/**
 * 운행 상세 — 데이터 로드, 파생 정보, 상태 변경/가져오기/리뷰/취소/분리를 담당한다.
 *
 * @param {object} options
 * @param {import('vue-router').RouteLocationNormalizedLoaded} options.route
 * @param {import('vue-router').Router} options.router
 * @param {object} options.auth useAuthStore
 * @param {object} options.chats useChatsStore
 * @param {object} options.naiveMessage naive-ui message
 */
export function useOrderDetail({ route, router, auth, chats, naiveMessage }) {
    const order = ref(null);
    const group = ref(null);
    const statusOptions = ref({});
    const nextTransitions = ref([]);
    // 운행 타임라인 — 상태·단계 변경 이력 (최신이 위)
    const timeline = ref([]);
    const loading = ref(true);
    const error = ref('');
    const acting = ref(false);
    const message = ref('');
    const messageType = ref('success');
    // 내가 이 운행을 찜했는지 — 히어로 하트 표시 (마켓 운행일 때만 노출)
    const favorited = ref(false);

    const currentStep = computed(() => {
        const index = STATUS_FLOW.indexOf(order.value?.status ?? '');

        return index === -1 ? 0 : index;
    });

    const isCancelled = computed(() => order.value?.status === 'cancelled');
    // 임박/긴급은 서버(OrderListRowBuilder)와 같은 규칙을 쓴다 — 목록 카드와 상세 배지가 갈라지지 않게
    const isPriority = computed(() => isPriorityOrder(order.value ?? {}));

    // 서비스 시각 (KST) — 임박/오늘/내일/카운트다운 판정
    const serviceTime = computed(() => {
        const date = order.value?.service_date;
        const time = order.value?.service_time;

        if (!date || !time) {
            return null;
        }

        const [h, m] = time.split(':').map(Number);
        const local = new Date(`${date}T00:00:00`);

        if (isNaN(local.getTime())) {
            return null;
        }

        local.setHours(h, m, 0, 0);

        return local;
    });

    const minutesToService = computed(() => {
        const st = serviceTime.value;

        return st ? Math.round((st.getTime() - Date.now()) / 60000) : null;
    });

    const isUrgent = computed(() => isUrgentOrder(order.value ?? {}));

    const isToday = computed(() => {
        const st = serviceTime.value;

        return st ? st.toDateString() === new Date().toDateString() : false;
    });

    const isTomorrow = computed(() => {
        const st = serviceTime.value;

        if (!st) {
            return false;
        }

        const t = new Date();
        t.setDate(t.getDate() + 1);

        return st.toDateString() === t.toDateString();
    });

    const serviceCountdownLabel = computed(() => {
        const mins = minutesToService.value;

        if (mins === null) {
            return '-';
        }
        if (mins < 0) {
            return '서비스 종료';
        }
        if (mins <= 120) {
            return `약 ${mins}분 후 시작`;
        }

        const st = serviceTime.value;

        return `${st.getHours()}:${String(st.getMinutes()).padStart(2, '0')}`;
    });

    const amountLabel = computed(() => {
        const v = order.value?.expected_revenue ?? order.value?.amount_value;

        if (v) {
            return `${Number(v).toLocaleString()}원`;
        }

        // 금액 미지정 운행은 '요금 협의' — 끝난 운행에서 비어 있으면 데이터 문제이므로 '-'
        const closed = ['completed', 'settled', 'cancelled'];

        return closed.includes(order.value?.status) ? '-' : '요금 협의';
    });

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
    // 본인 운행 여부 — 내가 가져온/등록한 운행은 가져오기 대상이 아니다
    const isMine = computed(() => Boolean(order.value && auth.user && order.value.user_id === auth.user.id));

    // 가져오기/제안은 기사(Driver) 역할만 가능하다 — 일반 관람자는 요청을 보낼 수 없다
    const isDriver = computed(() => Boolean(auth.user && auth.user.role === 'Driver'));

    // 내가 이미 대기 중인 신청이 있는지 — 있으면 중복 신청 버튼을 숨긴다
    const hasPendingClaimByMe = computed(() => claims.value.some((claim) => claim.driver_id === auth.user?.id));

    // 마켓 운행 가져오기 — 기사만, 남의 운행만, 이미 신청하지 않은 운행만 전체 넓이 버튼으로 노출
    const canClaim = computed(() =>
        Boolean(order.value && isClaimable.value && !isMine.value && isDriver.value && !hasPendingClaimByMe.value),
    );

    // 일반 관람자 안내 — 가져올 수 있는 운행이지만 기사가 아니라서 버튼 대신 안내를 보여준다
    const showDriverOnlyNotice = computed(() =>
        Boolean(order.value && isClaimable.value && !isMine.value && !isDriver.value),
    );

    // 내가 이 운행의 등록자(원 등록자)인지 — 완료 후 정산 처리 권한 판별
    const isRegistrant = computed(() => {
        const o = order.value;

        if (!o || !auth.user) {
            return false;
        }

        return o.original_owner_id === auth.user.id || (o.original_owner_id == null && o.user_id === auth.user.id);
    });

    // 내가 수행자(가져온 운행 진행자)인지 — 완료되면 '정산 대기중'으로 대기.
    // 직접 수행(등록자가 본인이 수행)도 같은 수행자로 본다 — 하단 스테퍼로 운행을 진행해야 하기 때문이다.
    const isSelfDriving = computed(() =>
        Boolean(
            order.value && auth.user
            && order.value.user_id === auth.user.id
            && order.value.original_owner_id === auth.user.id,
        ),
    );

    const isPerformer = computed(() =>
        Boolean(
            order.value && auth.user
            && order.value.user_id === auth.user.id
            && (isSelfDriving.value
                || (order.value.original_owner_id !== null && order.value.original_owner_id !== auth.user.id)),
        ),
    );

    // 상태 전이 가능 여부 — 운행 진행(예약→운행중→완료)은 운행자·수행자만, 정산은 등록자만.
    // 수행 기사는 완료가 마지막이고, 완료 후 정산은 등록자(원 등록자)가 처리한다.
    const canManageStatus = computed(() => {
        const o = order.value;

        if (!o || !auth.user) {
            return false;
        }

        // 승인 대기 중에는 전이 버튼을 노출하지 않는다 — 승인/거절은 처리할 일에서 진행
        if (o.status === 'acceptance_pending') {
            return false;
        }

        if (o.user_id === auth.user.id || o.claimant_user_id === auth.user.id) {
            return true;
        }

        // 완료 → 정산 전이는 등록자(원 등록자)만 가능
        return o.status === 'completed' && isRegistrant.value;
    });

    // 내가 등록한 공개 운행을 기사 모집 없이 직접 수행할 수 있는지 —
    // 다른 기사가 이미 신청한 운행은 신청자를 앞질러 가로채지 않는다.
    const canSelfDrive = computed(() =>
        Boolean(
            order.value && auth.user
            && order.value.status === 'published'
            && order.value.user_id === auth.user.id
            && claims.value.length === 0,
        ),
    );

    // 상태 라벨 — 완료(정산 전)는 '정산 대기중'으로 표시.
    // 승인 대기 상태는 요청을 보낸 기사(claimant)와 등록자에게만 '수락 대기'로 보여주고,
    // 그 외 드라이버/관람자에게는 아직 가져올 수 있는 운행으로 안내한다.
    const statusLabel = computed(() => {
        const status = order.value?.status;

        if (status === 'completed') {
            return '정산 대기중';
        }

        if (status === 'acceptance_pending' && !isClaimantPending.value && !isRegistrantPending.value) {
            return '가져오기 가능';
        }

        return statusOptions.value[status] ?? status ?? '-';
    });

    // 리뷰 — 운행 완료/정산 후, 운행 당사자(등록자·수행자)만 작성 가능. 이미 작성했으면 더 이상 표시하지 않는다
    const myReview = ref(null);
    const canReview = computed(() =>
        Boolean(
            order.value
            && ['completed', 'settled'].includes(order.value.status)
            && !myReview.value
            && (isRegistrant.value || order.value.user_id === auth.user?.id),
        ),
    );
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
            naiveMessage.warning('리뷰 내용을 입력해 주세요.');

            return;
        }

        reviewSubmitting.value = true;

        try {
            await apiReviewOrder(order.value.id, {
                rating: reviewRating.value,
                content: reviewContent.value.trim(),
            });
            naiveMessage.success('리뷰를 남겼습니다.');
            myReview.value = { rating: reviewRating.value };
            reviewOpen.value = false;
        } catch (e) {
            naiveMessage.error(getApiErrorMessage(e, '리뷰 작성에 실패했습니다.'));
        } finally {
            reviewSubmitting.value = false;
        }
    };

    // 채팅 상대 — 운행 참여자(등록자·소유자·가져오기 신청자들) 중 나와 다른 사람.
    // 승인으로 소유권이 넘어가도 대화는 계속 유지한다. 멀티 신청 시 모든 신청자도 참여자로 본다.
    const chatTargetId = computed(() => {
        const o = order.value;

        if (!o) {
            return null;
        }

        const me = auth.user?.id;
        const ownerId = o.user_id;
        const registrantId = o.original_owner_id ?? o.user_id;
        const claimantId = o.claimant_user_id ?? null;

        // 승인 대기 중에는 모든 신청자(드라이버)도 참여자로 취급한다
        const applicantIds = claims.value.map((claim) => claim.driver_id).filter(Boolean);

        const involved = new Set([ownerId, registrantId, claimantId, ...applicantIds].filter(Boolean));

        // 내가 참여자이고, 나와 대화할 상대가 있어야 한다
        if (!involved.has(me) || involved.size < 2) {
            return null;
        }

        return [ownerId, registrantId, claimantId, ...applicantIds].find((id) => id && id !== me) ?? null;
    });

    // 채팅: 운행 참여자(타인)와 대화 가능할 때만 버튼 노출
    const canChat = computed(() => Boolean(chatTargetId.value));

    // 채팅 상대가 보낸 안 읽은 채팅이 있으면 채팅 버튼에 빨간점을 표시한다.
    // 같은 운행에 대화가 여러 개 생길 수 있어(이전 수행자 등) 현재 채팅 상대와 일치하는 대화만 본다.
    const hasRegistrantChat = computed(() => {
        if (!order.value?.id || !chatTargetId.value) {
            return false;
        }

        const conversation = chats.conversations.find(
            (c) => c.order_id === order.value.id && c.counterpart?.id === chatTargetId.value,
        );

        if (!conversation) {
            return false;
        }

        // 대화 상대(등록자 등)가 보낸 안 읽은 메시지가 있을 때
        return (conversation.unread_count ?? 0) > 0;
    });

    // 수정: 운행 등록자(원 등록자)만, 초안/공개 상태에서만
    const canEdit = computed(() => Boolean(
        order.value
        && isRegistrant.value
        && ['draft', 'published'].includes(order.value.status),
    ));

    const goEdit = () => router.push({ name: 'order-edit', params: { id: order.value.id } });

    // ── 가져오기 승인 대기 — 등록자는 신청자별 승인/거절, 요청자는 대기 상태 ──
    const isClaimPending = computed(() => order.value?.status === 'acceptance_pending');
    // 내가 등록한 운행에 쌓인 가져오기 요청(신청 건) 목록 — 여러 드라이버가 동시에 신청할 수 있다
    const claims = ref([]);
    // 내가 등록한 운행의 가져오기 요청이 대기 중인 경우
    const isRegistrantPending = computed(() =>
        Boolean(isClaimPending.value && order.value.user_id === auth.user?.id),
    );
    // 내가 가져오기를 요청하고 등록자 승인을 기다리는 경우 — 신청 목록 중 내 신청이 있는지로 판별.
    // 신청 건 기록이 없는 레거시(마이그레이션 이전) 신청도 claimant_user_id로 판별한다.
    const isClaimantPending = computed(() =>
        Boolean(
            isClaimPending.value
            && auth.user
            && (
                claims.value.some((claim) => claim.driver_id === auth.user.id)
                || (
                    claims.value.length === 0
                    && order.value?.claimant_user_id === auth.user.id
                )
            ),
        ),
    );

    // 상태 관리 카드 노출 여부 — 운행 참여자(등록자·운행자·수행자·신청자)만 보고,
    // 일반 관람 유저에게는 노출하지 않는다.
    const canSeeStatusManagement = computed(() => {
        // 수행 기사는 하단 스테퍼로 운행을 진행하므로 상태 관리 UI는 노출하지 않는다
        if (isPerformer.value) {
            return false;
        }

        // 승인 대기 중에는 상태 관리가 필요 없다 — 요청 승인 전에는 상태 전이가 없기 때문이다
        if (order.value?.status === 'acceptance_pending') {
            return false;
        }

        return Boolean(
            canManageStatus.value
            || canEdit.value
            || canChat.value
            || isRegistrantPending.value
            || isClaimantPending.value,
        );
    });

    // 요청 대기 중 배지 — 내 가져오기 요청이 승인 대기 중일 때
    const isWaitingClaims = isClaimantPending;

    // ── 공용 확인 다이얼로그 — 모든 상태 변경은 확인 후 진행한다 ──
    const confirmState = ref({ open: false, title: '확인', message: '', confirmText: '확인', type: 'primary', onConfirm: null });

    const askConfirm = ({ title, message, confirmText = '확인', type = 'primary', onConfirm }) => {
        confirmState.value = { open: true, title, message, confirmText, type, onConfirm };
    };

    const closeConfirm = () => {
        confirmState.value.open = false;
    };

    const doConfirm = async () => {
        const action = confirmState.value.onConfirm;

        closeConfirm();
        if (action) {
            await action();
        }
    };

    // 가져오기 거절 사유 모달 — 등록자가 사유(선택)와 함께 신청을 거절
    const rejectOpen = ref(false);
    const rejectReason = ref('');
    const rejectTarget = ref(null);
    const rejectSubmitting = ref(false);

    const approveClaim = (claim) => {
        askConfirm({
            title: '가져오기 승인',
            message: `${claim.driver_name}님이 이 운행을 가져오기 요청했습니다.\n승인하면 운행이 기사에게 넘어가고 진행할 수 있습니다. 다른 신청은 자동으로 거절됩니다.`,
            confirmText: '승인',
            type: 'primary',
            onConfirm: async () => {
                acting.value = true;
                message.value = '';

                try {
                    await apiApproveClaim(order.value.id, claim.claim_id);
                    message.value = '가져오기를 승인했습니다. 기사가 운행을 진행할 수 있습니다.';
                    messageType.value = 'success';
                    await refresh();
                } catch (e) {
                    message.value = getApiErrorMessage(e, '승인에 실패했습니다.');
                    messageType.value = 'error';
                } finally {
                    acting.value = false;
                }
            },
        });
    };

    const rejectClaim = (claim) => {
        rejectOpen.value = true;
        rejectTarget.value = claim;
        rejectReason.value = '';
    };

    // 거절 사유(선택)를 기록해 기사에게 전달 — 입력 없이 바로 거절할 수도 있다
    const submitRejectClaim = async () => {
        const claim = rejectTarget.value;

        if (!claim) return;

        rejectSubmitting.value = true;
        message.value = '';

        try {
            const reason = rejectReason.value.trim();
            await apiRejectClaim(order.value.id, claim.claim_id, reason ? { reason } : {});
            message.value = '가져오기를 거절했습니다.';
            messageType.value = 'success';
            rejectOpen.value = false;
            await refresh();
        } catch (e) {
            message.value = getApiErrorMessage(e, '거절에 실패했습니다.');
            messageType.value = 'error';
        } finally {
            rejectSubmitting.value = false;
        }
    };

    // 가져오기 요청을 내가 직접 철회 — 등록자 승인 전에 마음을 바꿀 수 있다
    const withdrawClaim = () => {
        askConfirm({
            title: '가져오기 요청 철회',
            message: '가져오기 요청을 철회할까요?\n철회하면 운행은 마켓에 그대로 남습니다.',
            confirmText: '철회',
            type: 'warning',
            onConfirm: () => run(() => apiTransitionOrder(order.value.id, 'published'), '가져오기 요청을 철회했습니다.'),
        });
    };

    // 상세 정보 요청 — 운행 정보가 부족할 때 사유·메모를 골라 등록자에게 요청한다
    const detailRequestOpen = ref(false);
    const detailRequestReason = ref('');
    const detailRequestMessage = ref('');
    const detailRequestSubmitting = ref(false);

    const requestDetails = () => {
        detailRequestReason.value = '';
        detailRequestMessage.value = '';
        detailRequestOpen.value = true;
    };

    const submitDetailRequest = async () => {
        if (!detailRequestReason.value.trim() && !detailRequestMessage.value.trim()) {
            naiveMessage.warning('요청 사유 또는 메모를 입력해 주세요.');

            return;
        }

        detailRequestSubmitting.value = true;

        try {
            await apiRequestOrderDetails(order.value.id, {
                reason: detailRequestReason.value.trim() || null,
                message: detailRequestMessage.value.trim() || null,
            });
            naiveMessage.success('등록자에게 상세 정보 입력을 요청했습니다.');
            detailRequestOpen.value = false;
        } catch (e) {
            naiveMessage.error(getApiErrorMessage(e, '요청을 보내지 못했습니다.'));
        } finally {
            detailRequestSubmitting.value = false;
        }
    };

    // ── 요금 제안(오퍼) — 기사가 운임을 제안하면 등록자가 비교 후 수락/거절 ──
    const offers = ref([]);
    const offersLoading = ref(false);

    // 공개/거래중 운행만 제안 대상 (본인 운행·비기사 제외) — 요금 제안은 기사만, 승인 대기 제외
    const canOffer = computed(() => Boolean(
        order.value
        && ['published', 'trading'].includes(order.value.status)
        && !isMine.value
        && isDriver.value,
    ));

    // 나의 대기 제안 — 기사가 이미 제안했으면 철회 가능
    const myPendingOffer = computed(
        () => offers.value.find((o) => o.status === 'pending' && o.driver?.id === auth.user?.id) ?? null,
    );

    const loadOffers = async () => {
        if (!order.value?.id || !['published', 'trading'].includes(order.value.status)) {
            offers.value = [];

            return;
        }
        if (!canOffer.value && order.value.user_id !== auth.user?.id) {
            offers.value = [];

            return;
        }
        offersLoading.value = true;
        try {
            const { data } = await apiOrderOffers(order.value.id);
            offers.value = data.data ?? [];
        } catch {
            offers.value = [];
        } finally {
            offersLoading.value = false;
        }
    };

    // 제안 작성 모달
    const offerOpen = ref(false);
    const offerAmount = ref(null);
    const offerMessage = ref('');
    const offerSubmitting = ref(false);

    const openOffer = () => {
        offerAmount.value = order.value?.expected_revenue ?? null;
        offerMessage.value = '';
        offerOpen.value = true;
    };

    const submitOffer = async () => {
        const amount = Number(offerAmount.value);

        if (!Number.isFinite(amount) || amount < 1000) {
            naiveMessage.warning('제안 금액을 입력해 주세요 (최소 1,000원).');

            return;
        }

        offerSubmitting.value = true;

        try {
            await apiCreateOffer(order.value.id, { amount, message: offerMessage.value.trim() });
            naiveMessage.success('요금 제안을 보냈습니다. 등록자의 수락을 기다려 주세요.');
            offerOpen.value = false;
            await load();
            await loadOffers();
        } catch (e) {
            naiveMessage.error(getApiErrorMessage(e, '요금 제안에 실패했습니다.'));
        } finally {
            offerSubmitting.value = false;
        }
    };

    const acceptOffer = (offer) => {
        askConfirm({
            title: '요금 제안 수락',
            message: `${offer.driver?.name}님의 ${Number(offer.amount).toLocaleString()}원 제안을 수락할까요?\n수락하면 운행이 해당 기사에게 넘어갑니다.`,
            confirmText: '수락',
            type: 'primary',
            onConfirm: async () => {
                acting.value = true;

                try {
                    await apiAcceptOffer(order.value.id, offer.id);
                    await load();
                    naiveMessage.success(`제안을 수락했습니다. ${Number(offer.amount).toLocaleString()}원에 딜이 성사되어 운행이 기사에게 넘어갔습니다.`);
                    await loadOffers();
                } catch (e) {
                    naiveMessage.error(getApiErrorMessage(e, '제안 수락에 실패했습니다.'));
                } finally {
                    acting.value = false;
                }
            },
        });
    };

    const rejectOffer = (offer) => {
        askConfirm({
            title: '요금 제안 거절',
            message: `${offer.driver?.name}님의 제안을 거절할까요? 운행은 마켓에 그대로 남습니다.`,
            confirmText: '거절',
            type: 'error',
            onConfirm: async () => {
                acting.value = true;

                try {
                    await apiRejectOffer(order.value.id, offer.id);
                    naiveMessage.success('제안을 거절했습니다.');
                    await loadOffers();
                } catch (e) {
                    naiveMessage.error(getApiErrorMessage(e, '제안 거절에 실패했습니다.'));
                } finally {
                    acting.value = false;
                }
            },
        });
    };

    const withdrawOffer = (offer) => {
        askConfirm({
            title: '요금 제안 철회',
            message: '보낸 요금 제안을 철회할까요?',
            confirmText: '철회',
            type: 'warning',
            onConfirm: async () => {
                acting.value = true;

                try {
                    await apiDeleteOffer(order.value.id, offer.id);
                    naiveMessage.success('요금 제안을 철회했습니다.');
                    await loadOffers();
                } catch (e) {
                    naiveMessage.error(getApiErrorMessage(e, '제안 철회에 실패했습니다.'));
                } finally {
                    acting.value = false;
                }
            },
        });
    };

    const openChat = async () => {
        if (!chatTargetId.value) {
            return;
        }

        try {
            await chats.openWith(chatTargetId.value, order.value.id);
            router.push({ name: 'chat' });
        } catch (e) {
            message.value = getApiErrorMessage(e, '채팅을 시작하지 못했습니다.');
            messageType.value = 'error';
        }
    };

    // 등록자 공개 프로필로 이동
    const goUserPage = (id) => {
        if (id) {
            router.push({ name: 'user-page', params: { id } });
        }
    };

    // 하단 액션 바의 주 동작 — 직접 수행 > 가져오기 요청 철회(요청자) > 다음 상태 전이 > 리뷰
    const primaryAction = computed(() => {
        // 내가 등록한 공개 운행 — 기사 모집 없이 바로 수행하는 것이 가장 흔한 다음 행동이다
        if (canSelfDrive.value) {
            return { label: '내가 직접 수행하기', indicator: false, handler: selfDrive };
        }
        // 승인 대기 중인 가져오기 요청은 '수락 대기 중' 표시 버튼으로 안내한다
        if (isClaimantPending.value) {
            return { label: '수락 대기 중', indicator: true, handler: null };
        }
        if (nextTransitions.value.length && canManageStatus.value) {
            const next = nextTransitions.value[0];

            // 완료 → 정산은 등록자만 — 수행 기사는 완료가 마지막 (버튼 숨김)
            if (next === 'settled' && order.value?.status === 'completed' && !isRegistrant.value) {
                // 리뷰 남기기는 박스 밖 내용 하단 버튼으로 제공
                return null;
            }

            return { label: `→ ${statusOptions.value[next] ?? next}`, handler: () => requestTransition(next) };
        }

        return null;
    });

    // 하단 주동작이 상태 전이일 때의 대상 상태 (색상 일관 적용용)
    const primaryActionStatus = computed(() => nextTransitions.value[0] ?? null);

    // naive-ui 버튼의 color prop은 CSS var를 직접 받지 못하므로 실제 색상으로 변환한다.
    // CSS var 문자열('var(--x)')이 아닌 값은 그대로 반환한다.
    const resolveCssVarColor = (cssVar) => {
        if (!cssVar || !cssVar.startsWith('var(')) {
            return cssVar || undefined;
        }

        const name = cssVar.slice(4, -1).trim();

        return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || undefined;
    };

    const statusButtonColor = (status) => {
        const cssVar = statusColorVar[status];

        return resolveCssVarColor(cssVar);
    };

    // 그룹 일정 행으로 변환 (셋트 운행일 때 그룹 내 모든 운행)
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

    // 진행 단계별 스타일: 지난 단계=해당 색, 현재=채움+링, 진행 전=회색
    const stepStyle = (status, index) => {
        const color = statusColorVar[status] ?? 'var(--status-draft)';

        if (index === currentStep.value) {
            return {
                background: color,
                borderColor: color,
                color: '#ffffff',
                boxShadow: `0 0 0 4px color-mix(in srgb, ${color} 30%, transparent)`,
            };
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
        if (isClaimable.value && !isMine.value) {
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
        timeline.value = data.data.timeline ?? [];
        myReview.value = data.data.my_review ?? null;
        claims.value = data.data.claims ?? [];
        favorited.value = Boolean(data.data.favorited);
    };

    const refresh = async () => {
        loading.value = true;
        error.value = '';

        try {
            await load();
            await loadOffers();
        } catch (e) {
            error.value = getApiErrorMessage(e, '운행을 불러오지 못했습니다.');
        } finally {
            loading.value = false;
        }

        // 등록자 채팅 여부(빨간점)를 최신으로 갱신
        await chats.loadConversations().catch(() => {});
    };

    // 승인 대기 상태 폴링/SSE용 — 로딩 플래시 없이 조용히 최신 상태만 반영한다
    const refreshSilently = async () => {
        try {
            await load();
            await loadOffers();
        } catch (e) {
            // 조용히 실패 — 다음 갱신에서 재시도
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

    // 가져오기 — 요청 후 등록자 승인을 기다리는 상태로 전환 (확인 후 진행)
    const claim = () => {
        askConfirm({
            title: '운행 가져오기',
            message: '이 운행을 내 운행으로 가져올까요?\n가져오기 요청이 등록자에게 전달되고, 승인하면 운행할 수 있습니다.',
            confirmText: '가져오기',
            type: 'primary',
            onConfirm: async () => {
                acting.value = true;

                try {
                    await apiClaimOrder(order.value.id);
                    await load();
                    naiveMessage.success('가져오기 요청을 보냈습니다. 운행 등록자가 승인하면 운행을 진행할 수 있습니다.');
                } catch (e) {
                    naiveMessage.error(getApiErrorMessage(e, '운행 가져오기에 실패했습니다.'));
                } finally {
                    acting.value = false;
                }
            },
        });
    };

    // 상태 전이 — 모든 운행 상태 변경은 확인 다이얼로그 후 진행
    const transition = (status) => {
        const label = statusOptions.value[status] ?? status;

        askConfirm({
            title: '운행 상태 변경',
            message: `운행 상태를 "${label}"(으)로 변경할까요?`,
            confirmText: '변경',
            type: 'primary',
            onConfirm: () => run(() => apiTransitionOrder(order.value.id, status), `상태가 "${label}"로 변경되었습니다.`),
        });
    };

    // ── 취소 사유 입력 ──
    const cancelOpen = ref(false);
    const cancelReason = ref('');

    // ── 운행 완료 시 실제 수익 입력 (상태 관리 카드 경로) ──
    const completionOpen = ref(false);
    const completionRevenue = ref(null);

    const openCompletion = () => {
        completionRevenue.value = order.value?.actual_revenue ?? order.value?.expected_revenue ?? null;
        completionOpen.value = true;
    };

    const confirmComplete = async () => {
        const revenue = completionRevenue.value == null ? null : Number(completionRevenue.value);
        completionOpen.value = false;
        const revenueArg = Number.isFinite(revenue) ? revenue : null;

        await run(
            () => apiTransitionOrder(order.value.id, 'completed', '', revenueArg),
            '운행이 완료되었습니다.',
        );
    };

    const requestTransition = (status) => {
        if (status === 'cancelled') {
            cancelReason.value = '';
            cancelOpen.value = true;
        } else if (status === 'completed') {
            // 상태 관리 카드/주동작 경로 — 단계 기록 없이 바로 완료 전이
            openCompletion();
        } else {
            transition(status);
        }
    };

    // 직접 수행 — 기사 모집 없이 등록자가 자기 운행을 바로 수행 확정한다.
    // 공개가 즉시 중단되고(마켓에서 내려감) 정산이 0으로 마감되므로, 실행 전에 경고로 확인받는다.
    const selfDrive = () => {
        askConfirm({
            title: '직접 운행하시겠습니까?',
            message: '이 운행은 지금 바로 마켓에서 내려가고 기사 모집이 중단됩니다.\n'
                + '본인이 수행하므로 등록자 입금·플랫폼 수수료·기사 지급이 모두 발생하지 않습니다(수금 0).\n'
                + '확정하면 되돌릴 수 없습니다.',
            confirmText: '직접 운행',
            type: 'warning',
            onConfirm: () => run(
                () => apiTransitionOrder(order.value.id, 'accepted'),
                '이 운행을 직접 수행합니다.',
            ),
        });
    };

    const confirmCancel = async () => {
        cancelOpen.value = false;
        await run(
            () => apiTransitionOrder(order.value.id, 'cancelled', cancelReason.value),
            '운행이 취소되었습니다.',
        );
    };

    // 셋트 그룹에서 개별 운행 분리
    const detach = (siblingId) =>
        run(() => apiDetachOrder(siblingId), '셋트 그룹에서 분리되었습니다.');

    // 찜(즐겨찾기) 토글 — 하트를 누르면 상태를 뒤집는다 (마켓 운행만 가능)
    const toggleFavorite = async () => {
        if (!order.value) {
            return;
        }

        try {
            const { data } = await apiToggleFavorite(order.value.id);
            favorited.value = Boolean(data?.data?.favorited);
        } catch (e) {
            naiveMessage.error(getApiErrorMessage(e, '찜 처리에 실패했습니다.'));
        }
    };

    return {
        order,
        group,
        statusOptions,
        nextTransitions,
        timeline,
        loading,
        error,
        acting,
        message,
        messageType,
        currentStep,
        isCancelled,
        isPriority,
        serviceTime,
        minutesToService,
        isUrgent,
        isToday,
        isTomorrow,
        serviceCountdownLabel,
        amountLabel,
        serviceDatetimeLabel,
        isClaimable,
        isMine,
        isDriver,
        canClaim,
        showDriverOnlyNotice,
        isRegistrant,
        isPerformer,
        statusLabel,
        canReview,
        myReview,
        favorited,
        toggleFavorite,
        reviewOpen,
        reviewRating,
        reviewContent,
        reviewSubmitting,
        openReview,
        submitReview,
        canChat,
        chatTargetId,
        hasRegistrantChat,
        canEdit,
        goEdit,
        canManageStatus,
        canSeeStatusManagement,
        isClaimPending,
        claims,
        isRegistrantPending,
        isClaimantPending,
        isWaitingClaims,
        confirmState,
        askConfirm,
        closeConfirm,
        doConfirm,
        approveClaim,
        rejectClaim,
        rejectOpen,
        rejectReason,
        rejectSubmitting,
        submitRejectClaim,
        withdrawClaim,
        requestDetails,
        detailRequestOpen,
        detailRequestReason,
        detailRequestMessage,
        detailRequestSubmitting,
        submitDetailRequest,
        offers,
        offersLoading,
        canOffer,
        myPendingOffer,
        offerOpen,
        offerAmount,
        offerMessage,
        offerSubmitting,
        openOffer,
        submitOffer,
        acceptOffer,
        rejectOffer,
        withdrawOffer,
        openChat,
        goUserPage,
        primaryAction,
        primaryActionStatus,
        statusButtonColor,
        resolveCssVarColor,
        groupOrderRows,
        groupTotalAmount,
        stepStyle,
        lineItems,
        statusTagType,
        SERVICE_LABELS,
        STATUS_FLOW,
        load,
        refresh,
        refreshSilently,
        run,
        claim,
        transition,
        cancelOpen,
        cancelReason,
        requestTransition,
        confirmCancel,
        completionOpen,
        completionRevenue,
        confirmComplete, detach,
    };
}
