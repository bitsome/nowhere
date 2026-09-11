<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getApiErrorMessage } from '../../api/client';
import { apiForgotPassword, apiResetPassword } from '../../api/auth';
import { safeRedirectPath } from '../../utils/redirect';
import UiCard from '../../components/ui/UiCard.vue';

const router = useRouter();
const route = useRoute();

// 공유 링크에서 온 경우 재설정 뒤 로그인을 거쳐 그 운행으로 돌아가도록 경로를 이어 준다
const redirectTo = safeRedirectPath(route.query.redirect);

const email = ref('');
const code = ref('');
const password = ref('');
const confirm = ref('');

// 1단계(이메일) → 2단계(인증코드+새 비밀번호) 진행 상태
const sent = ref(false);
const loading = ref(false);
const error = ref('');
const info = ref('');

// 1단계 — 인증코드 발송
const sendCode = async () => {
    if (!email.value.trim()) {
        error.value = '이메일을 입력해 주세요.';
        return;
    }

    loading.value = true;
    error.value = '';
    info.value = '';

    try {
        await apiForgotPassword(email.value.trim());

        sent.value = true;
        info.value = '인증코드가 담긴 이메일을 보냈습니다. 메일함(스팸 포함)을 확인해 주세요.';
    } catch (e) {
        error.value = getApiErrorMessage(e, '인증코드 발송에 실패했습니다. 잠시 후 다시 시도해 주세요.');
    } finally {
        loading.value = false;
    }
};

// 2단계 — 코드 확인 후 새 비밀번호로 변경
const submitReset = async () => {
    if (!email.value.trim() || !code.value.trim() || !password.value) {
        error.value = '이메일·인증코드·새 비밀번호를 모두 입력해 주세요.';
        return;
    }

    if (password.value !== confirm.value) {
        error.value = '비밀번호 확인이 일치하지 않습니다.';
        return;
    }

    loading.value = true;
    error.value = '';
    info.value = '';

    try {
        await apiResetPassword({
            email: email.value.trim(),
            code: code.value.trim(),
            password: password.value,
            password_confirmation: confirm.value,
        });

        // 완료 후 로그인 화면으로 이동해 새 비밀번호로 로그인을 유도한다
        router.push({ name: 'login', query: redirectTo ? { redirect: redirectTo } : {} });
    } catch (e) {
        error.value = getApiErrorMessage(e, '비밀번호 변경에 실패했습니다. 인증코드를 확인해 주세요.');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="reset-wrap">
        <UiCard :padded="false" class="reset-card">
            <div class="reset-head">
                <span class="reset-mark">N</span>
                <h1 class="reset-title">비밀번호 찾기</h1>
                <p class="reset-desc">
                    {{ sent ? '인증코드를 확인해 새 비밀번호를 설정하세요' : '가입한 이메일로 인증코드를 보내 드려요' }}
                </p>
            </div>

            <n-alert v-if="error" type="error" :show-icon="true" class="reset-alert">
                {{ error }}
            </n-alert>
            <n-alert v-if="info" type="success" :show-icon="true" class="reset-alert">
                {{ info }}
            </n-alert>

            <!-- 1단계 — 이메일 입력 -->
            <n-form v-if="!sent" label-placement="top" size="large" @submit.prevent="sendCode">
                <n-form-item label="가입 이메일">
                    <!-- inputmode·autocomplete는 n-input의 prop이 아니라 input-props로 넘겨야 내부 <input>에 적용된다 -->
                    <n-input
                        v-model:value="email"
                        placeholder="name@example.com"
                        :input-props="{ autocomplete: 'email' }"
                        @keyup.enter="sendCode"
                    />
                </n-form-item>

                <n-button type="primary" attr-type="submit" block :loading="loading" class="reset-submit">
                    인증코드 보내기
                </n-button>
            </n-form>

            <!-- 2단계 — 인증코드 + 새 비밀번호 -->
            <n-form v-else label-placement="top" size="large" @submit.prevent="submitReset">
                <n-form-item label="인증코드 (6자리)">
                    <n-input
                        v-model:value="code"
                        placeholder="123456"
                        :input-props="{ inputmode: 'numeric' }"
                        maxlength="6"
                        @keyup.enter="submitReset"
                    />
                </n-form-item>

                <n-form-item label="새 비밀번호 (8자 이상)">
                    <n-input
                        v-model:value="password"
                        type="password"
                        show-password-on="click"
                        placeholder="새 비밀번호"
                        :input-props="{ autocomplete: 'new-password' }"
                    />
                </n-form-item>

                <n-form-item label="새 비밀번호 확인">
                    <n-input
                        v-model:value="confirm"
                        type="password"
                        show-password-on="click"
                        placeholder="새 비밀번호 확인"
                        :input-props="{ autocomplete: 'new-password' }"
                        @keyup.enter="submitReset"
                    />
                </n-form-item>

                <n-button type="primary" attr-type="submit" block :loading="loading" class="reset-submit">
                    비밀번호 변경
                </n-button>

                <button type="button" class="reset-resend" :disabled="loading" @click="sendCode">
                    인증코드 다시 받기
                </button>
            </n-form>

            <p class="reset-footer">
                <router-link
                    :to="{ name: 'login', query: redirectTo ? { redirect: redirectTo } : {} }"
                    class="reset-link"
                >로그인으로 돌아가기</router-link>
            </p>
        </UiCard>
    </div>
</template>

<style scoped>
.reset-wrap {
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background:
        radial-gradient(ellipse at top left, color-mix(in srgb, var(--brand) 12%, transparent), transparent 50%),
        radial-gradient(ellipse at bottom right, color-mix(in srgb, var(--status-accepted) 12%, transparent), transparent 50%),
        var(--bg);
}

.reset-card {
    width: 100%;
    max-width: 400px;
    margin: auto;
    padding: 36px 32px;
    max-height: calc(100dvh - 40px);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.reset-head {
    text-align: center;
    margin-bottom: 24px;
}

.reset-mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: var(--brand-gradient);
    color: #ffffff;
    font-size: 20px;
    font-weight: 700;
    box-shadow: 0 6px 18px color-mix(in srgb, var(--brand) 35%, transparent);
}

/* 다크 — 그라디언트가 밝은 틸로 바뀌어 흰 글자 대비가 ≈1.6:1로 떨어짐 → 앱 표준 어두운 글자 */
html.dark .reset-mark {
    color: #07120e;
}

.reset-title {
    margin: 16px 0 0;
    font-size: 20px;
    font-weight: 700;
}

.reset-desc {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-size: 11px;
}

.reset-alert {
    margin-bottom: 16px;
}

.reset-submit {
    margin-top: 8px;
    height: 44px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.reset-resend {
    display: block;
    margin: 14px auto 0;
    padding: 0;
    border: none;
    background: none;
    color: var(--text-muted);
    font-size: 11px;
    font-family: inherit;
    cursor: pointer;
    text-decoration: underline;
}

.reset-resend:hover {
    color: var(--brand);
}

.reset-footer {
    margin: 20px 0 0;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    text-align: center;
    color: var(--text-muted);
    font-size: 11px;
}

.reset-link {
    color: var(--brand);
    font-weight: 700;
    text-decoration: none;
}
</style>
