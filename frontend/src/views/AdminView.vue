<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useDialog, useMessage } from 'naive-ui';
import {
    apiAdminAutoOrderSettings,
    apiAdminAutoOrders,
    apiAdminDeleteAutoOrders,
    apiAdminDrivers,
    apiAdminPayoutPay,
    apiAdminPayoutReject,
    apiAdminPayouts,
    apiAdminSetDriverStatus,
    apiAdminSetUserFeeRate,
    apiAdminSetUserRole,
    apiAdminUpdateAutoOrderSettings,
    apiAdminUpdateVerification,
    apiAdminUsers,
} from '../api/admin';
import { apiAdminCollections, apiAdminCollectSettlement } from '../api/settlement';
import { getApiErrorMessage } from '../api/client';
import { useAuthStore } from '../stores/auth';
import {
    apiAdminConversationMessages,
    apiAdminModerateConversation,
    apiAdminModerateUser,
    apiAdminOperationAudit,
    apiAdminOperationConversations,
    apiAdminOperationDaily,
    apiAdminOperationMeta,
    apiAdminOperationMetrics,
    apiAdminOperationOrders,
    apiAdminOperationSettlements,
    apiAdminOrderForceCancel,
    apiAdminOrderHide,
    apiAdminOrderHold,
    apiAdminSettlementHold,
} from '../api/operations';
import { apiAdminAdvanceReport, apiAdminReports, apiReportOptions } from '../api/reports';
import {
    apiAdminAnswerSupportTicket,
    apiAdminCreateSupportPost,
    apiAdminDeleteSupportPost,
    apiAdminSupportPosts,
    apiAdminSupportTickets,
    apiAdminUpdateSupportPost,
} from '../api/support';
import { apiAdminReviewVerification, apiAdminVerifications } from '../api/verification';
import { statusColorVar } from '../utils/colors';
import { ADMIN_ROLES, ROLE_ADMIN, ROLE_CUSTOMER, ROLE_SUPER_ADMIN, ROLE_OPERATOR, ROLE_DRIVER, roleLabel } from '../data/roles';
import EmptyState from '../components/common/EmptyState.vue';
import BaseIcon from '../components/common/BaseIcon.vue';

const message = useMessage();
const dialog = useDialog();
const auth = useAuthStore();
const loading = ref(true);
const actingId = ref(null);
const users = ref([]);
const meta = ref({ total: 0 });
const page = ref(1);
const pageSize = 20;

const tab = ref('metrics');

const load = async () => {
    loading.value = true;

    try {
        const { data } = await apiAdminUsers({ page: page.value });
        users.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        message.error(getApiErrorMessage(e, '사용자 목록을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const changeVerification = async (user, field) => {
    actingId.value = user.id;
    const toggles = {
        vehicle: ['vehicle', 'is_vehicle_verified'],
        license: ['license', 'is_license_verified'],
        business: ['business', 'is_business_verified'],
        account: ['account', 'is_account_verified'],
    };

    const [payloadKey, stateKey] = toggles[field] ?? toggles.vehicle;
    const payload = { [payloadKey]: !user[stateKey] };

    try {
        const { data } = await apiAdminUpdateVerification(user.id, payload);
        user.is_vehicle_verified = data.data.is_vehicle_verified;
        user.is_license_verified = data.data.is_license_verified;
        user.is_business_verified = data.data.is_business_verified;
        user.is_account_verified = data.data.is_account_verified;
        message.success('인증 상태가 업데이트되었습니다.');
    } catch (e) {
        message.error(getApiErrorMessage(e, '인증 상태 변경에 실패했습니다.'));
    } finally {
        actingId.value = null;
    }
};

// ── 사용자 역할 변경 — 기사↔등록자 전환(테스트용)과 관리자 지정 ──
// 최고 관리자는 대상이 될 수 없고, Admin 지정은 루트(id=1)만 가능(서버가 재검증)
const roleChangeOpen = ref(false);
const roleTarget = ref(null);
const roleValue = ref('');
const roleBusy = ref(false);

const roleOptions = computed(() => {
    const options = [
        { value: ROLE_DRIVER, label: roleLabel(ROLE_DRIVER) },
        { value: ROLE_CUSTOMER, label: roleLabel(ROLE_CUSTOMER) },
        { value: ROLE_OPERATOR, label: roleLabel(ROLE_OPERATOR) },
    ];

    if (auth.user?.id === 1) {
        options.push({ value: ROLE_ADMIN, label: roleLabel(ROLE_ADMIN) });
    }

    return options;
});

const openRoleChange = (user) => {
    if (user.role === ROLE_SUPER_ADMIN) {
        message.warning('최고 관리자의 역할은 변경할 수 없습니다.');

        return;
    }

    roleTarget.value = user;
    roleValue.value = user.role;
    roleChangeOpen.value = true;
};

const submitRoleChange = async () => {
    const user = roleTarget.value;

    if (!user) return;

    roleBusy.value = true;

    try {
        const { data } = await apiAdminSetUserRole(user.id, roleValue.value);
        user.role = data.data.role;
        message.success(`역할을 '${roleLabel(data.data.role)}'로 변경했습니다.`);
        roleChangeOpen.value = false;
    } catch (e) {
        message.error(getApiErrorMessage(e, '역할 변경에 실패했습니다.'));
    } finally {
        roleBusy.value = false;
    }
};

// ── 등록자 개별 수수료율 — 업체와 개별 계약한 요율 지정/해제 ──
// 서버는 요율을 0~1 비율로 다루고, 화면은 %로 입력받아 변환한다. 비우면 전역 기본 요율을 따른다.
const feeRateOpen = ref(false);
const feeRateTarget = ref(null);
const feeRateValue = ref(null);
const feeRateBusy = ref(false);

// 지정된 개별 요율을 화면용 % 라벨로 (미지정이면 '기본')
const feeRateLabel = (user) => (user.fee_rate !== null && user.fee_rate !== undefined
    ? `${Number((user.fee_rate * 100).toFixed(2))}%`
    : '기본');

const openFeeRate = (user) => {
    feeRateTarget.value = user;
    feeRateValue.value = user.fee_rate !== null && user.fee_rate !== undefined
        ? Number((user.fee_rate * 100).toFixed(2))
        : null;
    feeRateOpen.value = true;
};

const submitFeeRate = async () => {
    const user = feeRateTarget.value;

    if (!user) return;

    const raw = feeRateValue.value;
    const hasValue = raw !== null && raw !== '';

    if (hasValue && (Number(raw) < 0 || Number(raw) > 100)) {
        message.warning('수수료율은 0~100% 사이로 입력해 주세요.');

        return;
    }

    feeRateBusy.value = true;

    try {
        const rate = hasValue ? Number((Number(raw) / 100).toFixed(4)) : null;
        const { data } = await apiAdminSetUserFeeRate(user.id, rate);
        user.fee_rate = data.data.fee_rate;
        message.success(
            data.data.fee_rate === null
                ? '개별 수수료율을 해제했습니다. 기본 요율이 적용됩니다.'
                : `수수료율을 ${raw}%로 지정했습니다.`,
        );
        feeRateOpen.value = false;
    } catch (e) {
        message.error(getApiErrorMessage(e, '수수료율 지정에 실패했습니다.'));
    } finally {
        feeRateBusy.value = false;
    }
};

// ── 기사 관리 ──
const drivers = ref([]);
const driverMeta = ref({ total: 0 });
const driverPage = ref(1);
const driverStatusBusy = ref(null);

const STATUS_OPTIONS = [
    { value: 'online', label: '온라인' },
    { value: 'offline', label: '오프라인' },
    { value: 'rest', label: '휴식' },
    { value: 'on_trip', label: '운행 중' },
];

const loadDrivers = async () => {
    loading.value = true;

    try {
        const { data } = await apiAdminDrivers({ page: driverPage.value });
        drivers.value = data.data;
        driverMeta.value = data.meta;
    } catch (e) {
        message.error(getApiErrorMessage(e, '기사 목록을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

const changeDriverStatus = async (driver, status) => {
    driverStatusBusy.value = driver.id;

    try {
        const { data } = await apiAdminSetDriverStatus(driver.id, status);
        driver.status = data.data.status;
        driver.status_label = data.data.status_label;
        message.success(`${driver.name} 기사 상태를 '${data.data.status_label}'(으)로 변경했습니다.`);
    } catch (e) {
        message.error(getApiErrorMessage(e, '기사 상태 변경에 실패했습니다.'));
    } finally {
        driverStatusBusy.value = null;
    }
};

const onDriversRefresh = () => {
    loadDrivers();
};

// ── 자동 운행 등록 설정 — 매일 관리자 계정으로 운행을 자동 등록 (중지/시작 가능) ──
const autoSetting = ref({
    is_active: false,
    min_count: 10,
    max_count: 20,
    owner_user_id: null,
    last_run_at: null,
});
const autoLoading = ref(false);
const autoSaving = ref(false);

const loadAutoSetting = async () => {
    autoLoading.value = true;

    try {
        const { data } = await apiAdminAutoOrderSettings();
        autoSetting.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '자동 등록 설정을 불러오지 못했습니다.'));
    } finally {
        autoLoading.value = false;
    }
};

// 등록 계정 후보 — 관리자(Admin/Super Admin) 사용자만
const adminOwnerOptions = computed(() =>
    users.value
        .filter((u) => ADMIN_ROLES.includes(u.role))
        .map((u) => ({ label: `${u.name} (${u.email})`, value: u.id })),
);

const saveAutoSetting = async () => {
    autoSaving.value = true;

    try {
        const payload = {
            is_active: autoSetting.value.is_active,
            min_count: Math.max(1, Number(autoSetting.value.min_count) || 10),
            max_count: Math.max(1, Number(autoSetting.value.max_count) || 20),
            owner_user_id: autoSetting.value.owner_user_id ?? null,
        };

        const { data } = await apiAdminUpdateAutoOrderSettings(payload);
        autoSetting.value = data.data;
        message.success(autoSetting.value.is_active ? '자동 운행 등록이 시작되었습니다.' : '자동 운행 등록이 중지되었습니다.');
    } catch (e) {
        message.error(getApiErrorMessage(e, '자동 등록 설정 저장에 실패했습니다.'));
    } finally {
        autoSaving.value = false;
    }
};

const formatRunAt = (value) => {
    if (!value) {
        return '아직 실행 전';
    }

    return new Date(value).toLocaleString('ko-KR');
};

// ── 자동 등록 운행 이력 — 자동 등록으로 생성된 운행을 확인하고 관리 ──
const autoOrders = ref([]);
const autoOrdersLoading = ref(false);
const autoDeleting = ref(false);

const loadAutoOrders = async () => {
    autoOrdersLoading.value = true;

    try {
        const { data } = await apiAdminAutoOrders();
        autoOrders.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '자동 등록 이력을 불러오지 못했습니다.'));
    } finally {
        autoOrdersLoading.value = false;
    }
};

const deleteAutoOrder = async (id) => {
    autoDeleting.value = true;

    try {
        const { data } = await apiAdminDeleteAutoOrders({ ids: [id] });
        autoOrders.value = autoOrders.value.filter((o) => o.id !== id);
        message.success(`${data.data.deleted}건 삭제되었습니다.`);
    } catch (e) {
        message.error(getApiErrorMessage(e, '운행 삭제에 실패했습니다.'));
    } finally {
        autoDeleting.value = false;
    }
};

const deleteAllAutoOrders = async () => {
    autoDeleting.value = true;

    try {
        const { data } = await apiAdminDeleteAutoOrders({ all: true });
        autoOrders.value = [];
        message.success(`자동 등록 운행 ${data.data.deleted}건이 삭제되었습니다.`);
    } catch (e) {
        message.error(getApiErrorMessage(e, '전체 삭제에 실패했습니다.'));
    } finally {
        autoDeleting.value = false;
    }
};

const autoStatusColor = (order) => statusColorVar[order.status] ?? 'var(--status-draft)';

// 상태 배지 글자색 — 밝은 배경(완료·공개 등)에서는 어두운 글자로 대비 확보 (RideHistoryView·ChatThread와 동일 규칙)
const autoStatusTextColor = (status) =>
    ['completed', 'trading', 'published', 'driving', 'acceptance_pending'].includes(status)
        ? '#101418'
        : '#ffffff';

// ── 출금 처리 — 기사 출금 신청 지급/거절 ──
const payouts = ref([]);
const payoutsLoading = ref(false);
const payoutBusy = ref(null);

const loadPayouts = async () => {
    payoutsLoading.value = true;

    try {
        const { data } = await apiAdminPayouts();
        payouts.value = data.data ?? [];
    } catch (e) {
        message.error(getApiErrorMessage(e, '출금 신청 목록을 불러오지 못했습니다.'));
    } finally {
        payoutsLoading.value = false;
    }
};

const payPayout = (payout) => {
    dialog.warning({
        title: '출금 지급',
        content: `${payout.driver?.name ?? '기사'}님에게 ${Number(payout.amount).toLocaleString('ko-KR')}원을 지급 처리할까요?\n묶인 정산이 '지급 완료'로 확정됩니다.`,
        positiveText: '지급 완료',
        negativeText: '취소',
        onPositiveClick: async () => {
            payoutBusy.value = payout.id;

            try {
                await apiAdminPayoutPay(payout.id);
                message.success('출금 지급 처리가 완료되었습니다.');
                await loadPayouts();
            } catch (e) {
                message.error(getApiErrorMessage(e, '지급 처리에 실패했습니다.'));
            } finally {
                payoutBusy.value = null;
            }
        },
    });
};

const rejectPayout = (payout) => {
    dialog.warning({
        title: '출금 거절',
        content: `${payout.driver?.name ?? '기사'}님의 출금 신청을 거절할까요?\n정산은 다시 출금 가능 상태로 돌아갑니다.`,
        positiveText: '거절',
        negativeText: '취소',
        onPositiveClick: async () => {
            payoutBusy.value = payout.id;

            try {
                await apiAdminPayoutReject(payout.id);
                message.success('출금 신청을 거절했습니다.');
                await loadPayouts();
            } catch (e) {
                message.error(getApiErrorMessage(e, '거절 처리에 실패했습니다.'));
            } finally {
                payoutBusy.value = null;
            }
        },
    });
};

// ── 수금 확인 — 등록자 운행 대금 입금 확인 (수금 확정 → 기사 출금 재원) ──
const collections = ref([]);
const collectionsLoading = ref(false);
const collectionBusy = ref(null);

const loadCollections = async () => {
    collectionsLoading.value = true;

    try {
        const { data } = await apiAdminCollections();
        collections.value = data.data ?? [];
    } catch (e) {
        message.error(getApiErrorMessage(e, '입금 확인 목록을 불러오지 못했습니다.'));
    } finally {
        collectionsLoading.value = false;
    }
};

const collectSettlement = (settlement) => {
    dialog.warning({
        title: '입금 확인',
        content: `${settlement.registrant?.company_name || settlement.registrant?.name || '등록자'}님의 운행 대금 ${Number(settlement.gross_amount).toLocaleString('ko-KR')}원 입금을 확인 처리할까요?\n확인하면 기사가 출금 신청할 수 있습니다.`,
        positiveText: '입금 확인',
        negativeText: '취소',
        onPositiveClick: async () => {
            collectionBusy.value = settlement.id;

            try {
                await apiAdminCollectSettlement(settlement.id);
                message.success('입금 확인 처리가 완료되었습니다.');
                await loadCollections();
            } catch (e) {
                message.error(getApiErrorMessage(e, '입금 확인 처리에 실패했습니다.'));
            } finally {
                collectionBusy.value = null;
            }
        },
    });
};

// ── 신고/분쟁 처리 — '접수 대기(즉시 처리)'를 기본 필터로, 단계 진행은 모달에서 메모와 함께 ──
const reports = ref([]);
const reportsMeta = ref({ total: 0 });
const reportsLoading = ref(false);
const reportStatusFilter = ref('pending');
const reportStatusOptions = ref([]);

// 처리 단계 라벨 — 백엔드 옵션(단일 소스)에서 로드해 필터·진행 선택지에 쓴다
const loadReportStatuses = async () => {
    try {
        const { data } = await apiReportOptions();
        reportStatusOptions.value = Object.entries(data.data.statuses)
            .map(([value, label]) => ({ value, label }));
    } catch {
        reportStatusOptions.value = [];
    }
};

const statusLabelOf = (value) => reportStatusOptions.value.find((option) => option.value === value)?.label ?? value;

const loadReports = async () => {
    reportsLoading.value = true;

    try {
        const { data } = await apiAdminReports({ status: reportStatusFilter.value || undefined });
        reports.value = data.data;
        reportsMeta.value = data.meta;
    } catch (e) {
        message.error(getApiErrorMessage(e, '신고 목록을 불러오지 못했습니다.'));
    } finally {
        reportsLoading.value = false;
    }
};

const formatReportTime = (iso) => (iso ? new Date(iso).toLocaleString('ko-KR') : '');

// 신고 카드 대상 한 줄 — 운행은 노선, 사용자·채팅은 상대 이름
const reportSubjectText = (report) => {
    if (report.target_type === 'order') {
        return report.target?.route || '운행';
    }
    if (report.target_type === 'user') {
        return report.target?.name || '사용자';
    }

    return report.subject ? `${report.subject.name}님과의 채팅` : '채팅';
};

// 단계 진행 모달 — 다음 단계 선택 + 처리 메모(신고자에게 전달될 수 있음)
const advanceOpen = ref(false);
const advanceTarget = ref(null);
const advanceStatus = ref(null);
const advanceNote = ref('');
const advanceBusy = ref(false);

const nextOptions = computed(() => {
    const target = advanceTarget.value;

    if (!target?.next_statuses?.length) {
        return [];
    }

    return target.next_statuses.map((value) => ({ value, label: statusLabelOf(value) }));
});

const openAdvance = (report) => {
    if (!report.next_statuses?.length) {
        message.warning('이미 완료된 신고입니다.');

        return;
    }

    advanceTarget.value = report;
    advanceStatus.value = report.next_statuses[0];
    advanceNote.value = '';
    advanceOpen.value = true;
};

const submitAdvance = async () => {
    if (!advanceTarget.value || !advanceStatus.value) {
        return;
    }
    advanceBusy.value = true;

    try {
        const { data } = await apiAdminAdvanceReport(advanceTarget.value.id, {
            status: advanceStatus.value,
            note: advanceNote.value.trim() || undefined,
        });
        message.success(`신고를 '${data.data.status_label}' 단계로 진행했습니다.`);
        advanceOpen.value = false;
        await loadReports();
    } catch (e) {
        message.error(getApiErrorMessage(e, '처리 단계 진행에 실패했습니다.'));
    } finally {
        advanceBusy.value = false;
    }
};

// ── 감사 로그 — 관리자 개입·변경 행위 기록 (정산·신고·제재·역할·증빙 등) ──
const auditRows = ref([]);
const auditLoading = ref(false);

const loadAudit = async () => {
    auditLoading.value = true;

    try {
        const { data } = await apiAdminOperationAudit();
        auditRows.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '감사 로그를 불러오지 못했습니다.'));
    } finally {
        auditLoading.value = false;
    }
};

const onTabChange = (name) => {
    if (name === 'metrics') {
        loadMetrics();
    } else if (name === 'drivers') {
        loadDrivers();
    } else if (name === 'auto') {
        loadAutoSetting();
        loadAutoOrders();
    } else if (name === 'payouts') {
        loadPayouts();
    } else if (name === 'collections') {
        loadCollections();
    } else if (name === 'reports') {
        loadReportStatuses();
        loadReports();
    } else if (name === 'verifications') {
        loadVerifications();
    } else if (name === 'support') {
        loadSupportPosts('notice');
        loadSupportTickets();
    } else if (name === 'operations') {
        loadOperationMeta();
        loadOperationTab(opTab.value);
    } else if (name === 'audit') {
        loadAudit();
    } else {
        load();
    }
};

// ── 운영 개입(B-2) — 문제 운행·사용자만 관리자가 처리 (일일/운행/채팅/정산) ──
const opTab = ref('daily');
const opMeta = ref({ moderation: [], order_statuses: [] });

// 사용자 제재 상태 라벨 — 백엔드 meta(단일 소스)에서
const loadOperationMeta = async () => {
    try {
        const { data } = await apiAdminOperationMeta();
        opMeta.value = {
            moderation: Object.entries(data.data.moderation).map(([value, label]) => ({ value, label })),
            order_statuses: Object.entries(data.data.order_statuses).map(([value, label]) => ({ value, label })),
        };
    } catch (e) {
        message.error(getApiErrorMessage(e, '운영 옵션을 불러오지 못했습니다.'));
    }
};

const moderationLabelOf = (status) =>
    opMeta.value.moderation.find((option) => option.value === status)?.label ?? status;

const opStatusLabelOf = (status) =>
    opMeta.value.order_statuses.find((option) => option.value === status)?.label ?? status;

// 하위 탭 전환 시 해당 데이터만 로드 (문제 운행 우선 처리 순서)
const loadOperationTab = (name) => {
    if (name === 'daily') {
        loadDaily();
    } else if (name === 'orders') {
        loadOpOrders();
    } else if (name === 'chat') {
        loadConvs();
    } else if (name === 'settle') {
        loadSettles();
    }
};

const onOpTabChange = (name) => {
    opTab.value = name;
    loadOperationTab(name);
};

// ── 사용자 제재 — 기사·등록자 상태(정상/주의/운행 제한/정지) 사유와 함께 변경 ──
// 대상 조건: 최고 관리자·동급 관리자는 대상이 아니다 (백엔드에서도 재검증)
const canSanction = (user) => {
    if (!auth.user) {
        return false;
    }
    if (user.role === ROLE_SUPER_ADMIN || user.id === auth.user.id) {
        return false;
    }
    if (user.role === ROLE_ADMIN && auth.user.role !== ROLE_SUPER_ADMIN) {
        return false;
    }

    return true;
};

const sanctionOpen = ref(false);
const sanctionTarget = ref(null);
const sanctionStatus = ref('active');
const sanctionNote = ref('');
const sanctionBusy = ref(false);

const moderationStatusTone = (status) => {
    if (status === 'suspended') {
        return 'red';
    }
    if (status === 'restricted') {
        return 'amber';
    }
    if (status === 'watch') {
        return 'yellow';
    }

    return 'green';
};

const openSanction = (user) => {
    if (!canSanction(user)) {
        return;
    }
    sanctionTarget.value = user;
    sanctionStatus.value = user.moderation_status || 'active';
    sanctionNote.value = user.moderation_note || '';
    sanctionOpen.value = true;
};

const submitSanction = async () => {
    const user = sanctionTarget.value;

    if (!user) {
        return;
    }
    sanctionBusy.value = true;

    try {
        const { data } = await apiAdminModerateUser(user.id, {
            status: sanctionStatus.value,
            note: sanctionNote.value.trim(),
        });
        user.moderation_status = data.data.moderation_status;
        user.moderation_label = data.data.moderation_label;
        user.moderation_note = data.data.moderation_note;
        message.success(`${user.name}님을 '${data.data.moderation_label}'(으)로 변경했습니다.`);
        sanctionOpen.value = false;
    } catch (e) {
        message.error(getApiErrorMessage(e, '제재 상태 변경에 실패했습니다.'));
    } finally {
        sanctionBusy.value = false;
    }
};

// ── 일일 운영 요약 — 🔴 즉시 처리 > 🟡 확인 필요 > 🟢 정상 ──
const daily = ref(null);
const dailyLoading = ref(false);

// 운영 지표(Q-6) — 정산·매칭·신고·운행 파이프라인 요약 카드 데이터
const metrics = ref(null);
const metricsLoading = ref(false);

const loadMetrics = async () => {
    metricsLoading.value = true;

    try {
        const { data } = await apiAdminOperationMetrics();
        metrics.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '운영 지표를 불러오지 못했습니다.'));
    } finally {
        metricsLoading.value = false;
    }
};

// 지표 카드 단위 — { key, label, tone(민트/옐로우/레드/회색), value, sub }
const formatWon = (v) => `${Number(v ?? 0).toLocaleString('ko-KR')}원`;

// 수수료율 표시 — 5%, 7.5%처럼 정수면 소수점을 붙이지 않는다
const ratePercent = (rate) => {
    const pct = Number(rate ?? 0) * 100;

    return `${Number.isInteger(pct) ? pct : pct.toFixed(1)}%`;
};

const metricCards = computed(() => {
    const m = metrics.value;

    if (!m) {
        return { revenue: [], red: [], yellow: [], green: [], neutral: [] };
    }

    return {
        revenue: [
            { key: 'revenue_month_fee', label: '이번 달 수수료 매출', value: formatWon(m.revenue?.month_fee ?? 0), tone: 'brand', sub: `실효 요율 ${ratePercent(m.revenue?.effective_rate)} · 정산 ${m.revenue?.month_count ?? 0}건` },
            { key: 'revenue_month_gross', label: '이번 달 거래액', value: formatWon(m.revenue?.month_gross ?? 0), tone: 'neutral', sub: `기사 지급 ${formatWon(m.revenue?.month_net ?? 0)}` },
            { key: 'revenue_total_fee', label: '누적 수수료', value: formatWon(m.revenue?.total_fee ?? 0), tone: 'neutral', sub: `기본 요율 ${ratePercent(m.revenue?.fee_rate)}` },
        ],
        red: [
            { key: 'reports_pending', label: '처리 중 신고', value: m.reports?.pending ?? 0, tone: 'red', sub: '접수·조사 단계' },
            { key: 'users_restricted', label: '제한·정지 계정', value: m.users?.restricted ?? 0, tone: 'red', sub: '기사·등록자' },
        ],
        yellow: [
            { key: 'acceptance_pending', label: '승인 대기 운행', value: m.pipeline?.acceptance_pending ?? 0, tone: 'yellow', sub: '등록자 승인 필요' },
            { key: 'matching_pending', label: '신청 대기(30일)', value: m.matching_30d?.pending ?? 0, tone: 'yellow', sub: `승인 ${m.matching_30d?.approved ?? 0} · 거절 ${m.matching_30d?.rejected ?? 0}` },
        ],
        green: [
            { key: 'accepted', label: '예약(확정) 운행', value: m.pipeline?.accepted ?? 0, tone: 'green', sub: '진행 대기' },
            { key: 'driving', label: '운행중', value: m.pipeline?.driving ?? 0, tone: 'green', sub: '실시간 진행' },
        ],
        neutral: [
            { key: 'settle_pending', label: '정산 지급 대기', value: formatWon(m.settlement?.pending_amount ?? 0), tone: 'neutral', sub: '기사 출금 가능 금액' },
            { key: 'settle_paid_month', label: '이번 달 지급', value: formatWon(m.settlement?.paid_month_amount ?? 0), tone: 'neutral', sub: `수수료 ${formatWon(m.settlement?.fee_month_amount ?? 0)}` },
            { key: 'reports_completed', label: '처리 완료 신고', value: m.reports?.completed ?? 0, tone: 'neutral', sub: '누적' },
            { key: 'users_new_today', label: '오늘 신규 가입', value: (m.users?.drivers_today ?? 0) + (m.users?.customers_today ?? 0), tone: 'neutral', sub: `기사 ${m.users?.drivers_today ?? 0} · 등록자 ${m.users?.customers_today ?? 0}` },
        ],
    };
});

// 항목 → 클릭 시 이동할 탭 (상위 탭 전환 or 운영 개입 하위 탭 전환)
const DAILY_ITEMS = [
    { key: 'reports_pending', label: '신고 접수', group: 'red', tab: 'reports' },
    { key: 'orders_hold', label: '운행 보류', group: 'red', opTab: 'orders' },
    { key: 'settlements_hold', label: '정산 보류', group: 'red', opTab: 'settle' },
    { key: 'users_suspended', label: '제한·정지 계정', group: 'red', tab: 'users' },
    { key: 'approval_waiting', label: '승인 대기 운행', group: 'yellow', opTab: 'orders' },
    { key: 'request_cards_pending', label: '확정 대기 요청', group: 'yellow', opTab: 'chat' },
    { key: 'today_completed', label: '오늘 정상 완료', group: 'green' },
];

const loadDaily = async () => {
    dailyLoading.value = true;

    try {
        const { data } = await apiAdminOperationDaily();
        daily.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '운영 현황을 불러오지 못했습니다.'));
    } finally {
        dailyLoading.value = false;
    }
};

const goFromDaily = (item) => {
    if (item.tab) {
        tab.value = item.tab;
    } else if (item.opTab) {
        opTab.value = item.opTab;
    }
};

// ── 운행 개입 — 숨김(마켓 제외)·보류(진행 동결)·강제 취소 ──
const opOrders = ref([]);
const opOrdersLoading = ref(false);
const opOrderQ = ref('');
const opOrderStatus = ref('');

const loadOpOrders = async () => {
    opOrdersLoading.value = true;

    try {
        const { data } = await apiAdminOperationOrders({
            q: opOrderQ.value.trim() || undefined,
            status: opOrderStatus.value || undefined,
        });
        opOrders.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '운행 목록을 불러오지 못했습니다.'));
    } finally {
        opOrdersLoading.value = false;
    }
};

// 운행 개입 확인 모달 — 숨김/보류 토글·강제 취소 (사유 입력)
const opActionOpen = ref(false);
const opActionKind = ref('hide');
const opActionTarget = ref(null);
const opActionReason = ref('');
const opActionBusy = ref(false);
const opActionNote = ref('');

const openOpAction = (kind, order) => {
    opActionKind.value = kind;
    opActionTarget.value = order;
    opActionReason.value = '';
    opActionOpen.value = true;

    if (kind === 'hide') {
        opActionNote.value = order.is_hidden
            ? '숨김을 해제하면 운행이 다시 마켓·추천에 노출됩니다.'
            : '숨긴 운행은 마켓·추천에서 빠지지만, 등록자(내 마켓)와 관리자는 그대로 확인할 수 있습니다.';
    } else if (kind === 'hold') {
        opActionNote.value = order.admin_hold
            ? '보류를 해제하면 상태 진행이 다시 허용됩니다.'
            : '보류된 운행은 기사 신청·요금 제안·상태 변경이 모두 차단됩니다.';
    } else {
        opActionNote.value = '진행 단계(승인 대기~운행중) 운행만 취소할 수 있습니다. 완료·정산 운행은 정산 보류로 처리하세요.';
    }
};

const submitOpAction = async () => {
    const order = opActionTarget.value;

    if (!order) {
        return;
    }
    opActionBusy.value = true;

    try {
        if (opActionKind.value === 'hide') {
            const { data } = await apiAdminOrderHide(order.id, {
                hidden: !order.is_hidden,
                reason: opActionReason.value.trim() || undefined,
            });
            order.is_hidden = data.data.is_hidden;
            message.success(data.data.is_hidden ? '운행을 숨겼습니다.' : '운행 숨김을 해제했습니다.');
        } else if (opActionKind.value === 'hold') {
            const { data } = await apiAdminOrderHold(order.id, {
                hold: !order.admin_hold,
                reason: opActionReason.value.trim() || undefined,
            });
            order.admin_hold = data.data.admin_hold;
            order.admin_hold_reason = data.data.admin_hold_reason;
            message.success(data.data.admin_hold ? '운행을 보류했습니다.' : '운행 보류를 해제했습니다.');
        } else {
            await apiAdminOrderForceCancel(order.id, { reason: opActionReason.value.trim() });
            message.success('운행을 강제 취소했습니다.');
            opOrders.value = opOrders.value.filter((row) => row.id !== order.id);
            loadDaily();
        }
        opActionOpen.value = false;
    } catch (e) {
        message.error(getApiErrorMessage(e, '처리에 실패했습니다.'));
    } finally {
        opActionBusy.value = false;
    }
};

// ── 채팅 운영 — 대화 확인 + 중재 메시지 ──
const convs = ref([]);
const convsLoading = ref(false);
const convQ = ref('');
const convSelected = ref(null);
const convMessages = ref([]);
const convMessagesLoading = ref(false);
const convModerateBody = ref('');
const convModerateBusy = ref(false);

const loadConvs = async () => {
    convsLoading.value = true;

    try {
        const { data } = await apiAdminOperationConversations({ q: convQ.value.trim() || undefined });
        convs.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '대화 목록을 불러오지 못했습니다.'));
    } finally {
        convsLoading.value = false;
    }
};

const selectConv = async (conv) => {
    convSelected.value = conv;
    convMessagesLoading.value = true;
    convMessages.value = [];

    try {
        const { data } = await apiAdminConversationMessages(conv.id);
        convMessages.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '대화 내용을 불러오지 못했습니다.'));
    } finally {
        convMessagesLoading.value = false;
    }
};

const sendModerate = async () => {
    const body = convModerateBody.value.trim();

    if (!convSelected.value || !body) {
        return;
    }
    convModerateBusy.value = true;

    try {
        const { data } = await apiAdminModerateConversation(convSelected.value.id, { body });
        convMessages.value.push(data.data);
        convModerateBody.value = '';
        convSelected.value.last_message_at = '방금';
        message.success('중재 메시지를 대화에 남겼습니다.');
    } catch (e) {
        message.error(getApiErrorMessage(e, '중재 메시지 전송에 실패했습니다.'));
    } finally {
        convModerateBusy.value = false;
    }
};

// ── 정산 보류 — 보류된 정산은 출금 신청 대상에서 제외 ──
const settleRows = ref([]);
const settleLoading = ref(false);

const loadSettles = async () => {
    settleLoading.value = true;

    try {
        const { data } = await apiAdminOperationSettlements();
        settleRows.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '정산 목록을 불러오지 못했습니다.'));
    } finally {
        settleLoading.value = false;
    }
};

const settleActionOpen = ref(false);
const settleActionTarget = ref(null);
const settleActionReason = ref('');
const settleActionBusy = ref(false);

const openSettleAction = (row) => {
    settleActionTarget.value = row;
    settleActionReason.value = '';
    settleActionOpen.value = true;
};

const submitSettleAction = async () => {
    const row = settleActionTarget.value;

    if (!row) {
        return;
    }
    settleActionBusy.value = true;

    try {
        const { data } = await apiAdminSettlementHold(row.id, {
            hold: row.hold_reason ? false : true,
            reason: settleActionReason.value.trim() || undefined,
        });
        row.hold_reason = data.data.hold_reason;
        message.success(row.hold_reason ? '정산을 보류했습니다.' : '정산 보류를 해제했습니다.');
        settleActionOpen.value = false;
        loadDaily();
    } catch (e) {
        message.error(getApiErrorMessage(e, '정산 보류 처리에 실패했습니다.'));
    } finally {
        settleActionBusy.value = false;
    }
};

// 모달 제목·확인 라벨 — 동작(숨김/보류/강제 취소)과 현재 토글 상태에 따라
const opActionTitle = computed(() => {
    const order = opActionTarget.value;
    const kind = opActionKind.value;

    if (kind === 'hide') {
        return order?.is_hidden ? '운행 숨김 해제' : '운행 숨김';
    }
    if (kind === 'hold') {
        return order?.admin_hold ? '운행 보류 해제' : '운행 보류';
    }

    return '운행 강제 취소';
});

const opActionConfirmLabel = computed(() => {
    const order = opActionTarget.value;
    const kind = opActionKind.value;

    if (kind === 'hide') {
        return order?.is_hidden ? '숨김 해제' : '숨기기';
    }
    if (kind === 'hold') {
        return order?.admin_hold ? '보류 해제' : '보류하기';
    }

    return '강제 취소';
});

// 사유 필수 여부 — 상태를 거는 동작(숨김 시작·보류 시작·강제 취소)만 사유가 필요하다
const opActionNeedReason = computed(() => {
    const order = opActionTarget.value;
    const kind = opActionKind.value;

    if (kind === 'cancel') {
        return true;
    }
    if (kind === 'hide') {
        return !order?.is_hidden;
    }

    return !order?.admin_hold;
});

const settleActionTitle = computed(() =>
    settleActionTarget.value?.hold_reason ? '정산 보류 해제' : '정산 보류');

const settleActionNeedReason = computed(() => !settleActionTarget.value?.hold_reason);

// ── 증빙 심사(B-3) — 차량·면허 인증 사진 심사 대기 목록 → 승인/거절 ──
const verifications = ref([]);
const vLoading = ref(false);
const vStatus = ref('pending');
const vQ = ref('');
const VERIFY_STATUS_OPTIONS = [
    { value: '', label: '전체 상태' },
    { value: 'pending', label: '심사 대기' },
    { value: 'approved', label: '승인' },
    { value: 'rejected', label: '거절' },
];

const loadVerifications = async () => {
    vLoading.value = true;

    try {
        const { data } = await apiAdminVerifications({
            status: vStatus.value || undefined,
            q: vQ.value.trim() || undefined,
        });
        verifications.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '심사 목록을 불러오지 못했습니다.'));
    } finally {
        vLoading.value = false;
    }
};

// 심사 처리 모달 — 승인/거절 (거절 시 사유 필수)
const reviewOpen = ref(false);
const reviewTarget = ref(null);
const reviewStatus = ref('approved');
const reviewNote = ref('');
const reviewBusy = ref(false);

const openReview = (request, status) => {
    reviewTarget.value = request;
    reviewStatus.value = status;
    reviewNote.value = '';
    reviewOpen.value = true;
};

const formatVerifyTime = (iso) => (iso ? new Date(iso).toLocaleString('ko-KR') : '');

const submitReview = async () => {
    const target = reviewTarget.value;

    if (!target) {
        return;
    }
    reviewBusy.value = true;

    try {
        const { data } = await apiAdminReviewVerification(target.id, {
            status: reviewStatus.value,
            note: reviewNote.value.trim() || undefined,
        });
        message.success(`${target.user?.name ?? '사용자'}님의 ${target.type_label} 인증을 '${data.data.status_label}'(으)로 처리했습니다.`);
        reviewOpen.value = false;
        await loadVerifications();
    } catch (e) {
        message.error(getApiErrorMessage(e, '심사 처리에 실패했습니다.'));
    } finally {
        reviewBusy.value = false;
    }
};

// ── 고객지원(B-4) — 공지·FAQ 작성/수정/삭제 + 1:1 문의 답변 ──
const supportTab = ref('posts-notice');
const supportPosts = ref([]);
const supportPostsKind = ref('notice');
const supportPostsLoading = ref(false);
const supportKindLabel = ref('공지');

const loadSupportPosts = async (kind) => {
    supportPostsKind.value = kind;
    supportKindLabel.value = kind === 'notice' ? '공지' : 'FAQ';
    supportPostsLoading.value = true;

    try {
        const { data } = await apiAdminSupportPosts({ kind });
        supportPosts.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '게시물 목록을 불러오지 못했습니다.'));
    } finally {
        supportPostsLoading.value = false;
    }
};

const onSupportTabChange = (name) => {
    supportTab.value = name;

    if (name === 'posts-notice') {
        loadSupportPosts('notice');
    } else if (name === 'posts-faq') {
        loadSupportPosts('faq');
    } else if (name === 'tickets') {
        loadSupportTickets();
    }
};

// 공지/FAQ 작성·수정 모달
const postEditorOpen = ref(false);
const postEditorTarget = ref(null);
const postEditorKind = ref('notice');
const postEditorTitle = ref('');
const postEditorBody = ref('');
const postEditorBusy = ref(false);

const openPostEditor = (kind, post = null) => {
    postEditorKind.value = kind;
    postEditorTarget.value = post;
    postEditorTitle.value = post?.title ?? '';
    postEditorBody.value = post?.body ?? '';
    postEditorOpen.value = true;
};

const submitPostEditor = async () => {
    if (!postEditorTitle.value.trim() || !postEditorBody.value.trim()) {
        message.warning('제목과 내용을 입력해 주세요.');

        return;
    }
    postEditorBusy.value = true;

    try {
        if (postEditorTarget.value) {
            await apiAdminUpdateSupportPost(postEditorTarget.value.id, {
                title: postEditorTitle.value.trim(),
                body: postEditorBody.value.trim(),
            });
            message.success('게시물을 수정했습니다.');
        } else {
            await apiAdminCreateSupportPost({
                kind: postEditorKind.value,
                title: postEditorTitle.value.trim(),
                body: postEditorBody.value.trim(),
            });
            message.success('게시물을 등록했습니다.');
        }
        postEditorOpen.value = false;
        await loadSupportPosts(supportPostsKind.value);
    } catch (e) {
        message.error(getApiErrorMessage(e, '저장에 실패했습니다.'));
    } finally {
        postEditorBusy.value = false;
    }
};

const deleteSupportPost = (post) => {
    dialog.warning({
        title: '게시물 삭제',
        content: `'${post.title}' 게시물을 삭제할까요?\n삭제하면 사용자 화면에서도 사라집니다.`,
        positiveText: '삭제',
        negativeText: '취소',
        onPositiveClick: async () => {
            try {
                await apiAdminDeleteSupportPost(post.id);
                message.success('게시물을 삭제했습니다.');
                await loadSupportPosts(supportPostsKind.value);
            } catch (e) {
                message.error(getApiErrorMessage(e, '삭제에 실패했습니다.'));
            }
        },
    });
};

// 1:1 문의 목록·답변
const supportTickets = ref([]);
const supportTicketsLoading = ref(false);
const supportTicketStatus = ref('open');
const SUPPORT_TICKET_STATUS_OPTIONS = [
    { value: '', label: '전체 상태' },
    { value: 'open', label: '답변 대기' },
    { value: 'answered', label: '답변 완료' },
];

const loadSupportTickets = async () => {
    supportTicketsLoading.value = true;

    try {
        const { data } = await apiAdminSupportTickets({
            status: supportTicketStatus.value || undefined,
        });
        supportTickets.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '문의 목록을 불러오지 못했습니다.'));
    } finally {
        supportTicketsLoading.value = false;
    }
};

const answerOpen = ref(false);
const answerTarget = ref(null);
const answerBody = ref('');
const answerBusy = ref(false);

const postEditorTitleText = computed(() => {
    const kindLabel = postEditorKind.value === 'notice' ? '공지' : 'FAQ';

    return postEditorTarget.value ? `${kindLabel} 수정` : `새 ${kindLabel} 작성`;
});

const openAnswer = (ticket) => {
    answerTarget.value = ticket;
    answerBody.value = '';
    answerOpen.value = true;
};

const submitAnswer = async () => {
    const ticket = answerTarget.value;

    if (!ticket || !answerBody.value.trim()) {
        return;
    }
    answerBusy.value = true;

    try {
        const { data } = await apiAdminAnswerSupportTicket(ticket.id, { answer: answerBody.value.trim() });
        message.success(`문의를 '${data.data.status_label}'(으)로 처리했습니다.`);
        answerOpen.value = false;
        await loadSupportTickets();
    } catch (e) {
        message.error(getApiErrorMessage(e, '답변 처리에 실패했습니다.'));
    } finally {
        answerBusy.value = false;
    }
};

onMounted(() => {
    load();
    loadMetrics();
    window.addEventListener('app:drivers-refresh', onDriversRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener('app:drivers-refresh', onDriversRefresh);
});
</script>

<template>
    <div class="page-shell">
        <div class="page-head">
            <div>
                <p class="page-head__eyebrow">관리자</p>
                <h1 class="page-head__title">운영 관리</h1>
                <p class="page-head__desc">사용자 인증·제재, 운행 개입(숨김·보류·취소), 신고·출금·정산까지 운영 업무를 처리합니다.</p>
            </div>
        </div>

        <n-tabs v-model:value="tab" type="line" animated @update:value="onTabChange">
            <n-tab-pane name="metrics" tab="운영 지표">
                <div v-if="metricsLoading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                </div>

                <div v-else-if="metrics" class="metrics-grid">
                    <section class="metrics-group">
                        <h3 class="metrics-group__title">
                            <span class="metrics-dot metrics-dot--brand" /> 수수료 매출
                        </h3>
                        <div class="metrics-cards">
                            <div v-for="card in metricCards.revenue" :key="card.key" class="metric-card">
                                <span class="metric-card__label">{{ card.label }}</span>
                                <strong class="metric-card__value" :class="`metric-card__value--${card.tone}`">{{ card.value }}</strong>
                                <span class="metric-card__sub">{{ card.sub }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="metrics-group">
                        <h3 class="metrics-group__title">
                            <span class="metrics-dot metrics-dot--red" /> 즉시 처리
                        </h3>
                        <div class="metrics-cards">
                            <div v-for="card in metricCards.red" :key="card.key" class="metric-card">
                                <span class="metric-card__label">{{ card.label }}</span>
                                <strong class="metric-card__value" :class="`metric-card__value--${card.tone}`">{{ card.value }}</strong>
                                <span class="metric-card__sub">{{ card.sub }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="metrics-group">
                        <h3 class="metrics-group__title">
                            <span class="metrics-dot metrics-dot--yellow" /> 확인 필요
                        </h3>
                        <div class="metrics-cards">
                            <div v-for="card in metricCards.yellow" :key="card.key" class="metric-card">
                                <span class="metric-card__label">{{ card.label }}</span>
                                <strong class="metric-card__value" :class="`metric-card__value--${card.tone}`">{{ card.value }}</strong>
                                <span class="metric-card__sub">{{ card.sub }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="metrics-group">
                        <h3 class="metrics-group__title">
                            <span class="metrics-dot metrics-dot--green" /> 진행 중
                        </h3>
                        <div class="metrics-cards">
                            <div v-for="card in metricCards.green" :key="card.key" class="metric-card">
                                <span class="metric-card__label">{{ card.label }}</span>
                                <strong class="metric-card__value" :class="`metric-card__value--${card.tone}`">{{ card.value }}</strong>
                                <span class="metric-card__sub">{{ card.sub }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="metrics-group">
                        <h3 class="metrics-group__title">
                            <span class="metrics-dot metrics-dot--neutral" /> 정산·가입 현황
                        </h3>
                        <div class="metrics-cards">
                            <div v-for="card in metricCards.neutral" :key="card.key" class="metric-card">
                                <span class="metric-card__label">{{ card.label }}</span>
                                <strong class="metric-card__value" :class="`metric-card__value--${card.tone}`">{{ card.value }}</strong>
                                <span class="metric-card__sub">{{ card.sub }}</span>
                            </div>
                        </div>
                    </section>

                    <p class="admin-page-hint">
                        운영 지표는 '정상 운행 자동 처리, 문제 운행만 관리자 개입' 원칙의 요약입니다.
                        상세 처리는 사용자·신고·출금·운영 개입 탭에서 진행하세요. 매칭(신청)은 최근 30일 집계입니다.
                    </p>
                </div>
            </n-tab-pane>

            <n-tab-pane name="users" tab="사용자 관리">
                <div v-if="loading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row">
                        <div class="admin-skeleton__line">
                            <div class="sk-line sk-line--md" style="width: 45%" />
                            <div class="sk-line" style="width: 65%; margin-top: 8px" />
                        </div>
                        <div class="admin-skeleton__chips">
                            <div class="sk-chip" />
                            <div class="sk-chip" />
                        </div>
                    </div>
                </div>

                <template v-else>
                    <div class="admin-list">
                        <div v-for="user in users" :key="user.id" class="admin-user">
                            <div class="admin-user__main">
                                <div class="admin-user__name">
                                    <strong>{{ user.name }}</strong>
                                    <n-tag size="small" round :type="user.role === ROLE_SUPER_ADMIN ? 'error' : user.role === ROLE_ADMIN ? 'warning' : 'default'">
                                        {{ roleLabel(user.role) }}
                                    </n-tag>
                                    <span
                                        v-if="user.moderation_status && user.moderation_status !== 'active'"
                                        class="op-badge"
                                        :class="`op-badge--${moderationStatusTone(user.moderation_status)}`"
                                        :title="user.moderation_note"
                                    >
                                        {{ user.moderation_label }}
                                    </span>
                                </div>
                                <p class="admin-user__meta">
                                    {{ user.email }} · 가입 {{ user.created_at }} · 완료 {{ user.completed_count }}건
                                    <template v-if="user.moderation_status && user.moderation_status !== 'active' && user.moderation_note">
                                        · {{ user.moderation_note }}
                                    </template>
                                </p>
                            </div>
                            <div class="admin-user__verify">
                                <!-- 등록자(업체) — 사업자등록증·대표 계좌 인증 (Q-4) -->
                                <template v-if="user.role === ROLE_CUSTOMER">
                                    <div class="admin-user__verify-row">
                                        <span class="admin-user__verify-label">사업자</span>
                                        <n-button
                                            size="small"
                                            :type="user.is_business_verified ? 'success' : 'default'"
                                            round
                                            :loading="actingId === user.id"
                                            @click="changeVerification(user, 'business')"
                                        >
                                            <template v-if="user.is_business_verified"><BaseIcon name="check" :size="12" /> 인증됨</template>
                                            <template v-else>승인</template>
                                        </n-button>
                                    </div>
                                    <div class="admin-user__verify-row">
                                        <span class="admin-user__verify-label">계좌</span>
                                        <n-button
                                            size="small"
                                            :type="user.is_account_verified ? 'success' : 'default'"
                                            round
                                            :loading="actingId === user.id"
                                            @click="changeVerification(user, 'account')"
                                        >
                                            <template v-if="user.is_account_verified"><BaseIcon name="check" :size="12" /> 인증됨</template>
                                            <template v-else>승인</template>
                                        </n-button>
                                    </div>
                                </template>
                                <!-- 그 외(기사 등) — 차량·면허 인증 -->
                                <template v-else>
                                    <div class="admin-user__verify-row">
                                        <span class="admin-user__verify-label">차량</span>
                                        <n-button
                                            size="small"
                                            :type="user.is_vehicle_verified ? 'success' : 'default'"
                                            round
                                            :loading="actingId === user.id"
                                            @click="changeVerification(user, 'vehicle')"
                                        >
                                            <template v-if="user.is_vehicle_verified"><BaseIcon name="check" :size="12" /> 인증됨</template>
                                            <template v-else>승인</template>
                                        </n-button>
                                    </div>
                                    <div class="admin-user__verify-row">
                                        <span class="admin-user__verify-label">면허</span>
                                        <n-button
                                            size="small"
                                            :type="user.is_license_verified ? 'success' : 'default'"
                                            round
                                            :loading="actingId === user.id"
                                            @click="changeVerification(user, 'license')"
                                        >
                                            <template v-if="user.is_license_verified"><BaseIcon name="check" :size="12" /> 인증됨</template>
                                            <template v-else>승인</template>
                                        </n-button>
                                    </div>
                                </template>
                                <div v-if="canSanction(user)" class="admin-user__verify-row">
                                    <button type="button" class="op-btn op-btn--ghost" @click="openSanction(user)">
                                        {{ user.moderation_status && user.moderation_status !== 'active' ? '제재 변경' : '제재' }}
                                    </button>
                                </div>
                                <!-- 등록자(업체) — 개별 계약 수수료율 지정 (기본 요율 대신 적용) -->
                                <div v-if="user.role === ROLE_CUSTOMER" class="admin-user__verify-row">
                                    <button type="button" class="op-btn op-btn--ghost" @click="openFeeRate(user)">
                                        수수료율 {{ feeRateLabel(user) }}
                                    </button>
                                </div>
                                <div v-if="user.role !== ROLE_SUPER_ADMIN" class="admin-user__verify-row">
                                    <button type="button" class="op-btn op-btn--ghost" @click="openRoleChange(user)">
                                        역할 변경
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="meta.last_page > 1" class="admin-pager">
                        <n-button size="small" :disabled="page <= 1" @click="page--; load()">이전</n-button>
                        <span class="admin-pager__info">{{ page }} / {{ meta.last_page }} (총 {{ meta.total }}명)</span>
                        <n-button size="small" :disabled="page >= meta.last_page" @click="page++; load()">다음</n-button>
                    </div>

                    <EmptyState v-if="users.length === 0" title="사용자가 없습니다" hint="계정이 가입하면 이 목록에 표시됩니다" />
                </template>
            </n-tab-pane>

            <n-tab-pane name="drivers" tab="기사 관리">
                <div v-if="loading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row">
                        <div class="admin-skeleton__line">
                            <div class="sk-line sk-line--md" style="width: 45%" />
                            <div class="sk-line" style="width: 65%; margin-top: 8px" />
                        </div>
                        <div class="admin-skeleton__chips">
                            <div class="sk-chip" />
                            <div class="sk-chip" />
                        </div>
                    </div>
                </div>

                <template v-else>
                    <div class="admin-list">
                        <div v-for="driver in drivers" :key="driver.id" class="admin-user">
                            <div class="admin-user__main">
                                <div class="admin-user__name">
                                    <strong>{{ driver.name }}</strong>
                                    <n-tag size="small" round :type="driver.status === 'on_trip' ? 'success' : driver.status === 'rest' ? 'warning' : 'default'">
                                        {{ driver.status_label }}
                                    </n-tag>
                                </div>
                                <p class="admin-user__meta">
                                    {{ driver.phone || driver.email }} · 차량 {{ driver.vehicle_count }}대
                                    <template v-if="driver.today_completed"> · 오늘 {{ driver.today_completed }}건 · {{ driver.today_income.toLocaleString() }}원</template>
                                    <template v-else> · 오늘 운행 없음</template>
                                </p>
                                <div class="admin-user__verify">
                                    <div class="admin-user__verify-row">
                                        <span class="admin-user__verify-label">차량</span>
                                        <span class="admin-user__verify-label" :class="{ 'admin-user__verify-label--ok': driver.is_vehicle_verified }">
                                            <template v-if="driver.is_vehicle_verified"><BaseIcon name="check" :size="12" /> 인증</template>
                                            <template v-else>미인증</template>
                                        </span>
                                        <span class="admin-user__verify-label">면허</span>
                                        <span class="admin-user__verify-label" :class="{ 'admin-user__verify-label--ok': driver.is_license_verified }">
                                            <template v-if="driver.is_license_verified"><BaseIcon name="check" :size="12" /> 인증</template>
                                            <template v-else>미인증</template>
                                        </span>
                                    </div>
                                    <div class="admin-user__verify-row">
                                        <span class="admin-user__verify-label">상태 전환</span>
                                        <n-select
                                            :value="driver.status"
                                            :options="STATUS_OPTIONS"
                                            size="small"
                                            style="width: 110px"
                                            :disabled="driverStatusBusy === driver.id"
                                            @update:value="(value) => changeDriverStatus(driver, value)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="driverMeta.last_page > 1" class="admin-pager">
                        <n-button size="small" :disabled="driverPage <= 1" @click="driverPage--; loadDrivers()">이전</n-button>
                        <span class="admin-pager__info">{{ driverPage }} / {{ driverMeta.last_page }} (총 {{ driverMeta.total }}명)</span>
                        <n-button size="small" :disabled="driverPage >= driverMeta.last_page" @click="driverPage++; loadDrivers()">다음</n-button>
                    </div>

                    <EmptyState v-if="drivers.length === 0" title="기사가 없습니다" hint="기사 계정이 가입하면 이 목록에 표시됩니다" />
                </template>
            </n-tab-pane>

            <n-tab-pane name="auto" tab="자동 운행">
                <div v-if="autoLoading" class="admin-skeleton">
                    <div v-for="n in 2" :key="n" class="sk-card admin-skeleton__row">
                        <div class="sk-line sk-line--md" style="width: 40%" />
                        <div class="sk-line" style="width: 70%; margin-top: 10px" />
                    </div>
                </div>

                <div v-else class="auto-card">
                    <div class="auto-card__head">
                        <div>
                            <strong>매일 운행 자동 등록</strong>
                            <p class="auto-card__desc">매일 오전 9시에 관리자 계정으로 운행을 자동 등록합니다. 설정으로 언제든 중지/시작할 수 있습니다.</p>
                        </div>
                        <n-switch
                            v-model:value="autoSetting.is_active"
                            size="large"
                            :disabled="autoSaving"
                            :rail-style="({ checked }) => (checked ? { backgroundColor: 'var(--brand)' } : {})"
                        >
                            <template #checked>시작</template>
                            <template #unchecked>중지</template>
                        </n-switch>
                    </div>

                    <div class="auto-card__row">
                        <span class="auto-card__label">등록 계정</span>
                        <n-select
                            v-model:value="autoSetting.owner_user_id"
                            :options="adminOwnerOptions"
                            placeholder="기본 관리자 (가장 먼저 가입한 관리자)"
                            clearable
                            style="flex: 1"
                        />
                    </div>

                    <div class="auto-card__row">
                        <span class="auto-card__label">하루 등록 건수</span>
                        <div class="auto-card__count">
                            <n-input-number
                                v-model:value="autoSetting.min_count"
                                :min="1"
                                :max="50"
                                size="small"
                                style="width: 90px"
                            />
                            <span class="auto-card__tilde">~</span>
                            <n-input-number
                                v-model:value="autoSetting.max_count"
                                :min="1"
                                :max="50"
                                size="small"
                                style="width: 90px"
                            />
                            <span class="auto-card__unit">건</span>
                        </div>
                    </div>

                    <div class="auto-card__row">
                        <span class="auto-card__label">마지막 실행</span>
                        <span class="auto-card__value">{{ formatRunAt(autoSetting.last_run_at) }}</span>
                    </div>

                    <div class="auto-card__footer">
                        <n-button
                            type="primary"
                            round
                            :loading="autoSaving"
                            @click="saveAutoSetting"
                        >
                            설정 저장
                        </n-button>
                    </div>
                </div>

                <!-- 자동 등록 운행 이력 — 생성된 운행을 확인하고 관리 -->
                <div class="auto-history">
                    <div class="auto-history__head">
                        <div>
                            <strong>최근 자동 등록 이력</strong>
                            <p class="auto-history__desc">자동 등록으로 생성된 운행(최근 50건)을 확인하고 삭제할 수 있습니다.</p>
                        </div>
                        <div class="auto-history__actions">
                            <n-button size="small" quaternary round title="새로고침" @click="loadAutoOrders">
                                <BaseIcon name="refresh" :size="14" />
                            </n-button>
                            <n-button
                                size="small"
                                type="error"
                                round
                                :loading="autoDeleting"
                                :disabled="!autoOrders.length"
                                @click="deleteAllAutoOrders"
                            >
                                전체 삭제
                            </n-button>
                        </div>
                    </div>

                    <div v-if="autoOrdersLoading" class="auto-history__skeleton">
                        <div v-for="n in 3" :key="n" class="sk-card auto-history__row auto-history__row--skel">
                            <div class="sk-line sk-line--md" style="width: 40%" />
                            <div class="sk-line" style="width: 65%; margin-top: 8px" />
                        </div>
                    </div>

                    <p v-else-if="!autoOrders.length" class="auto-history__empty">
                        아직 자동 등록된 운행이 없습니다. 다음 실행(매일 오전 9시) 후 이곳에 표시됩니다.
                    </p>

                    <div v-else class="auto-history__list">
                        <div v-for="order in autoOrders" :key="order.id" class="auto-history__row">
                            <span
                                class="auto-history__status"
                                :style="{ background: autoStatusColor(order), borderColor: autoStatusColor(order), color: autoStatusTextColor(order.status) }"
                            >
                                {{ order.status_label }}
                            </span>
                            <div class="auto-history__main">
                                <strong class="auto-history__route">{{ order.route }}</strong>
                                <span class="auto-history__meta">
                                    {{ order.service_date }} {{ order.service_time }} · {{ order.customer_name }} · {{ order.created_at }}
                                </span>
                            </div>
                            <span class="auto-history__amount">{{ (order.expected_revenue ?? 0).toLocaleString() }}원</span>
                            <button
                                type="button"
                                class="auto-history__del"
                                aria-label="삭제"
                                title="삭제"
                                :disabled="autoDeleting"
                                @click="deleteAutoOrder(order.id)"
                            >
                                <BaseIcon name="trash" :size="15" />
                            </button>
                        </div>
                    </div>
                </div>
            </n-tab-pane>

            <!-- 수금 확인 — 등록자 운행 대금 입금 확인 (수금 확정 → 기사 출금 재원) -->
            <n-tab-pane name="collections" tab="수금 확인">
                <p class="admin-page-hint">등록자가 입금한 운행 대금을 확인합니다. 확인하면 해당 정산이 기사 출금 재원이 됩니다.</p>

                <div v-if="collectionsLoading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                </div>

                <EmptyState
                    v-else-if="collections.length === 0"
                    icon="cash"
                    title="입금 확인 대기가 없습니다"
                    hint="정산 후 등록자가 입금할 금액이 생기면 이 목록에 표시됩니다"
                />

                <div v-else class="payout-list">
                    <div v-for="settlement in collections" :key="settlement.id" class="payout-card">
                        <div class="payout-card__head">
                            <strong class="payout-card__driver">{{ settlement.registrant?.company_name || settlement.registrant?.name || '등록자' }}</strong>
                            <span class="payout-card__amount">{{ Number(settlement.gross_amount).toLocaleString('ko-KR') }}원</span>
                        </div>
                        <p class="payout-card__account">{{ settlement.route || '운행' }} · {{ settlement.service_date }} {{ settlement.service_time || '' }}</p>
                        <p class="payout-card__meta">
                            수수료 {{ Number(settlement.fee_amount).toLocaleString('ko-KR') }}원
                            · 기사 지급 예정 {{ Number(settlement.net_amount).toLocaleString('ko-KR') }}원
                            · 정산 {{ new Date(settlement.created_at_iso).toLocaleString('ko-KR') }}
                        </p>
                        <div class="payout-card__actions">
                            <button
                                type="button"
                                class="admin-payout-btn admin-payout-btn--ok"
                                :disabled="collectionBusy === settlement.id"
                                @click="collectSettlement(settlement)"
                            >
                                입금 확인
                            </button>
                        </div>
                    </div>
                </div>
            </n-tab-pane>

            <!-- 출금 처리 — 기사 출금 신청 지급/거절 -->
            <n-tab-pane name="payouts" tab="출금 처리">
                <p class="admin-page-hint">기사가 신청한 출금을 확인하고 지급합니다. 지급하면 정산 원장이 '지급 완료'로 확정됩니다.</p>

                <div v-if="payoutsLoading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                </div>

                <EmptyState
                    v-else-if="payouts.length === 0"
                    icon="coin"
                    title="출금 신청이 없습니다"
                    hint="기사가 출금을 신청하면 이 목록에 표시됩니다"
                />

                <div v-else class="payout-list">
                    <div v-for="payout in payouts" :key="payout.id" class="payout-card">
                        <div class="payout-card__head">
                            <strong class="payout-card__driver">{{ payout.driver?.name ?? '기사' }}</strong>
                            <span class="payout-card__amount">{{ Number(payout.amount).toLocaleString('ko-KR') }}원</span>
                        </div>
                        <p class="payout-card__account">
                            {{ payout.bank_name }} {{ payout.account_number }} · {{ payout.account_holder }}
                        </p>
                        <p class="payout-card__meta">신청 {{ new Date(payout.created_at_iso).toLocaleString('ko-KR') }}</p>
                        <div class="payout-card__actions">
                            <button
                                type="button"
                                class="admin-payout-btn admin-payout-btn--no"
                                :disabled="payoutBusy"
                                @click="rejectPayout(payout)"
                            >
                                거절
                            </button>
                            <button
                                type="button"
                                class="admin-payout-btn admin-payout-btn--ok"
                                :disabled="payoutBusy"
                                @click="payPayout(payout)"
                            >
                                지급 완료
                            </button>
                        </div>
                    </div>
                </div>
            </n-tab-pane>

            <!-- 신고/분쟁 처리 — 문제가 된 운행·사용자·채팅만 관리자가 개입 (접수→확인→조사→처리→완료) -->
            <n-tab-pane name="reports" tab="신고 처리">
                <div class="admin-filter-row">
                    <n-select
                        :value="reportStatusFilter"
                        size="small"
                        style="width: 150px"
                        :options="[{ value: '', label: '전체 상태' }, ...reportStatusOptions]"
                        @update:value="(value) => { reportStatusFilter = value ?? ''; loadReports(); }"
                    />
                    <span class="admin-filter-info">총 {{ reportsMeta.total }}건</span>
                </div>

                <p class="admin-page-hint">
                    신고 접수(즉시 처리)부터 차례로 진행합니다. '처리·완료' 단계로 넘기면 남긴 메모가 신고자에게
                    알림으로 전달됩니다.
                </p>

                <div v-if="reportsLoading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                </div>

                <EmptyState
                    v-else-if="reports.length === 0"
                    icon="inbox"
                    title="해당 상태의 신고가 없습니다"
                    hint="신고가 접수되면 이 목록에 표시됩니다"
                />

                <div v-else class="report-list">
                    <article v-for="report in reports" :key="report.id" class="report-card">
                        <div class="report-card__head">
                            <span class="report-card__cat">{{ report.category_label }}</span>
                            <span class="report-card__status" :class="`report-card__status--${report.status}`">
                                {{ report.status_label }}
                            </span>
                        </div>
                        <p class="report-card__subject">{{ report.target_label }} · {{ reportSubjectText(report) }}</p>
                        <p class="report-card__reason">{{ report.reason }}</p>
                        <p class="report-card__meta">
                            신고자 {{ report.reporter?.name ?? '탈퇴 사용자' }} · {{ formatReportTime(report.created_at_iso) }}
                        </p>
                        <p v-if="report.note" class="report-card__note">
                            <strong>처리 메모</strong> {{ report.note }}
                        </p>
                        <p v-if="report.processed_by_name" class="report-card__meta">
                            마지막 처리 {{ report.processed_by_name }} · {{ formatReportTime(report.processed_at_iso) }}
                        </p>
                        <div class="report-card__actions">
                            <button
                                type="button"
                                class="admin-payout-btn admin-payout-btn--ok"
                                @click="openAdvance(report)"
                            >
                                단계 진행
                            </button>
                        </div>
                    </article>
                </div>
            </n-tab-pane>

            <n-tab-pane name="verifications" tab="증빙 심사">
                <div class="admin-filter-row">
                    <n-select
                        :value="vStatus"
                        size="small"
                        style="width: 140px"
                        :options="VERIFY_STATUS_OPTIONS"
                        @update:value="(value) => { vStatus = value ?? ''; loadVerifications(); }"
                    />
                    <n-input
                        :value="vQ"
                        size="small"
                        style="width: 200px"
                        placeholder="이름 검색"
                        clearable
                        @update:value="(value) => { vQ = value ?? ''; }"
                        @keyup.enter="loadVerifications"
                    />
                    <button type="button" class="op-btn op-btn--ghost" @click="loadVerifications">
                        <BaseIcon name="refresh" :size="14" /> 조회
                    </button>
                </div>

                <p class="admin-page-hint">
                    기사·등록자가 올린 차량/면허 증빙 사진을 확인하고 승인하거나 거절합니다. 처리 결과는 신청자에게
                    알림으로 전달되고 승인 시 인증 배지가 부여됩니다.
                </p>

                <div v-if="vLoading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                </div>

                <EmptyState
                    v-else-if="verifications.length === 0"
                    icon="inbox"
                    title="심사할 요청이 없습니다"
                    hint="증빙이 접수되면 이 목록에 표시됩니다"
                />

                <div v-else class="v-list">
                    <article v-for="request in verifications" :key="request.id" class="v-card">
                        <div class="v-card__head">
                            <div class="v-card__who">
                                <strong>{{ request.user?.name ?? '탈퇴 사용자' }}</strong>
                                <span>{{ request.user?.email }}</span>
                            </div>
                            <div class="v-card__chips">
                                <span class="v-chip v-chip--type">{{ request.type_label }}</span>
                                <span class="v-chip" :class="`v-chip--${request.status}`">{{ request.status_label }}</span>
                            </div>
                        </div>

                        <div class="v-card__body">
                            <img :src="request.image_url" class="v-card__proof" alt="증빙 사진" loading="lazy" />
                            <div class="v-card__info">
                                <p class="v-card__meta">신청 {{ formatVerifyTime(request.created_at_iso) }}</p>
                                <p v-if="request.note" class="v-card__note">참고: {{ request.note }}</p>
                                <p v-if="request.status === 'rejected' && request.review_note" class="v-card__reason">
                                    <strong>거절 사유</strong> {{ request.review_note }}
                                </p>
                                <p v-if="request.status !== 'pending'" class="v-card__meta">
                                    처리 {{ request.reviewer_name ?? '' }} · {{ formatVerifyTime(request.reviewed_at_iso) }}
                                </p>
                            </div>
                        </div>

                        <div v-if="request.status === 'pending'" class="v-card__actions">
                            <button type="button" class="op-btn op-btn--ok" @click="openReview(request, 'approved')">승인</button>
                            <button type="button" class="op-btn op-btn--danger" @click="openReview(request, 'rejected')">거절</button>
                        </div>
                    </article>
                </div>
            </n-tab-pane>

            <n-tab-pane name="support" tab="고객지원">
                <n-tabs v-model:value="supportTab" type="segment" animated size="small" @update:value="onSupportTabChange">
                    <!-- 공지 관리 -->
                    <n-tab-pane name="posts-notice" tab="공지 관리">
                        <div class="admin-filter-row">
                            <button type="button" class="op-btn op-btn--ok" @click="openPostEditor('notice')">
                                <BaseIcon name="add" :size="14" /> 새 공지 작성
                            </button>
                            <span class="admin-filter-info">사용자 고객지원 > 공지 탭에 표시됩니다.</span>
                        </div>

                        <div v-if="supportPostsLoading" class="admin-skeleton">
                            <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                        </div>

                        <EmptyState
                            v-else-if="supportPosts.length === 0"
                            icon="inbox"
                            title="등록된 공지가 없습니다"
                            hint="'새 공지 작성'으로 안내 문구를 올려 보세요"
                        />

                        <div v-else class="support-admin-list">
                            <article v-for="post in supportPosts" :key="post.id" class="support-admin-card">
                                <div class="support-admin-card__main">
                                    <p class="support-admin-card__title">{{ post.title }}</p>
                                    <p class="support-admin-card__body">{{ post.body }}</p>
                                    <p class="support-admin-card__meta">{{ post.created_at }}</p>
                                </div>
                                <div class="support-admin-card__actions">
                                    <button type="button" class="op-btn op-btn--ghost" @click="openPostEditor('notice', post)">수정</button>
                                    <button type="button" class="op-btn op-btn--danger" @click="deleteSupportPost(post)">삭제</button>
                                </div>
                            </article>
                        </div>
                    </n-tab-pane>

                    <!-- FAQ 관리 -->
                    <n-tab-pane name="posts-faq" tab="FAQ 관리">
                        <div class="admin-filter-row">
                            <button type="button" class="op-btn op-btn--ok" @click="openPostEditor('faq')">
                                <BaseIcon name="add" :size="14" /> 새 FAQ 작성
                            </button>
                            <span class="admin-filter-info">사용자 고객지원 > FAQ 탭에 표시됩니다.</span>
                        </div>

                        <div v-if="supportPostsLoading" class="admin-skeleton">
                            <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                        </div>

                        <EmptyState
                            v-else-if="supportPosts.length === 0"
                            icon="inbox"
                            title="등록된 FAQ가 없습니다"
                            hint="'새 FAQ 작성'으로 자주 묻는 질문을 정리해 보세요"
                        />

                        <div v-else class="support-admin-list">
                            <article v-for="post in supportPosts" :key="post.id" class="support-admin-card">
                                <div class="support-admin-card__main">
                                    <p class="support-admin-card__title">Q. {{ post.title }}</p>
                                    <p class="support-admin-card__body">A. {{ post.body }}</p>
                                    <p class="support-admin-card__meta">{{ post.created_at }}</p>
                                </div>
                                <div class="support-admin-card__actions">
                                    <button type="button" class="op-btn op-btn--ghost" @click="openPostEditor('faq', post)">수정</button>
                                    <button type="button" class="op-btn op-btn--danger" @click="deleteSupportPost(post)">삭제</button>
                                </div>
                            </article>
                        </div>
                    </n-tab-pane>

                    <!-- 1:1 문의 처리 -->
                    <n-tab-pane name="tickets" tab="1:1 문의 처리">
                        <div class="admin-filter-row">
                            <n-select
                                :value="supportTicketStatus"
                                size="small"
                                style="width: 140px"
                                :options="SUPPORT_TICKET_STATUS_OPTIONS"
                                @update:value="(value) => { supportTicketStatus = value ?? ''; loadSupportTickets(); }"
                            />
                            <span class="admin-filter-info">총 {{ supportTickets.length }}건</span>
                        </div>

                        <div v-if="supportTicketsLoading" class="admin-skeleton">
                            <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                        </div>

                        <EmptyState
                            v-else-if="supportTickets.length === 0"
                            icon="inbox"
                            title="확인할 문의가 없습니다"
                            hint="사용자가 문의를 남기면 이 목록에 표시됩니다"
                        />

                        <div v-else class="support-admin-list">
                            <article v-for="ticket in supportTickets" :key="ticket.id" class="support-admin-card">
                                <div class="support-admin-card__main">
                                    <p class="support-admin-card__title">
                                        {{ ticket.title }}
                                        <span class="support-ticket-chip" :class="`support-ticket-chip--${ticket.status}`">{{ ticket.status_label }}</span>
                                    </p>
                                    <p class="support-admin-card__body">{{ ticket.body }}</p>
                                    <p v-if="ticket.answer" class="support-admin-card__answer">
                                        <strong>답변</strong> {{ ticket.answer }}
                                    </p>
                                    <p class="support-admin-card__meta">
                                        {{ ticket.user?.name ?? '탈퇴 사용자' }} ({{ ticket.user?.email }}) · {{ ticket.created_at }}
                                    </p>
                                </div>
                                <div v-if="ticket.status === 'open'" class="support-admin-card__actions">
                                    <button type="button" class="op-btn op-btn--ok" @click="openAnswer(ticket)">답변</button>
                                </div>
                            </article>
                        </div>
                    </n-tab-pane>
                </n-tabs>
            </n-tab-pane>

            <n-tab-pane name="operations" tab="운영 개입">
                <p class="admin-page-hint">
                    정상 운행은 자동으로 흐르게 두고, 문제가 생긴 운행·사용자만 관리자가 개입합니다.
                    우선순위: 🔴 즉시 처리 → 🟡 확인 필요 → 🟢 정상.
                </p>

                <n-tabs v-model:value="opTab" type="segment" animated size="small" @update:value="onOpTabChange">
                    <!-- 일일 운영 — 문제 우선 한 화면 요약 -->
                    <n-tab-pane name="daily" tab="일일 운영">
                        <div v-if="dailyLoading && !daily" class="admin-skeleton">
                            <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                        </div>

                        <template v-else-if="daily">
                            <div class="op-daily__grid">
                                <section
                                    v-for="group in [
                                        { key: 'red', title: '즉시 처리', desc: '문제가 있어 바로 손이 가야 하는 업무' },
                                        { key: 'yellow', title: '확인 필요', desc: '승인·요청처럼 확인이 필요한 업무' },
                                        { key: 'green', title: '정상', desc: '오늘 정상적으로 완료된 운행' },
                                    ]"
                                    :key="group.key"
                                    class="op-daily-group"
                                    :class="`op-daily-group--${group.key}`"
                                >
                                    <header class="op-daily-group__head">
                                        <strong>{{ group.title }}</strong>
                                        <span>{{ group.desc }}</span>
                                    </header>
                                    <div class="op-daily-group__body">
                                        <button
                                            v-for="item in DAILY_ITEMS.filter((i) => i.group === group.key)"
                                            :key="item.key"
                                            type="button"
                                            class="op-daily-item"
                                            :class="{ 'op-daily-item--link': item.tab || item.opTab }"
                                            @click="goFromDaily(item)"
                                        >
                                            <span class="op-daily-item__label">{{ item.label }}</span>
                                            <span class="op-daily-item__count">{{ daily[group.key][item.key] ?? 0 }}</span>
                                        </button>
                                    </div>
                                </section>
                            </div>
                            <div class="op-daily-foot">
                                <button type="button" class="op-btn op-btn--ghost" @click="loadDaily">
                                    <BaseIcon name="refresh" :size="14" /> 새로고침
                                </button>
                            </div>
                        </template>
                    </n-tab-pane>

                    <!-- 운행 개입 — 숨김·보류·강제 취소 -->
                    <n-tab-pane name="orders" tab="운행 개입">
                        <div class="admin-filter-row">
                            <n-input
                                :value="opOrderQ"
                                size="small"
                                style="width: 220px"
                                placeholder="노선·번호 검색"
                                clearable
                                @update:value="(value) => { opOrderQ = value ?? ''; }"
                                @keyup.enter="loadOpOrders"
                            />
                            <n-select
                                :value="opOrderStatus"
                                size="small"
                                style="width: 140px"
                                :options="[{ value: '', label: '전체 상태' }, ...opMeta.order_statuses]"
                                @update:value="(value) => { opOrderStatus = value ?? ''; loadOpOrders(); }"
                            />
                            <button type="button" class="op-btn op-btn--ghost" @click="loadOpOrders">
                                <BaseIcon name="refresh" :size="14" /> 조회
                            </button>
                        </div>

                        <div v-if="opOrdersLoading" class="admin-skeleton">
                            <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                        </div>

                        <EmptyState
                            v-else-if="opOrders.length === 0"
                            icon="inbox"
                            title="개입 대상 운행이 없습니다"
                            hint="검색어나 상태를 바꿔 확인해 보세요"
                        />

                        <div v-else class="op-list">
                            <article v-for="row in opOrders" :key="row.id" class="op-order" :class="{ 'op-order--hold': row.admin_hold }">
                                <div class="op-order__main">
                                    <p class="op-order__route">
                                        {{ row.route }}
                                        <span class="op-order__number">{{ row.order_number }}</span>
                                    </p>
                                    <p class="op-order__meta">
                                        {{ row.service_date }} {{ row.service_time }} · {{ row.amount.toLocaleString() }}원
                                        <template v-if="row.registrant"> · 등록 {{ row.registrant.name }}</template>
                                        <template v-if="row.driver"> · 기사 {{ row.driver.name }}</template>
                                    </p>
                                    <p v-if="row.admin_hold_reason" class="op-order__reason">보류 사유: {{ row.admin_hold_reason }}</p>
                                </div>
                                <div class="op-order__side">
                                    <span class="op-order__status" :style="{ background: statusColorVar[row.status], color: autoStatusTextColor(row.status) }">
                                        {{ row.status_label }}
                                    </span>
                                    <span v-if="row.is_hidden" class="op-badge op-badge--gray">숨김</span>
                                    <span v-if="row.admin_hold" class="op-badge op-badge--yellow">보류</span>
                                    <div class="op-order__actions">
                                        <button
                                            type="button"
                                            class="op-btn op-btn--ghost"
                                            :disabled="opActionBusy"
                                            @click="openOpAction('hide', row)"
                                        >
                                            {{ row.is_hidden ? '숨김 해제' : '숨김' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="op-btn op-btn--ghost"
                                            :disabled="opActionBusy"
                                            @click="openOpAction('hold', row)"
                                        >
                                            {{ row.admin_hold ? '보류 해제' : '보류' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="op-btn op-btn--danger"
                                            :disabled="opActionBusy"
                                            @click="openOpAction('cancel', row)"
                                        >
                                            강제 취소
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </n-tab-pane>

                    <!-- 채팅 운영 — 대화 확인 + 중재 -->
                    <n-tab-pane name="chat" tab="채팅 운영">
                        <div class="admin-filter-row">
                            <n-input
                                :value="convQ"
                                size="small"
                                style="width: 220px"
                                placeholder="노선·참가자 검색"
                                clearable
                                @update:value="(value) => { convQ = value ?? ''; }"
                                @keyup.enter="loadConvs"
                            />
                            <button type="button" class="op-btn op-btn--ghost" @click="loadConvs">
                                <BaseIcon name="refresh" :size="14" /> 조회
                            </button>
                        </div>

                        <div v-if="convsLoading" class="admin-skeleton">
                            <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                        </div>

                        <EmptyState
                            v-else-if="convs.length === 0"
                            icon="inbox"
                            title="확인할 대화가 없습니다"
                            hint="채팅이 시작되면 이 목록에 표시됩니다"
                        />

                        <div v-else class="op-chat">
                            <div class="op-chat__list">
                                <button
                                    v-for="conv in convs"
                                    :key="conv.id"
                                    type="button"
                                    class="op-chat-row"
                                    :class="{ 'op-chat-row--active': convSelected?.id === conv.id }"
                                    @click="selectConv(conv)"
                                >
                                    <strong class="op-chat-row__who">
                                        {{ (conv.users || []).map((u) => `${u.name}(${u.role_label})`).join(' · ') }}
                                    </strong>
                                    <span v-if="conv.order" class="op-chat-row__route">{{ conv.order.route }}</span>
                                    <span class="op-chat-row__meta">
                                        {{ conv.order ? `${conv.order.service_date} ${conv.order.service_time}` : '' }}
                                        · {{ conv.last_message_at }}
                                    </span>
                                </button>
                            </div>

                            <div class="op-chat__thread">
                                <template v-if="convSelected">
                                    <div v-if="convMessagesLoading" class="op-chat__empty">대화를 불러오는 중입니다...</div>
                                    <div v-else-if="convMessages.length === 0" class="op-chat__empty">아직 메시지가 없습니다.</div>
                                    <div v-else class="op-chat__messages">
                                        <div
                                            v-for="msg in convMessages"
                                            :key="msg.id"
                                            class="op-chat-msg"
                                            :class="{ 'op-chat-msg--moderator': msg.payload?.moderator }"
                                        >
                                            <div class="op-chat-msg__head">
                                                <strong>
                                                    {{ msg.payload?.moderator ? '운영팀' : msg.user_name || '알 수 없음' }}
                                                </strong>
                                                <span>{{ msg.created_at }}</span>
                                            </div>
                                            <p class="op-chat-msg__body">{{ msg.body || '(내용 없음)' }}</p>
                                            <img
                                                v-for="(image, index) in msg.images || []"
                                                :key="index"
                                                :src="image"
                                                class="op-chat-msg__image"
                                                alt="대화 이미지"
                                            />
                                        </div>
                                    </div>

                                    <div class="op-chat__moderate">
                                        <n-input
                                            v-model:value="convModerateBody"
                                            type="textarea"
                                            :rows="2"
                                            :maxlength="2000"
                                            placeholder="중재 메시지를 입력하세요. 참가자에게 새 메시지로 전달됩니다."
                                        />
                                        <button
                                            type="button"
                                            class="op-btn op-btn--ok"
                                            :disabled="convModerateBusy || !convModerateBody.trim()"
                                            @click="sendModerate"
                                        >
                                            중재 메시지 보내기
                                        </button>
                                    </div>
                                </template>
                                <div v-else class="op-chat__empty">왼쪽에서 대화를 선택해 내용을 확인하세요.</div>
                            </div>
                        </div>
                    </n-tab-pane>

                    <!-- 정산 보류 — 보류된 정산은 출금 신청 대상에서 제외 -->
                    <n-tab-pane name="settle" tab="정산 보류">
                        <div v-if="settleLoading" class="admin-skeleton">
                            <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                        </div>

                        <EmptyState
                            v-else-if="settleRows.length === 0"
                            icon="inbox"
                            title="대상 정산이 없습니다"
                            hint="완료된 운행이 정산되면 이 목록에 표시됩니다"
                        />

                        <div v-else class="op-list">
                            <article v-for="row in settleRows" :key="row.id" class="op-order" :class="{ 'op-order--hold': row.hold_reason }">
                                <div class="op-order__main">
                                    <p class="op-order__route">
                                        {{ row.route || '(운행 정보 없음)' }}
                                        <template v-if="row.driver"><span class="op-order__number">{{ row.driver.name }} 기사</span></template>
                                    </p>
                                    <p class="op-order__meta">
                                        {{ row.service_date ?? '' }} · {{ row.net_amount.toLocaleString() }}원
                                    </p>
                                    <p v-if="row.hold_reason" class="op-order__reason">보류 사유: {{ row.hold_reason }}</p>
                                </div>
                                <div class="op-order__side">
                                    <span v-if="row.hold_reason" class="op-badge op-badge--yellow">보류</span>
                                    <span v-else class="op-badge op-badge--green">정상</span>
                                    <div class="op-order__actions">
                                        <button
                                            type="button"
                                            class="op-btn op-btn--ghost"
                                            :disabled="settleActionBusy"
                                            @click="openSettleAction(row)"
                                        >
                                            {{ row.hold_reason ? '보류 해제' : '보류' }}
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </n-tab-pane>
                </n-tabs>
            </n-tab-pane>

            <n-tab-pane name="audit" tab="감사 로그">
                <p class="admin-page-hint">
                    관리자가 직접 처리한 행위(제재·역할 변경·운행 개입·채팅 중재·정산 보류·출금·신고·증빙 심사)를 기록합니다.
                    정상 운행은 자동 처리되므로, 관리자가 건드린 문제 건만 이 목록에 남습니다.
                </p>

                <div v-if="auditLoading" class="admin-skeleton">
                    <div v-for="n in 3" :key="n" class="sk-card admin-skeleton__row" />
                </div>

                <EmptyState
                    v-else-if="auditRows.length === 0"
                    icon="inbox"
                    title="기록된 운영 행위가 없습니다"
                    hint="관리자가 운행·사용자·정산을 직접 처리하면 이 목록에 기록됩니다"
                />

                <div v-else class="op-list">
                    <article v-for="row in auditRows" :key="row.id" class="op-order">
                        <div class="op-order__main">
                            <p class="op-order__route">
                                {{ row.action_label }}
                                <template v-if="row.message">
                                    <span class="op-order__number">{{ row.message }}</span>
                                </template>
                            </p>
                            <p class="op-order__meta">{{ row.admin_name }} · {{ formatVerifyTime(row.created_at_iso) }}</p>
                        </div>
                        <div class="op-order__side">
                            <span class="op-badge op-badge--gray">{{ row.action }}</span>
                        </div>
                    </article>
                </div>
            </n-tab-pane>

            <!-- 역할 변경 모달 — 기사↔등록자 전환·관리자 지정 -->
            <n-modal
                v-model:show="roleChangeOpen"
                preset="card"
                title="역할 변경"
                :style="{ maxWidth: '400px' }"
            >
                <p class="admin-page-hint">
                    {{ roleTarget?.name }}님의 계정 역할을 변경합니다. 변경 즉시 해당 계정의 접근 권한에도 반영됩니다.
                </p>
                <n-select
                    v-model:value="roleValue"
                    :options="roleOptions"
                    placeholder="변경할 역할을 선택하세요"
                />
                <template #footer>
                    <div class="admin-modal-actions">
                        <n-button @click="roleChangeOpen = false">취소</n-button>
                        <n-button type="primary" :loading="roleBusy" @click="submitRoleChange">변경</n-button>
                    </div>
                </template>
            </n-modal>

            <!-- 등록자 수수료율 모달 — 업체와 개별 계약한 요율 지정/해제 -->
            <n-modal
                v-model:show="feeRateOpen"
                preset="card"
                title="등록자 수수료율"
                :style="{ maxWidth: '400px' }"
            >
                <p class="admin-page-hint">
                    {{ feeRateTarget?.company_name || feeRateTarget?.name }}님의 정산 수수료율을 지정합니다.
                    비워 두면 전역 기본 요율이 적용됩니다. 실제 적용된 요율은 정산 원장에 기록됩니다.
                </p>
                <n-input-number
                    v-model:value="feeRateValue"
                    :min="0"
                    :max="100"
                    :step="0.5"
                    clearable
                    placeholder="예: 3.5"
                    style="width: 100%"
                >
                    <template #suffix>%</template>
                </n-input-number>
                <template #footer>
                    <div class="admin-modal-actions">
                        <n-button @click="feeRateOpen = false">취소</n-button>
                        <n-button type="primary" :loading="feeRateBusy" @click="submitFeeRate">저장</n-button>
                    </div>
                </template>
            </n-modal>
        </n-tabs>

        <!-- 신고 처리 단계 진행 모달 — 다음 단계 + 처리 메모 입력 -->
        <n-modal
            v-model:show="advanceOpen"
            preset="card"
            :style="{ width: 'min(92vw, 420px)' }"
            title="처리 단계 진행"
        >
            <div class="admin-modal-body">
                <p class="admin-modal-target">{{ advanceTarget?.target_label }} · {{ reportSubjectText(advanceTarget ?? {}) }}</p>
                <label class="admin-modal-label">다음 단계</label>
                <n-select v-model:value="advanceStatus" :options="nextOptions" />
                <label class="admin-modal-label">처리 메모 (선택)</label>
                <n-input
                    v-model:value="advanceNote"
                    type="textarea"
                    :rows="3"
                    :maxlength="1000"
                    placeholder="확인·조사한 내용을 적어 주세요. '처리·완료' 단계에서는 신고자에게 그대로 전달됩니다."
                />
            </div>
            <template #footer>
                <div class="admin-modal-footer">
                    <n-button :disabled="advanceBusy" @click="advanceOpen = false">취소</n-button>
                    <n-button type="primary" :loading="advanceBusy" :disabled="!advanceStatus" @click="submitAdvance">
                        단계 진행
                    </n-button>
                </div>
            </template>
        </n-modal>

        <!-- 사용자 제재 모달 — 상태(정상/주의/운행 제한/정지) + 사유 입력 -->
        <n-modal v-model:show="sanctionOpen" preset="card" :style="{ width: 'min(92vw, 420px)' }" title="사용자 제재">
            <div class="admin-modal-body">
                <p class="admin-modal-target">{{ sanctionTarget?.name }} ({{ roleLabel(sanctionTarget?.role) }})</p>
                <p class="admin-page-hint">
                    상태를 바꾸면 대상 사용자에게 알림이 가고, 정지·운행 제한은 기사의 운행 신청·요금 제안이 차단됩니다.
                    기존 제재를 풀려면 '정상'을 선택하세요.
                </p>
                <label class="admin-modal-label">계정 상태</label>
                <n-select v-model:value="sanctionStatus" :options="opMeta.moderation" />
                <label class="admin-modal-label">사유 <em>(필수)</em></label>
                <n-input
                    v-model:value="sanctionNote"
                    type="textarea"
                    :rows="3"
                    :maxlength="1000"
                    placeholder="제재 사유를 입력해 주세요. 대상 사용자에게 알림으로 전달됩니다. (해제 시에도 사유를 남겨 기록으로 보존합니다)"
                />
            </div>
            <template #footer>
                <div class="admin-modal-footer">
                    <n-button :disabled="sanctionBusy" @click="sanctionOpen = false">취소</n-button>
                    <n-button
                        type="warning"
                        :loading="sanctionBusy"
                        :disabled="!sanctionNote.trim()"
                        @click="submitSanction"
                    >
                        {{ sanctionStatus === 'active' ? '정상으로 해제' : '제재 적용' }}
                    </n-button>
                </div>
            </template>
        </n-modal>

        <!-- 운행 개입 모달 — 숨김/보류 토글·강제 취소 (사유 입력) -->
        <n-modal v-model:show="opActionOpen" preset="card" :style="{ width: 'min(92vw, 420px)' }" :title="opActionTitle">
            <div class="admin-modal-body">
                <p class="admin-modal-target">{{ opActionTarget?.order_number }} · {{ opActionTarget?.route }}</p>
                <p class="admin-page-hint">{{ opActionNote }}</p>
                <label class="admin-modal-label">사유 <em v-if="opActionNeedReason">(필수)</em></label>
                <n-input
                    v-model:value="opActionReason"
                    type="textarea"
                    :rows="3"
                    :maxlength="1000"
                    :placeholder="opActionNeedReason ? '사유를 입력해 주세요. 운행 타임라인에 기록됩니다.' : '해제 시 사유는 남기지 않아도 됩니다.'"
                />
            </div>
            <template #footer>
                <div class="admin-modal-footer">
                    <n-button :disabled="opActionBusy" @click="opActionOpen = false">취소</n-button>
                    <n-button
                        :type="opActionKind === 'cancel' ? 'error' : 'primary'"
                        :loading="opActionBusy"
                        :disabled="opActionNeedReason && !opActionReason.trim()"
                        @click="submitOpAction"
                    >
                        {{ opActionConfirmLabel }}
                    </n-button>
                </div>
            </template>
        </n-modal>

        <!-- 정산 보류 모달 — 보류/해제 -->
        <n-modal v-model:show="settleActionOpen" preset="card" :style="{ width: 'min(92vw, 420px)' }" :title="settleActionTitle">
            <div class="admin-modal-body">
                <p class="admin-modal-target">
                    {{ settleActionTarget?.driver?.name ?? '' }} 기사 · {{ settleActionTarget?.route ?? '' }}
                </p>
                <p class="admin-page-hint">
                    {{ settleActionNeedReason ? '보류된 정산은 기사가 출금 신청할 수 없습니다.' : '보류를 해제하면 다시 출금 신청 대상에 포함됩니다.' }}
                </p>
                <label v-if="settleActionNeedReason" class="admin-modal-label">사유 (필수)</label>
                <n-input
                    v-if="settleActionNeedReason"
                    v-model:value="settleActionReason"
                    type="textarea"
                    :rows="3"
                    :maxlength="1000"
                    placeholder="보류 사유를 입력해 주세요. 기사에게 알림으로 전달됩니다."
                />
            </div>
            <template #footer>
                <div class="admin-modal-footer">
                    <n-button :disabled="settleActionBusy" @click="settleActionOpen = false">취소</n-button>
                    <n-button
                        type="warning"
                        :loading="settleActionBusy"
                        :disabled="settleActionNeedReason && !settleActionReason.trim()"
                        @click="submitSettleAction"
                    >
                        {{ settleActionNeedReason ? '보류하기' : '보류 해제' }}
                    </n-button>
                </div>
            </template>
        </n-modal>

        <!-- 증빙 심사 처리 모달 — 증빙 사진 확인 + 승인/거절 -->
        <n-modal
            v-model:show="reviewOpen"
            preset="card"
            :style="{ width: 'min(94vw, 480px)' }"
            :title="`${reviewStatus === 'approved' ? '승인' : '거절'} — ${reviewTarget?.type_label ?? ''} 인증`"
        >
            <div class="admin-modal-body">
                <p class="admin-modal-target">
                    {{ reviewTarget?.user?.name ?? '탈퇴 사용자' }} ({{ reviewTarget?.user?.email }})
                </p>
                <a :href="reviewTarget?.image_url" target="_blank" rel="noopener" class="v-modal-proof">
                    <img :src="reviewTarget?.image_url" alt="증빙 사진 크게 보기" />
                    <span>원본 새 탭에서 보기</span>
                </a>
                <p v-if="reviewTarget?.note" class="v-card__note">참고: {{ reviewTarget.note }}</p>
                <label class="admin-modal-label">{{ reviewStatus === 'approved' ? '처리 메모 (선택)' : '거절 사유 (필수)' }}</label>
                <n-input
                    v-model:value="reviewNote"
                    type="textarea"
                    :rows="3"
                    :maxlength="1000"
                    :placeholder="reviewStatus === 'approved' ? '메모가 있으면 남겨 주세요.' : '거절 사유를 입력해 주세요. 신청자에게 그대로 전달됩니다.'"
                />
            </div>
            <template #footer>
                <div class="admin-modal-footer">
                    <n-button :disabled="reviewBusy" @click="reviewOpen = false">취소</n-button>
                    <n-button
                        :type="reviewStatus === 'approved' ? 'primary' : 'error'"
                        :loading="reviewBusy"
                        :disabled="reviewStatus === 'rejected' && !reviewNote.trim()"
                        @click="submitReview"
                    >
                        {{ reviewStatus === 'approved' ? '승인' : '거절' }}
                    </n-button>
                </div>
            </template>
        </n-modal>

        <!-- 공지/FAQ 작성·수정 모달 -->
        <n-modal v-model:show="postEditorOpen" preset="card" :style="{ width: 'min(94vw, 520px)' }" :title="postEditorTitleText">
            <div class="admin-modal-body">
                <label class="admin-modal-label">제목</label>
                <n-input
                    v-model:value="postEditorTitle"
                    :maxlength="200"
                    placeholder="제목을 입력해 주세요."
                />
                <label class="admin-modal-label">내용</label>
                <n-input
                    v-model:value="postEditorBody"
                    type="textarea"
                    :rows="8"
                    :maxlength="10000"
                    placeholder="안내할 내용을 입력해 주세요. 사용자 화면에 그대로 표시됩니다."
                />
            </div>
            <template #footer>
                <div class="admin-modal-footer">
                    <n-button :disabled="postEditorBusy" @click="postEditorOpen = false">취소</n-button>
                    <n-button
                        type="primary"
                        :loading="postEditorBusy"
                        :disabled="!postEditorTitle.trim() || !postEditorBody.trim()"
                        @click="submitPostEditor"
                    >
                        {{ postEditorTarget ? '수정 완료' : '등록' }}
                    </n-button>
                </div>
            </template>
        </n-modal>

        <!-- 1:1 문의 답변 모달 -->
        <n-modal v-model:show="answerOpen" preset="card" :style="{ width: 'min(94vw, 520px)' }" title="1:1 문의 답변">
            <div class="admin-modal-body">
                <p class="admin-modal-target">
                    {{ answerTarget?.user?.name ?? '탈퇴 사용자' }} ({{ answerTarget?.user?.email ?? '' }}) · {{ answerTarget?.title }}
                </p>
                <p class="support-admin-card__body support-modal-body">{{ answerTarget?.body }}</p>
                <label class="admin-modal-label">답변 내용 (필수)</label>
                <n-input
                    v-model:value="answerBody"
                    type="textarea"
                    :rows="5"
                    :maxlength="10000"
                    placeholder="답변을 입력해 주세요. 문의한 사용자에게 알림으로 전달됩니다."
                />
            </div>
            <template #footer>
                <div class="admin-modal-footer">
                    <n-button :disabled="answerBusy" @click="answerOpen = false">취소</n-button>
                    <n-button
                        type="primary"
                        :loading="answerBusy"
                        :disabled="!answerBody.trim()"
                        @click="submitAnswer"
                    >
                        답변 보내기
                    </n-button>
                </div>
            </template>
        </n-modal>
    </div>
</template>

<style scoped>
/* 로딩 스켈레톤 — admin-user 행 형태 */
.admin-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.admin-skeleton__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.admin-skeleton__line {
    flex: 1;
    min-width: 0;
}

.admin-skeleton__chips {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.admin-list {
    display: flex;
    flex-direction: column;
    gap: var(--card-gap);
}

.admin-user {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}

.admin-user__main {
    min-width: 0;
}

.admin-user__name {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* 업체명 칩 — 등록자 계정의 회사명 표시 */
.admin-user__company {
    padding: 1px 6px;
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
    color: var(--text-muted);
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* 모달 푸터 액션 정렬 (공용) */
.admin-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.admin-user__meta {
    margin: 4px 0 0;
    color: var(--text-muted);
    font-size: 11px;
}

.admin-user__verify {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex-shrink: 0;
}

.admin-user__verify-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.admin-user__verify-label {
    color: var(--text-muted);
    font-size: 11px;
}

.admin-user__verify-label--ok {
    color: var(--status-completed);
    font-weight: 700;
}

.admin-user__main > .admin-user__verify {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 14px;
    margin-top: 8px;
}

.admin-pager {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 16px;
}

.admin-pager__info {
    color: var(--text-muted);
    font-size: 11px;
}

/* 자동 운행 등록 설정 카드 */
.auto-card {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
}

.auto-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.auto-card__head strong {
    font-size: 11px;
    font-weight: 800;
}

.auto-card__desc {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.5;
}

.auto-card__row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.auto-card__label {
    flex-shrink: 0;
    width: 92px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}

.auto-card__value {
    color: var(--text);
    font-size: 11px;
}

.auto-card__count {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
}

.auto-card__tilde {
    color: var(--text-muted);
    font-size: 11px;
}

.auto-card__unit {
    color: var(--text-muted);
    font-size: 11px;
}

.auto-card__footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 4px;
    border-top: 1px solid var(--border);
}

/* 자동 등록 운행 이력 */
.auto-history {
    margin-top: 16px;
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}

.auto-history__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.auto-history__head strong {
    font-size: 11px;
    font-weight: 800;
}

.auto-history__desc {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.5;
}

.auto-history__actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.auto-history__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 14px;
}

.auto-history__row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg);
}

.auto-history__row--skel {
    flex-direction: column;
    align-items: stretch;
    border: 0;
}

.auto-history__status {
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
    white-space: nowrap;
}

.auto-history__main {
    flex: 1;
    min-width: 0;
}

.auto-history__route {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
    font-weight: 700;
}

.auto-history__meta {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 11px;
}

.auto-history__amount {
    flex-shrink: 0;
    color: var(--brand);
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.auto-history__del {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    flex-shrink: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: transparent;
    color: var(--danger);
    cursor: pointer;
    transition: border-color 0.12s ease, background 0.12s ease;
}

.auto-history__del:hover {
    border-color: var(--danger);
    background: color-mix(in srgb, var(--danger) 8%, transparent);
}

.auto-history__del:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.auto-history__empty {
    margin: 14px 0 0;
    padding: 14px;
    border-radius: 10px;
    background: var(--bg);
    color: var(--text-muted);
    font-size: 11px;
    text-align: center;
}

/* 자동 운행 스위치 라벨 대비 — naive 기본 글자색은 라이트 흰색(다크는 밝은 회색)인데
   켜짐 레일이 var(--brand)(라이트 #36adff·다크 #63e2b7 — 모두 밝음)라 대비가 약함.
   앱 표준 '밝은 채움 위 어두운 글자 #07120e'(MessageBubble 등)를 상태·모드별로 적용:
   - 켜짐: 레일이 밝은 brand → 두 모드 공통 #07120e
   - 꺼짐: 라이트는 연한 레일 위 흰 글자가 안 보임 → #07120e, 다크는 naive 기본(밝은 글자) 유지 */
.auto-card :deep(.n-switch.n-switch--active .n-switch__checked),
.auto-card :deep(.n-switch.n-switch--active .n-switch__unchecked) {
    color: #07120e;
}

.auto-card :deep(.n-switch__checked),
.auto-card :deep(.n-switch__unchecked) {
    color: #07120e;
}

html.dark .auto-card :deep(.n-switch__checked),
html.dark .auto-card :deep(.n-switch__unchecked) {
    color: initial;
}

@media (max-width: 480px) {
    .auto-card__row {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .auto-card__label {
        width: auto;
    }
}

/* ── 출금 처리 ── */
.admin-page-hint {
    margin: 4px 0 14px;
    font-size: 11px;
    color: var(--text-muted);
    line-height: 1.6;
}

.payout-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.payout-card {
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.payout-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.payout-card__driver {
    font-size: 12px;
    font-weight: 800;
}

.payout-card__amount {
    font-size: 13px;
    font-weight: 800;
    color: var(--text);
}

.payout-card__account,
.payout-card__meta {
    margin: 0;
    font-size: 11px;
    color: var(--text-muted);
}

.payout-card__actions {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
    margin-top: 6px;
    padding-top: 10px;
    border-top: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
}

.admin-payout-btn {
    padding: 7px 14px;
    border-radius: 999px;
    border: 0;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.admin-payout-btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.admin-payout-btn--ok {
    background: var(--brand);
    color: #07120e;
}

.admin-payout-btn--no {
    background: color-mix(in srgb, var(--danger) 12%, transparent);
    color: var(--danger);
}

/* ── 신고/분쟁 처리 ── */
.admin-filter-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 4px;
}
.admin-filter-info {
    color: var(--text-muted);
    font-size: 11px;
}

.report-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.report-card {
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.report-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

/* 신고 유형 — 연한 무채색 칩 */
.report-card__cat {
    padding: 1px 8px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--danger) 10%, transparent);
    color: var(--danger);
    font-size: 10px;
    font-weight: 600;
}

/* 처리 단계 색 — 접수(레드)=즉시 처리, 조사 중(옐로우)=확인, 완료(민트)=정상 */
.report-card__status {
    flex-shrink: 0;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
}
.report-card__status--pending {
    background: var(--danger);
    color: #ffffff;
}
.report-card__status--reviewing {
    background: #f0a800;
    color: #101418;
}
.report-card__status--investigating {
    background: #ffa940;
    color: #101418;
}
.report-card__status--handled {
    background: #13c2c2;
    color: #101418;
}
.report-card__status--completed {
    background: #18a058;
    color: #ffffff;
}

.report-card__subject {
    margin: 0;
    font-size: 12px;
    font-weight: 800;
}

.report-card__reason {
    margin: 0;
    font-size: 12px;
    line-height: 1.6;
    color: var(--text);
    white-space: pre-wrap;
    word-break: break-word;
}

.report-card__meta {
    margin: 0;
    font-size: 10px;
    color: var(--text-muted);
}

.report-card__note {
    margin: 0;
    padding: 8px 10px;
    border-radius: 10px;
    background: var(--bg);
    font-size: 11px;
    line-height: 1.5;
    color: var(--text);
    white-space: pre-wrap;
    word-break: break-word;
}
.report-card__note strong {
    display: block;
    margin-bottom: 2px;
    color: var(--text-muted);
    font-size: 10px;
}

.report-card__actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 6px;
    padding-top: 10px;
    border-top: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
}

/* 단계 진행 모달 */
.admin-modal-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.admin-modal-target {
    margin: 0;
    padding: 8px 10px;
    border-radius: 10px;
    background: var(--bg);
    font-size: 11px;
    font-weight: 700;
}
.admin-modal-label {
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}
.admin-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.admin-modal-label em {
    color: var(--danger);
    font-style: normal;
}

/* ── 운영 개입(B-2) 공통 — 제재 배지·액션 버튼 ── */
.op-badge {
    flex-shrink: 0;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
    white-space: nowrap;
}
.op-badge--green {
    background: color-mix(in srgb, var(--status-completed) 14%, transparent);
    color: var(--status-completed);
}
.op-badge--yellow {
    background: #f0a800;
    color: #101418;
}
.op-badge--amber {
    background: #ffa940;
    color: #101418;
}
.op-badge--red {
    background: var(--danger);
    color: #ffffff;
}
.op-badge--gray {
    background: color-mix(in srgb, var(--border) 70%, transparent);
    color: var(--text-muted);
}

.op-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 7px 12px;
    border-radius: 999px;
    border: 0;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
    cursor: pointer;
    white-space: nowrap;
}
.op-btn:disabled {
    opacity: 0.5;
    cursor: default;
}
.op-btn--ghost {
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text);
}
.op-btn--ghost:hover:not(:disabled) {
    border-color: var(--brand);
    color: var(--brand);
}
.op-btn--danger {
    background: color-mix(in srgb, var(--danger) 12%, transparent);
    color: var(--danger);
}
.op-btn--ok {
    background: var(--brand);
    color: #07120e;
}

/* ── 일일 운영 — 🔴 즉시 처리 > 🟡 확인 필요 > 🟢 정상 ── */
.op-daily__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: var(--card-gap);
}
.op-daily-group {
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}
.op-daily-group--red {
    border-top: 3px solid var(--danger);
}
.op-daily-group--yellow {
    border-top: 3px solid #f0a800;
}
.op-daily-group--green {
    border-top: 3px solid var(--status-completed);
}
.op-daily-group__head strong {
    font-size: 12px;
    font-weight: 800;
}
.op-daily-group__head span {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 10px;
}
.op-daily-group__body {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 10px;
}
.op-daily-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    padding: 9px 10px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg);
    font-family: inherit;
    font-size: 11px;
    color: var(--text);
    text-align: left;
    cursor: default;
}
.op-daily-item--link {
    cursor: pointer;
    transition: border-color 0.12s ease;
}
.op-daily-item--link:hover {
    border-color: var(--brand);
}
.op-daily-item__count {
    font-size: 14px;
    font-weight: 800;
}
.op-daily-group--red .op-daily-item__count {
    color: var(--danger);
}
.op-daily-group--yellow .op-daily-item__count {
    color: #b97e00;
}
.op-daily-group--green .op-daily-item__count {
    color: var(--status-completed);
}
.op-daily-foot {
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;
}

/* ── 운행 개입·정산 보류 목록 ── */
.op-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.op-order {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}
.op-order--hold {
    border-color: color-mix(in srgb, #f0a800 45%, var(--border));
}
.op-order__main {
    flex: 1;
    min-width: 0;
}
.op-order__route {
    margin: 0;
    font-size: 12px;
    font-weight: 800;
    overflow-wrap: anywhere;
}
.op-order__number {
    margin-left: 6px;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 400;
}
.op-order__meta {
    margin: 4px 0 0;
    color: var(--text-muted);
    font-size: 11px;
}
.op-order__reason {
    margin: 4px 0 0;
    font-size: 11px;
    color: var(--status-trading);
}
.op-order__side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
}
.op-order__status {
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
}
.op-order__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
    margin-top: 2px;
}

/* ── 채팅 운영 — 대화 목록 + 스레드 ── */
.op-chat {
    display: grid;
    grid-template-columns: 250px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}
.op-chat__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 60vh;
    overflow-y: auto;
    padding-right: 4px;
}
.op-chat-row {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    font-family: inherit;
    color: var(--text);
    text-align: left;
    cursor: pointer;
    transition: border-color 0.12s ease;
}
.op-chat-row:hover {
    border-color: var(--brand);
}
.op-chat-row--active {
    border-color: var(--brand);
    background: color-mix(in srgb, var(--brand) 6%, var(--surface));
}
.op-chat-row__who {
    font-size: 11px;
    font-weight: 800;
}
.op-chat-row__route {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
}
.op-chat-row__meta {
    font-size: 10px;
    color: var(--text-muted);
}
.op-chat__thread {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}
.op-chat__messages {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 50vh;
    overflow-y: auto;
    padding-right: 4px;
}
.op-chat-msg {
    padding: 9px 11px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
}
.op-chat-msg--moderator {
    border-color: color-mix(in srgb, var(--brand) 45%, var(--border));
    background: color-mix(in srgb, var(--brand) 6%, var(--surface));
}
.op-chat-msg__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: var(--text-muted);
    font-size: 10px;
}
.op-chat-msg__head strong {
    color: var(--text);
    font-size: 10px;
}
.op-chat-msg__body {
    margin: 4px 0 0;
    font-size: 11px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}
.op-chat-msg__image {
    display: block;
    margin-top: 6px;
    max-width: 140px;
    border-radius: 8px;
}
.op-chat__empty {
    padding: 18px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    color: var(--text-muted);
    font-size: 11px;
    text-align: center;
}
.op-chat__moderate {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}
.op-chat__moderate .n-input {
    flex: 1;
}

@media (max-width: 640px) {
    .op-chat {
        grid-template-columns: 1fr;
    }
    .op-chat__list {
        max-height: 200px;
    }
    .op-order {
        flex-direction: column;
    }
    .op-order__side {
        align-items: flex-start;
    }
    .op-daily__grid {
        grid-template-columns: 1fr;
    }
}

/* ── 증빙 심사(B-3) ── */
.v-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.v-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}
.v-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.v-card__who {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.v-card__who strong {
    font-size: 12px;
    font-weight: 800;
}
.v-card__who span {
    color: var(--text-muted);
    font-size: 10px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.v-card__chips {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.v-chip {
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}
.v-chip--type {
    background: color-mix(in srgb, var(--border) 70%, transparent);
    color: var(--text);
    font-weight: 600;
}
.v-chip--pending {
    background: #f0a800;
    color: #101418;
}
.v-chip--approved {
    background: var(--status-completed);
    color: #101418;
}
.v-chip--rejected {
    background: var(--danger);
    color: #ffffff;
}
.v-card__body {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.v-card__proof {
    width: 120px;
    height: 90px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg);
}
.v-card__info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.v-card__meta {
    margin: 0;
    color: var(--text-muted);
    font-size: 10px;
}
.v-card__note {
    margin: 0;
    font-size: 11px;
    color: var(--text);
    word-break: break-word;
}
.v-card__reason {
    margin: 0;
    padding: 8px 10px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--danger) 6%, var(--surface));
    border: 1px solid color-mix(in srgb, var(--danger) 28%, var(--border));
    font-size: 11px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}
.v-card__reason strong {
    display: block;
    color: var(--danger);
    font-size: 10px;
}
.v-card__actions {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
    padding-top: 8px;
    border-top: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
}
.v-card__actions .op-btn--ok {
    padding: 6px 16px;
}

/* 심사 모달 — 증빙 원본 */
.v-modal-proof {
    display: block;
    padding: 6px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--bg);
    text-align: center;
}
.v-modal-proof img {
    display: block;
    max-width: 100%;
    max-height: 300px;
    margin: 0 auto;
    border-radius: 8px;
    object-fit: contain;
}
.v-modal-proof span {
    display: inline-block;
    margin-top: 6px;
    color: var(--text-muted);
    font-size: 10px;
}

/* ── 고객지원(B-4) ── */
.support-admin-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.support-admin-card {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}
.support-admin-card__main {
    flex: 1;
    min-width: 0;
}
.support-admin-card__title {
    margin: 0;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.5;
    overflow-wrap: anywhere;
}
.support-admin-card__body {
    margin: 6px 0 0;
    font-size: 12px;
    line-height: 1.6;
    color: var(--text);
    white-space: pre-wrap;
    word-break: break-word;
}
.support-admin-card__meta {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-size: 10px;
}
.support-admin-card__answer {
    margin: 8px 0 0;
    padding: 8px 10px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--brand) 6%, var(--surface));
    border: 1px solid color-mix(in srgb, var(--brand) 22%, var(--border));
    font-size: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}
.support-admin-card__answer strong {
    display: block;
    margin-bottom: 2px;
    color: var(--text-muted);
    font-size: 10px;
}
.support-admin-card__actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}
.support-ticket-chip {
    margin-left: 6px;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    vertical-align: 2px;
    white-space: nowrap;
}
.support-ticket-chip--open {
    background: #f0a800;
    color: #101418;
}
.support-ticket-chip--answered {
    background: var(--status-completed);
    color: #101418;
}

/* 문의 답변 모달 — 문의 본문 */
.support-modal-body {
    margin: 8px 0 4px;
    padding: 10px 12px;
    border-radius: 10px;
    background: var(--bg);
}

/* ── 운영 지표(Q-6) — 문제 우선 카드 그리드 ── */
.metrics-grid {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.metrics-group__title {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 800;
    color: var(--text);
}

.metrics-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.metrics-dot--red {
    background: var(--danger);
}
.metrics-dot--yellow {
    background: #f0a800;
}
.metrics-dot--green {
    background: #1f9d63;
}
.metrics-dot--neutral {
    background: color-mix(in srgb, var(--text-muted) 60%, transparent);
}
.metrics-dot--brand {
    background: var(--brand);
}

.metrics-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.metric-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}

.metric-card__label {
    color: var(--text-muted);
    font-size: 11px;
}

.metric-card__value {
    font-size: 20px;
    font-weight: 800;
    line-height: 1.1;
    word-break: break-all;
}
.metric-card__value--red {
    color: var(--danger);
}
.metric-card__value--yellow {
    color: #b07f00;
}
.metric-card__value--green {
    color: var(--status-completed);
}
.metric-card__value--brand {
    color: var(--brand);
}

/* 앰버 값 텍스트 — 다크 배경에서는 밝게 보정(라이트 대비 #b07f00 유지) */
html.dark .metric-card__value--yellow {
    color: #e0b64d;
}

.metric-card__sub {
    color: var(--text-muted);
    font-size: 10px;
}
</style>
