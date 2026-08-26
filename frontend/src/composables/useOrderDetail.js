import { computed, ref } from 'vue';
import {
    apiAcceptOffer, apiApproveClaim, apiClaimOrder, apiCreateOffer, apiDeleteOffer, apiDetachOrder,
    apiOrder, apiOrderOffers, apiRejectClaim, apiRejectOffer, apiReviewOrder, apiTransitionOrder,
} from '../api/orders';
import { getApiErrorMessage } from '../api/client';
import { statusColorVar } from '../utils/colors';

const SERVICE_LABELS = { pickup: '픽업', sending: '공항샌딩', landing: '공항랜딩' };
const CLAIMABLE_STATUSES = ['published', 'trading'];

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
    const loading = ref(true);
    const error = ref('');
    const acting = ref(false);
    const message = ref('');
    const messageType = ref('success');

    const currentStep = computed(() => {
        const index = STATUS_FLOW.indexOf(order.value?.status ?? '');

        return index === -1 ? 0 : index;
    });

    const isCancelled = computed(() => order.value?.status === 'cancelled');
    const isPriority = computed(() => Boolean(order.value?.is_priority));

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

    const isUrgent = computed(() => {
        const mins = minutesToService.value;

        return mins !== null && mins > 0 && mins <= 120;
    });

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

        return v ? `${Number(v).toLocaleString()}원` : '-';
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

    // 내가 이 운행의 등록자(원 등록자)인지 — 완료 후 정산 처리 권한 판별
    const isRegistrant = computed(() => {
        const o = order.value;

        if (!o || !auth.user) {
            return false;
        }

        return o.original_owner_id === auth.user.id || (o.original_owner_id == null && o.user_id === auth.user.id);
    });

    // 내가 수행자(가져온 운행 진행자)인지 — 완료되면 '정산 진행중'으로 대기
    const isPerformer = computed(() =>
        Boolean(
            order.value && auth.user
            && order.value.user_id === auth.user.id
            && order.value.original_owner_id !== null
            && order.value.original_owner_id !== auth.user.id,
        ),
    );

    // 상태 라벨 — 완료(정산 전)는 '정산 진행중'으로 표시
    const statusLabel = computed(() => {
        const status = order.value?.status;

        if (status === 'completed') {
            return '정산 진행중';
        }

        return statusOptions.value[status] ?? status ?? '-';
    });

    // 리뷰 — 운행 완료/정산 후 작성 가능. 이미 작성했으면 더 이상 표시하지 않는다
    const myReview = ref(null);
    const canReview = computed(() =>
        Boolean(order.value && ['completed', 'settled'].includes(order.value.status) && !myReview.value),
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
            myReview.value = { rating: reviewRating.value };
            reviewOpen.value = false;
        } catch (e) {
            naiveMessage.error(getApiErrorMessage(e, '리뷰 작성에 실패했습니다.'));
        } finally {
            reviewSubmitting.value = false;
        }
    };

    // 채팅 상대 — 운행 참여자(등록자·소유자·가져오기 요청자) 중 나와 다른 사람.
    // 승인으로 소유권이 넘어가도 대화는 계속 유지한다.
    const chatTargetId = computed(() => {
        const o = order.value;

        if (!o) {
            return null;
        }

        const me = auth.user?.id;
        const ownerId = o.user_id;
        const registrantId = o.original_owner_id ?? o.user_id;
        const claimantId = o.claimant_user_id ?? null;

        const involved = new Set([ownerId, registrantId, claimantId].filter(Boolean));

        // 내가 참여자이고, 나와 대화할 상대가 있어야 한다
        if (!involved.has(me) || involved.size < 2) {
            return null;
        }

        return [ownerId, registrantId, claimantId].find((id) => id && id !== me) ?? null;
    });

    // 채팅: 운행 참여자(타인)와 대화 가능할 때만 버튼 노출
    const canChat = computed(() => Boolean(chatTargetId.value));

    // 채팅 상대가 보낸 안 읽은 채팅이 있으면 채팅 버튼에 빨간점을 표시한다
    const hasRegistrantChat = computed(() => {
        if (!order.value?.id || !chatTargetId.value) {
            return false;
        }

        const conversation = chats.conversations.find((c) => c.order_id === order.value.id);

        if (!conversation) {
            return false;
        }

        // 대화 상대(등록자 등)가 보낸 안 읽은 메시지가 있을 때
        return (conversation.unread_count ?? 0) > 0 && conversation.counterpart?.id === chatTargetId.value;
    });

    // 수정: 초안/공개 상태에서만
    const canEdit = computed(() => Boolean(order.value && ['draft', 'published'].includes(order.value.status)));

    const goEdit = () => router.push({ name: 'order-edit', params: { id: order.value.id } });

    // ── 가져오기 승인 대기 — 등록자는 승인/거절, 요청자는 대기 상태 ──
    const isClaimPending = computed(() => order.value?.status === 'acceptance_pending');
    // 내가 등록한 운행의 가져오기 요청이 대기 중인 경우
    const isRegistrantPending = computed(() =>
        Boolean(isClaimPending.value && order.value.user_id === auth.user?.id),
    );
    // 내가 가져오기를 요청하고 등록자 승인을 기다리는 경우
    const isClaimantPending = computed(() =>
        Boolean(isClaimPending.value && order.value.claimant_user_id === auth.user?.id),
    );

    // 요청 대기중 배지 — 내 가져오기 요청이 승인 대기 중일 때
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

    const approveClaim = () => {
        askConfirm({
            title: '가져오기 승인',
            message: '드라이버가 이 운행을 가져오기 요청했습니다.\n승인하면 운행이 드라이버에게 넘어가고 진행할 수 있습니다.',
            confirmText: '승인',
            type: 'primary',
            onConfirm: async () => {
                acting.value = true;
                message.value = '';

                try {
                    await apiApproveClaim(order.value.id);
                    message.value = '가져오기를 승인했습니다. 드라이버가 운행을 진행할 수 있습니다.';
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

    const rejectClaim = () => {
        askConfirm({
            title: '가져오기 거절',
            message: '가져오기 요청을 거절할까요?\n거절하면 운행은 마켓에 그대로 남습니다.',
            confirmText: '거절',
            type: 'error',
            onConfirm: async () => {
                acting.value = true;
                message.value = '';

                try {
                    await apiRejectClaim(order.value.id);
                    message.value = '가져오기를 거절했습니다. 운행은 마켓에 그대로 남습니다.';
                    messageType.value = 'success';
                    await refresh();
                } catch (e) {
                    message.value = getApiErrorMessage(e, '거절에 실패했습니다.');
                    messageType.value = 'error';
                } finally {
                    acting.value = false;
                }
            },
        });
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

    // ── 요금 제안(오퍼) — 기사가 운임을 제안하면 등록자가 비교 후 수락/거절 ──
    const offers = ref([]);
    const offersLoading = ref(false);

    // 공개/거래중 운행만 제안 대상 (본인 운행 제외)
    const canOffer = computed(() => Boolean(order.value && isClaimable.value && !isMine.value));

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
                    naiveMessage.success('제안을 수락했습니다. 운행이 기사에게 넘어갔습니다.');
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

    // 하단 액션 바의 주 동작 — 가져오기 요청 철회(요청자) > 가져오기(남의 운행만) > 다음 상태 전이 > 리뷰
    const primaryAction = computed(() => {
        // 승인 대기 중인 가져오기 요청은 내가 직접 철회할 수 있다
        if (isClaimantPending.value) {
            return { label: '가져오기 요청 철회', handler: withdrawClaim };
        }
        if (isClaimable.value && !isMine.value) {
            return { label: '내 운행으로 가져오기', handler: claim };
        }
        if (nextTransitions.value.length) {
            const next = nextTransitions.value[0];

            // 완료 → 정산은 등록자만 — 진행자는 '정산 진행중'으로 대기 (버튼 숨김)
            if (next === 'settled' && order.value?.status === 'completed' && !isRegistrant.value) {
                if (canReview.value) {
                    return { label: '리뷰 남기기', handler: openReview };
                }

                return null;
            }

            return { label: `→ ${statusOptions.value[next] ?? next}`, handler: () => requestTransition(next) };
        }
        if (canReview.value) {
            return { label: '리뷰 남기기', handler: openReview };
        }

        return null;
    });

    // 하단 주동작이 상태 전이일 때의 대상 상태 (색상 일관 적용용)
    const primaryActionStatus = computed(() => nextTransitions.value[0] ?? null);

    // naive-ui 버튼의 color prop은 CSS var를 직접 받지 못하므로 실제 색상으로 변환한다.
    const statusButtonColor = (status) => {
        const cssVar = statusColorVar[status];

        if (!cssVar || !cssVar.startsWith('var(')) {
            return undefined;
        }

        const name = cssVar.slice(4, -1).trim();

        return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || undefined;
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
        myReview.value = data.data.my_review ?? null;
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

    // ── 운행 완료 시 실제 수익 입력 ──
    const completionOpen = ref(false);
    const completionRevenue = ref(null);

    const openCompletion = () => {
        completionRevenue.value = order.value?.actual_revenue ?? order.value?.expected_revenue ?? null;
        completionOpen.value = true;
    };

    const confirmComplete = async () => {
        const revenue = completionRevenue.value == null ? null : Number(completionRevenue.value);
        completionOpen.value = false;

        await run(
            () => apiTransitionOrder(order.value.id, 'completed', '', Number.isFinite(revenue) ? revenue : null),
            '운행이 완료되었습니다.',
        );
    };

    const requestTransition = (status) => {
        if (status === 'cancelled') {
            cancelReason.value = '';
            cancelOpen.value = true;
        } else if (status === 'completed') {
            openCompletion();
        } else {
            transition(status);
        }
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

    return {
        order,
        group,
        statusOptions,
        nextTransitions,
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
        isRegistrant,
        isPerformer,
        statusLabel,
        canReview,
        myReview,
        reviewOpen,
        reviewRating,
        reviewContent,
        reviewSubmitting,
        openReview,
        submitReview,
        canChat,
        hasRegistrantChat,
        canEdit,
        goEdit,
        isClaimPending,
        isRegistrantPending,
        isClaimantPending,
        isWaitingClaims,
        confirmState,
        askConfirm,
        closeConfirm,
        doConfirm,
        approveClaim,
        rejectClaim,
        withdrawClaim,
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
        groupOrderRows,
        groupTotalAmount,
        stepStyle,
        lineItems,
        statusTagType,
        SERVICE_LABELS,
        STATUS_FLOW,
        load,
        refresh,
        run,
        claim,
        transition,
        cancelOpen,
        cancelReason,
        requestTransition,
        confirmCancel,
        completionOpen,
        completionRevenue,
        confirmComplete,
        detach,
    };
}
