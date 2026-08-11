<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { getApiErrorMessage } from '../api/client';

const router = useRouter();
const auth = useAuthStore();

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

// 로그인 화면은 뷰포트 1장이므로 window/body 스크롤을 잠근다
onMounted(() => {
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
});

onBeforeUnmount(() => {
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
});

const submit = async () => {
    loading.value = true;
    error.value = '';

    try {
        await auth.login(email.value, password.value);
        router.push({ name: 'market' });
    } catch (e) {
        error.value = getApiErrorMessage(e, '로그인에 실패했습니다.');
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
                <h1 class="login-title">NoWhere</h1>
                <p class="login-desc">오더 마켓에 로그인하세요</p>
            </div>

            <n-alert v-if="error" type="error" :show-icon="true" class="login-alert">
                {{ error }}
            </n-alert>

            <n-form label-placement="top" size="large" @submit.prevent="submit">
                <n-form-item label="이메일">
                    <n-input
                        v-model:value="email"
                        type="email"
                        placeholder="email@example.com"
                        autocomplete="email"
                    />
                </n-form-item>

                <n-form-item label="비밀번호">
                    <n-input
                        v-model:value="password"
                        type="password"
                        show-password-on="click"
                        placeholder="비밀번호"
                        autocomplete="current-password"
                        @keyup.enter="submit"
                    />
                </n-form-item>

                <n-button type="primary" attr-type="submit" block :loading="loading" class="login-submit">
                    로그인
                </n-button>
            </n-form>
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
        radial-gradient(ellipse at top left, rgba(54, 173, 255, 0.12), transparent 50%),
        radial-gradient(ellipse at bottom right, rgba(47, 84, 235, 0.12), transparent 50%),
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
    background: linear-gradient(135deg, #36adff, #2f54eb);
    color: #ffffff;
    font-size: 24px;
    font-weight: 700;
    box-shadow: 0 6px 18px rgba(54, 173, 255, 0.35);
}

.login-title {
    margin: 16px 0 0;
    font-size: 24px;
    font-weight: 700;
}

.login-desc {
    margin: 6px 0 0;
    color: var(--text-muted);
    font-size: 14px;
}

.login-alert {
    margin-bottom: 16px;
}

.login-submit {
    margin-top: 8px;
    height: 44px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
}
</style>
