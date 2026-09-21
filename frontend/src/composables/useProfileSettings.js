import { computed, onMounted, reactive, ref } from 'vue';
import { apiUpdateProfile } from '../api/auth';
import { apiCommunityUser } from '../api/community';
import { getApiErrorMessage } from '../api/client';
import { ROLE_CUSTOMER, roleLabel as roleLabelOf } from '../data/roles';
import { getActivePushSubscription, isPushSupported, subscribeToPush, unsubscribeFromPush } from '../utils/push';
import {
    isBrowserNotifyBlocked,
    isBrowserNotifyEnabled,
    requestNotifyPermission,
    setBrowserNotifyEnabled,
} from '../utils/browserNotify';

/**
 * 프로필 설정 — 회원정보 수정, 브라우저 알림, 내 실적을 담당한다.
 * (차량·면허 인증 신청은 B-3 증빙 심사로 대체되어 SettingsVerificationView가 전담한다)
 *
 * @param {object} options
 * @param {object} options.auth useAuthStore
 * @param {import('vue-router').Router} options.router
 * @param {object} options.message naive-ui message
 */
export function useProfileSettings({ auth, router, message }) {
    const saving = ref(false);
    const error = ref('');
    const success = ref('');

    // 브라우저 알림 설정 — 로컬 저장 + 웹 푸시 구독(서버 push_subscriptions) 동기화
    const notifyEnabled = ref(isBrowserNotifyEnabled());
    // 이 브라우저에서 푸시를 켤 수 있는지(HTTPS·PushManager) — 켤 수 없으면 화면이 이유를 안내한다
    const notifySupported = ref(isPushSupported());
    // 브라우저가 차단해 둔 상태 — 사용자가 브라우저 설정에서 풀어야 한다
    const notifyBlocked = ref(isBrowserNotifyBlocked());

    // 마운트 시 실제 활성 구독과 동기화 (다른 브라우저에서 꺼진 경우 반영)
    onMounted(() => {
        getActivePushSubscription()
            .then((sub) => {
                notifyEnabled.value = Boolean(sub) && isBrowserNotifyEnabled();
            })
            .catch(() => {});
    });

    const toggleNotify = async (enabled) => {
        try {
            if (enabled) {
                if (!('Notification' in window)) {
                    message.error('이 브라우저는 알림을 지원하지 않습니다.');

                    return;
                }

                // 차단된 뒤에는 권한 창이 다시 뜨지 않는다 — 켜기를 반복해도 실패만 하므로 먼저 안내한다
                if (isBrowserNotifyBlocked()) {
                    notifyBlocked.value = true;
                    notifyEnabled.value = false;
                    setBrowserNotifyEnabled(false);
                    message.error('브라우저가 알림을 차단하고 있습니다. 주소창의 자물쇠에서 알림을 허용으로 바꿔 주세요.');

                    return;
                }

                // 권한 요청 — 최초 한 번만
                if (Notification.permission === 'default') {
                    const granted = await requestNotifyPermission();

                    if (!granted) {
                        notifyBlocked.value = isBrowserNotifyBlocked();
                        setBrowserNotifyEnabled(false);
                        message.error('브라우저 알림 권한이 거부되었습니다. 브라우저 설정에서 허용해 주세요.');

                        return;
                    }
                }

                // 푸시 구독 생성 + 서버 저장
                await subscribeToPush();
                notifyBlocked.value = false;
                setBrowserNotifyEnabled(true);
                message.success('브라우저 알림이 켜졌습니다. 앱이 닫혀 있어도 알림을 받을 수 있습니다.');
            } else {
                // 구독 해제 + 서버 제거
                await unsubscribeFromPush();
                setBrowserNotifyEnabled(false);
                message.success('브라우저 알림이 꺼졌습니다.');
            }
        } catch (e) {
            notifyEnabled.value = !enabled;
            message.error(getApiErrorMessage(e, '알림 설정에 실패했습니다.'));
        }
    };

    const myStats = ref(null);

    // 내 실적 (완료 운행·매출·평점) — 실패해도 프로필은 정상 표시
    const loadMyStats = () => {
        apiCommunityUser(auth.user?.id)
            .then(({ data }) => {
                myStats.value = data.data;
            })
            .catch(() => {});
    };

    const form = reactive({
        name: '',
        phone: '',
        companyName: '',
    });

    const roleLabel = computed(() => roleLabelOf(auth.user?.role));
    const isCustomer = computed(() => auth.user?.role === ROLE_CUSTOMER);

    const initials = computed(() => auth.user?.name?.charAt(0) ?? 'N');
    const level = computed(() => auth.user?.level ?? null);
    const formatWon = (value) => (value ?? 0).toLocaleString();

    const save = async () => {
        if (!form.name.trim()) {
            error.value = '이름을 입력해 주세요.';
            message.warning('이름을 입력해 주세요.');

            return;
        }

        saving.value = true;
        error.value = '';
        success.value = '';

        try {
            const { data } = await apiUpdateProfile({
                name: form.name.trim(),
                phone: form.phone.trim(),
                // 업체명은 등록자(업체)만 — 기사 등 다른 역할에서는 서버가 무시
                ...(isCustomer.value ? { company_name: form.companyName.trim() || null } : {}),
            });

            auth.user = data.data;
            success.value = '회원정보가 저장되었습니다.';
            message.success('프로필이 저장되었습니다.');
        } catch (e) {
            error.value = getApiErrorMessage(e, '저장에 실패했습니다.');
            message.error(error.value);
        } finally {
            saving.value = false;
        }
    };

    const logout = async () => {
        await auth.logout();
        router.push({ name: 'login' });
    };

    return {
        saving,
        error,
        success,
        notifyEnabled,
        notifySupported,
        notifyBlocked,
        toggleNotify,
        myStats,
        loadMyStats,
        form,
        roleLabel,
        isCustomer,
        initials,
        level,
        formatWon,
        save,
        logout,
    };
}
