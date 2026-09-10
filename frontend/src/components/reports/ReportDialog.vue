<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useMessage } from 'naive-ui';
import { apiCreateReport, apiReportOptions } from '../../api/reports';
import { getApiErrorMessage } from '../../api/client';
import BaseIcon from '../common/BaseIcon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    // 신고 대상 — order(운행) | user(기사·등록자) | chat(채팅)
    targetType: { type: String, required: true },
    targetId: { type: Number, required: true },
    // 화면에 보여줄 대상 설명 — 운행 노선·사용자 이름 등
    subjectText: { type: String, default: '' },
});

const emit = defineEmits(['update:show', 'submitted']);

const message = useMessage();

const targetLabel = computed(() => options.value?.targets?.[props.targetType] ?? '운행');

// 대상별 신고 유형 — 백엔드 단일 소스 (GET /reports/options)
const options = ref(null);
const category = ref(null);
const reason = ref('');
const submitting = ref(false);

onMounted(async () => {
    try {
        const { data } = await apiReportOptions();
        options.value = data.data;
    } catch {
        // 옵션 로드 실패 시 비어 있게 유지 — 전송 시점에 서버 검증이 안내한다
        options.value = { targets: {}, categories: {}, statuses: {} };
    }
});

const categories = computed(() => options.value?.categories?.[props.targetType] ?? {});

const canSubmit = computed(() => category.value && reason.value.trim().length >= 5 && !submitting.value);

// 모달이 열릴 때마다 이전 입력을 비운다
watch(
    () => props.show,
    (open) => {
        if (open) {
            category.value = null;
            reason.value = '';
        }
    },
);

const submit = async () => {
    if (!category.value || reason.value.trim().length < 5) {
        return;
    }
    submitting.value = true;

    try {
        await apiCreateReport({
            target_type: props.targetType,
            target_id: props.targetId,
            category: category.value,
            reason: reason.value.trim(),
        });
        emit('submitted');
        emit('update:show', false);
        message.success('신고가 접수되었습니다. 처리 결과는 알림으로 알려 드려요.');
    } catch (e) {
        message.error(getApiErrorMessage(e, '신고 접수에 실패했습니다.'));
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <n-modal
        :show="show"
        preset="card"
        class="report-modal"
        :style="{ width: 'min(92vw, 420px)' }"
        :title="`신고하기 · ${targetLabel}`"
        :mask-closable="!submitting"
        :close-on-esc="!submitting"
        @update:show="emit('update:show', $event)"
    >
        <!-- 신고 대상 — 어느 화면에서 열었는지 맥락을 보여준다 -->
        <div v-if="subjectText" class="report-subject">
            <BaseIcon name="warning" :size="14" />
            <span>{{ subjectText }}</span>
        </div>

        <p class="report-desc">무엇 때문에 신고하시나요? 아래 유형 중 골라 주세요.</p>

        <div class="report-categories">
            <label
                v-for="(label, key) in categories"
                :key="key"
                class="report-category"
                :class="{ 'report-category--active': category === key }"
            >
                <input v-model="category" type="radio" :value="key" />
                <span>{{ label }}</span>
            </label>
        </div>

        <p class="report-desc">신고 내용을 자세히 적어 주세요. (최소 5자)</p>
        <n-input
            v-model:value="reason"
            type="textarea"
            placeholder="예) 예정 시간보다 2시간 늦게 출발했고 사과도 없었습니다."
            :rows="4"
            :maxlength="2000"
            :show-count="false"
            class="report-reason"
        />

        <p class="report-notice">
            신고 내용은 운영팀이 순서대로 확인해요. 허위 신고는 서비스 이용에 제한이 생길 수 있어요.
        </p>

        <template #footer>
            <div class="report-footer">
                <n-button :disabled="submitting" @click="emit('update:show', false)">취소</n-button>
                <n-button
                    class="report-footer__submit"
                    :loading="submitting"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    신고 접수
                </n-button>
            </div>
        </template>
    </n-modal>
</template>

<style scoped>
/* 신고 다이얼로그 — 문의가 아니라 '문제'이므로 접수 버튼은 레드로 의미를 준다 */
.report-modal :deep(.n-card-header__main) {
    font-size: 14px;
    font-weight: 800;
}

.report-subject {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
    padding: 8px 10px;
    border-radius: 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    font-size: 11px;
    font-weight: 600;
    word-break: break-word;
}
.report-subject svg {
    flex-shrink: 0;
    color: var(--danger);
}

.report-desc {
    margin: 0 0 8px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}
.report-desc:not(:first-child) {
    margin-top: 14px;
}

/* 유형 선택 — 가로 칩으로 늘어나지 않고 세로 목록으로 한 눈에 */
.report-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.report-category {
    cursor: pointer;
    user-select: none;
}
.report-category input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.report-category span {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 500;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}
.report-category--active span {
    border-color: var(--danger);
    background: color-mix(in srgb, var(--danger) 8%, transparent);
    color: var(--danger);
    font-weight: 700;
}

.report-reason textarea {
    font-size: 12px;
}

.report-notice {
    margin: 10px 0 0;
    color: var(--text-muted);
    font-size: 10px;
    line-height: 1.5;
}

.report-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
.report-footer__submit {
    background: var(--danger);
    border-color: var(--danger);
    color: #ffffff;
}
</style>
