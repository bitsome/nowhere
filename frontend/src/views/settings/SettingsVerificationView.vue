<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { useAuthStore } from '../../stores/auth';
import { getApiErrorMessage } from '../../api/client';
import { apiMyVerificationRequests, apiSubmitVerification } from '../../api/verification';
import { ROLE_CUSTOMER } from '../../data/roles';
import BaseIcon from '../../components/common/BaseIcon.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SettingsVerificationView' });

const auth = useAuthStore();
const router = useRouter();
const message = useMessage();

const isCustomer = computed(() => auth.user?.role === ROLE_CUSTOMER);

// 역할별 인증 항목 — 기사(Driver)는 차량/면허, 등록자(Customer)는 사업자등록증/대표 계좌 (Q-4)
const ALL_VERIFY_TYPES = {
    driver: [
        {
            key: 'vehicle',
            label: '차량 인증',
            desc: '차량등록증을 촬영해 주세요. 번호판·차량 정보가 선명해야 인증이 빨라집니다.',
            verifiedKey: 'is_vehicle_verified',
        },
        {
            key: 'license',
            label: '면허 인증',
            desc: '운전면허증을 촬영해 주세요. 얼굴·성명·면허번호가 선명해야 인증이 빨라집니다.',
            verifiedKey: 'is_license_verified',
        },
    ],
    customer: [
        {
            key: 'business',
            label: '사업자 인증',
            desc: '사업자등록증을 촬영해 주세요. 업체명·대표자·사업자등록번호가 선명해야 인증이 빨라집니다.',
            verifiedKey: 'is_business_verified',
        },
        {
            key: 'account',
            label: '대표 계좌 인증',
            desc: '대표자 명의 계좌(통장 사본·모바일 계좌 화면)를 촬영해 주세요. 예금주·계좌번호가 보여야 인증됩니다.',
            verifiedKey: 'is_account_verified',
        },
    ],
};

const VERIFY_TYPES = computed(() => (isCustomer.value ? ALL_VERIFY_TYPES.customer : ALL_VERIFY_TYPES.driver));

// 내 최근 심사 현황 — 역할별 항목의 최신 요청 (대기/승인/거절 + 사유)
const latest = ref({});
const loading = ref(true);
const submittingKey = ref('');

// 신청 폼 상태 — 파일·미리보기·메모 (재평가와 무관하게 고정)
const formState = reactive({
    vehicle: { image: null, preview: '', note: '' },
    license: { image: null, preview: '', note: '' },
    business: { image: null, preview: '', note: '' },
    account: { image: null, preview: '', note: '' },
});

const loadMyRequests = async () => {
    try {
        const { data } = await apiMyVerificationRequests();
        latest.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '인증 현황을 불러오지 못했습니다.'));
    } finally {
        loading.value = false;
    }
};

// 상태 판단 — 인증 완료 우선, 이후 최신 요청(대기/거절) 순
const statusOf = (type) => {
    if (auth.user?.[type.verifiedKey]) {
        return { key: 'done', label: '인증 완료' };
    }

    const recent = latest.value[type.key];

    if (recent?.status === 'pending') {
        return { key: 'pending', label: '심사 대기 중' };
    }
    if (recent?.status === 'rejected') {
        return { key: 'rejected', label: '거절됨' };
    }

    return { key: 'none', label: '미인증' };
};

const items = computed(() =>
    VERIFY_TYPES.map((type) => ({
        ...type,
        verified: Boolean(auth.user?.[type.verifiedKey]),
        status: statusOf(type),
        recent: latest.value[type.key],
        form: formState[type.key],
    })),
);

const selectFile = (item, event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }
    item.form.image = file;
    item.form.preview = URL.createObjectURL(file);
};

const submit = async (item) => {
    const image = item.form.image;

    if (!image) {
        message.warning('증빙 사진을 선택해 주세요.');

        return;
    }
    submittingKey.value = item.key;

    try {
        await apiSubmitVerification(item.key, image, item.form.note);
        item.form.image = null;
        item.form.preview = '';
        item.form.note = '';
        message.success('증빙이 접수되었습니다. 관리자 심사 후 알림으로 알려 드릴게요.');
        await loadMyRequests();
    } catch (e) {
        message.error(getApiErrorMessage(e, '신청에 실패했습니다.'));
    } finally {
        submittingKey.value = '';
    }
};

onMounted(loadMyRequests);
</script>

<template>
    <div class="settings-page page-shell">
        <button type="button" class="settings-back" @click="router.push({ name: 'settings' })"><BaseIcon name="arrow-back" :size="16" /> 설정</button>

        <div class="page-head">
            <div>
                <h1 class="page-head__title">인증 상태</h1>
                <p class="page-head__desc">
                    {{ isCustomer ? '사업자등록증·대표 계좌' : '차량·면허' }} 증빙 사진을 올리면 관리자가 심사합니다.
                    승인되면 {{ isCustomer ? '프로필에 업체 인증이' : '마켓에서 인증 배지가' }} 표시됩니다.
                </p>
            </div>
        </div>

        <div v-if="loading" class="verify-skeleton">
            <div v-for="n in 2" :key="n" class="verify-card">
                <div class="sk-line" style="width: 120px" />
                <div class="sk-line" style="width: 80%; margin-top: 8px" />
            </div>
        </div>

        <template v-else>
            <n-alert
                v-if="items.some((item) => item.status.key !== 'none')"
                type="info"
                :show-icon="true"
                class="verify-block"
            >
                인증 심사 결과는 알림으로 전달됩니다. 거절된 경우 사유를 확인하고 다시 신청할 수 있습니다.
            </n-alert>

            <section v-for="item in items" :key="item.key" class="verify-card">
                <header class="verify-card__head">
                    <div>
                        <h2 class="verify-card__title">{{ item.label }}</h2>
                        <p class="verify-card__desc">{{ item.desc }}</p>
                    </div>
                    <span class="verify-chip" :class="`verify-chip--${item.status.key}`">
                        <BaseIcon v-if="item.status.key === 'done'" name="check" :size="11" />
                        {{ item.status.label }}
                    </span>
                </header>

                <!-- 인증 완료 — 추가 신청 없음 -->
                <p v-if="item.status.key === 'done'" class="verify-card__done">
                    인증이 완료된 상태입니다.
                </p>

                <!-- 심사 대기 — 접수된 증빙 미리보기 -->
                <template v-else-if="item.status.key === 'pending'">
                    <img :src="item.recent.image_url" class="verify-card__proof" alt="제출한 증빙 사진" />
                    <p class="verify-card__hint">접수된 증빙을 관리자가 확인하고 있습니다.</p>
                </template>

                <!-- 거절 — 사유 확인 후 재신청 -->
                <template v-else-if="item.status.key === 'rejected'">
                    <div v-if="item.recent?.review_note" class="verify-card__reason">
                        <strong>거절 사유</strong>
                        {{ item.recent.review_note }}
                    </div>
                    <p class="verify-card__hint">사진을 다시 찍어 아래에서 재신청할 수 있습니다.</p>
                </template>

                <!-- 미인증/거절 후 재신청 폼 -->
                <form
                    v-if="item.status.key === 'none' || item.status.key === 'rejected'"
                    class="verify-form"
                    @submit.prevent="submit(item)"
                >
                    <div class="verify-form__photo">
                        <label class="verify-form__picker">
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                @change="selectFile(item, $event)"
                            />
                            <template v-if="item.form.preview">
                                <img :src="item.form.preview" alt="선택한 증빙 사진 미리보기" />
                                <span>사진 바꾸기</span>
                            </template>
                            <template v-else>
                                <BaseIcon name="image" :size="22" />
                                <strong>증빙 사진 선택</strong>
                                <span>jpg · png · webp (10MB 이하)</span>
                            </template>
                        </label>
                    </div>
                    <div class="verify-form__note">
                        <n-input
                            v-model:value="item.form.note"
                            type="textarea"
                            :rows="2"
                            :maxlength="500"
                            placeholder="참고 사항이 있으면 적어 주세요. (선택)"
                        />
                    </div>
                    <button type="submit" class="verify-form__submit" :disabled="submittingKey !== '' || !item.form.image">
                        {{ submittingKey === item.key ? '접수 중...' : '신청하기' }}
                    </button>
                </form>
            </section>
        </template>
    </div>
</template>

<style scoped>
.settings-page {
    padding-bottom: 24px;
}

.settings-back {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    margin-left: -10px;
    border: 0;
    background: none;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.12s ease;
}

.settings-back:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

.verify-block {
    margin-bottom: 14px;
}

/* 로딩 스켈레톤 */
.verify-skeleton {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.verify-card {
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}

.verify-card + .verify-card {
    margin-top: 14px;
}

.verify-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.verify-card__title {
    margin: 0;
    font-size: 13px;
    font-weight: 800;
}

.verify-card__desc {
    margin: 4px 0 0;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.5;
}

/* 상태 칩 — 완료(민트)/대기(옐로우)/거절(레드)/미인증(회색).
   배지 컨벤션(padding 1px 6px / 10px / 굵기 제거) 준수 — 회원정보의 동일 상태(verify-row__done·__pending)와 규격 통일 */
.verify-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 400;
    white-space: nowrap;
}
.verify-chip--done {
    background: var(--status-completed);
    color: #101418;
}
.verify-chip--pending {
    background: #f0a800;
    color: #101418;
}
.verify-chip--rejected {
    background: var(--danger);
    color: #ffffff;
}
.verify-chip--none {
    background: color-mix(in srgb, var(--border) 70%, transparent);
    color: var(--text-muted);
}

.verify-card__done {
    margin: 14px 0 0;
    padding: 12px;
    border-radius: 10px;
    background: var(--bg);
    color: var(--text-muted);
    font-size: 12px;
    text-align: center;
}

/* 제출한 증빙 미리보기 */
.verify-card__proof {
    display: block;
    margin-top: 14px;
    max-width: 220px;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.verify-card__reason {
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid color-mix(in srgb, var(--danger) 30%, var(--border));
    background: color-mix(in srgb, var(--danger) 6%, var(--surface));
    font-size: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}
.verify-card__reason strong {
    display: block;
    margin-bottom: 2px;
    color: var(--danger);
    font-size: 11px;
}

.verify-card__hint {
    margin: 10px 0 0;
    color: var(--text-muted);
    font-size: 11px;
}

/* 신청 폼 */
.verify-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 14px;
}

.verify-form__photo {
    max-width: 320px;
}

.verify-form__picker {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 130px;
    padding: 14px;
    border: 1px dashed var(--border);
    border-radius: 12px;
    background: var(--bg);
    cursor: pointer;
    text-align: center;
    transition: border-color 0.12s ease;
}
.verify-form__picker:hover {
    border-color: var(--brand);
}
.verify-form__picker input {
    display: none;
}
.verify-form__picker img {
    max-height: 160px;
    border-radius: 8px;
}
.verify-form__picker strong {
    font-size: 12px;
}
.verify-form__picker span {
    color: var(--text-muted);
    font-size: 10px;
}
.verify-form__picker .n-icon {
    color: var(--text-muted);
}

.verify-form__submit {
    align-self: flex-end;
    padding: 8px 18px;
    border: 0;
    border-radius: 999px;
    background: var(--brand);
    color: #07120e;
    font-family: inherit;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}
.verify-form__submit:disabled {
    opacity: 0.5;
    cursor: default;
}
</style>
