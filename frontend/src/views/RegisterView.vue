<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { getApiErrorMessage } from '../api/client';

const router = useRouter();
const auth = useAuthStore();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirm = ref('');
const loading = ref(false);
const error = ref('');
const agree = ref(false);

// 가입 화면도 로그인과 동일하게 뷰포트 1장 — window/body 스크롤 잠금
onMounted(() => {
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
});

onBeforeUnmount(() => {
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
});

const submit = async () => {
    error.value = '';

    if (!name.value.trim()) {
        error.value = '이름을 입력해 주세요.';
        return;
    }

    if (password.value.length < 8) {
        error.value = '비밀번호는 8자 이상이어야 합니다.';
        return;
    }

    if (password.value !== passwordConfirm.value) {
        error.value = '비밀번호가 일치하지 않습니다.';
        return;
    }

    if (!agree.value) {
        error.value = '이용약관에 동의해 주세요.';
        return;
    }

    loading.value = true;

    try {
        await auth.register(name.value.trim(), email.value.trim(), password.value);
        router.push({ name: 'home' });
    } catch (e) {
        error.value = getApiErrorMessage(e, '가입에 실패했습니다.');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-head">
                <span class="login-mark">N</span>
                <h1 class="login-title">회원가입</h1>
                <p class="login-desc">NoWhere 운행 마켓에 가입하세요</p>
            </div>

            <n-alert v-if="error" type="error" :show-icon="true" class="login-alert">
                {{ error }}
            </n-alert>

            <n-form label-placement="top" size="large" @submit.prevent="submit">
                <n-form-item label="이름">
                    <n-input
                        v-model:value="name"
                        placeholder="이름"
                        autocomplete="name"
                        maxlength="50"
                    />
                </n-form-item>

                <n-form-item label="이메일">
                    <n-input
                        v-model:value="email"
                        placeholder="이메일"
                        autocomplete="email"
                    />
                </n-form-item>

                <n-form-item label="비밀번호 (8자 이상)">
                    <n-input
                        v-model:value="password"
                        type="password"
                        show-password-on="click"
                        placeholder="비밀번호"
                        autocomplete="new-password"
                    />
                </n-form-item>

                <n-form-item label="비밀번호 확인">
                    <n-input
                        v-model:value="passwordConfirm"
                        type="password"
                        show-password-on="click"
                        placeholder="비밀번호 확인"
                        autocomplete="new-password"
                        @keyup.enter="submit"
                    />
                </n-form-item>

                <n-checkbox v-model:checked="agree" class="login-remember">
                    이용약관 및 개인정보 처리방침에 동의합니다
                </n-checkbox>

                <n-button type="primary" attr-type="submit" block :loading="loading" class="login-submit">
                    가입하기
                </n-button>
            </n-form>

            <p class="login-footer">
                이미 계정이 있으신가요?
                <router-link :to="{ name: 'login' }" class="login-link">로그인</router-link>
            </p>
        </div>
    </div>
</template>

<style scoped>
.login-wrap {
    height: 100dvh;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    overflow: hidden;
    background:
        radial-gradient(ellipse at top left, color-mix(in srgb, var(--brand) 12%, transparent), transparent 50%),
        radial-gradient(ellipse at bottom right, color-mix(in srgb, var(--status-accepted) 12%, transparent), transparent 50%),
        var(--bg);
}

.login-card {
    width: 100%;
    max-width: 400px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 36px 32px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
}

.login-head {
    text-align: center;
    margin-bottom: 24px;
}

.login-mark {
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

.login-title {
    margin: 16px 0 0;
    font-size: 20px;
    font-weight: 700;
}

.login-desc {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-size: 11px;
}

.login-alert {
    margin-bottom: 16px;
}

.login-remember {
    margin: 2px 0 14px;
}

.login-submit {
    margin-top: 8px;
    height: 44px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.login-footer {
    margin: 20px 0 0;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    text-align: center;
    color: var(--text-muted);
    font-size: 11px;
}

.login-link {
    color: var(--brand);
    font-weight: 700;
    text-decoration: none;
}
</style>
