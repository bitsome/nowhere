<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { getApiErrorMessage } from '../../api/client';
import { safeRedirectPath } from '../../utils/redirect';
import UiCard from '../../components/ui/UiCard.vue';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

// 공유 링크에서 넘어온 경우 로그인 후 그 운행으로 돌아간다 (내부 경로만 허용)
const redirectTo = safeRedirectPath(route.query.redirect);

const login = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');
const remember = ref(true);

// 저장된 아이디·비밀번호 불러오기
const loadSaved = () => {
    try {
        const saved = JSON.parse(localStorage.getItem('nowhere_login_saved') || 'null');

        if (saved?.login || saved?.email) {
            login.value = saved.login || saved.email;
            password.value = saved.password || '';
            remember.value = saved.remember !== false;
        }
    } catch {
        /* 저장값이 깨졌으면 무시 */
    }
};

// 로그인 화면은 뷰포트 1장이므로 window/body 스크롤을 잠근다
onMounted(() => {
    loadSaved();
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
        await auth.login(login.value, password.value);

        // 아이디·비밀번호 저장 (체크 시 localStorage에 보관)
        if (remember.value) {
            localStorage.setItem(
                'nowhere_login_saved',
                JSON.stringify({ login: login.value, password: password.value, remember: true }),
            );
        } else {
            localStorage.removeItem('nowhere_login_saved');
        }

        // 공유 링크로 온 기사는 그 운행으로, 아니면 홈으로
        router.push(redirectTo ?? { name: 'home' });
    } catch (e) {
        error.value = getApiErrorMessage(e, '로그인에 실패했습니다.');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="login-wrap">
        <UiCard :padded="false" class="login-card">
            <div class="login-head">
                <span class="login-mark">N</span>
                <h1 class="login-title">NoWhere</h1>
                <p class="login-desc">운행 마켓에 로그인하세요</p>
            </div>

            <n-alert v-if="error" type="error" :show-icon="true" class="login-alert">
                {{ error }}
            </n-alert>

            <n-form label-placement="top" size="large" @submit.prevent="submit">
                <n-form-item label="아이디(이메일) 또는 전화번호">
                    <!-- inputmode·autocomplete는 n-input의 prop이 아니라 input-props로 넘겨야 내부 <input>에 적용된다 -->
                    <n-input
                        v-model:value="login"
                        placeholder="이메일 또는 전화번호"
                        :input-props="{ autocomplete: 'email' }"
                    />
                </n-form-item>

                <n-form-item label="비밀번호">
                    <n-input
                        v-model:value="password"
                        type="password"
                        show-password-on="click"
                        placeholder="비밀번호"
                        :input-props="{ autocomplete: 'current-password' }"
                        @keyup.enter="submit"
                    />
                </n-form-item>

                <n-checkbox v-model:checked="remember" class="login-remember">
                    아이디·비밀번호 저장
                </n-checkbox>

                <n-button type="primary" attr-type="submit" block :loading="loading" class="login-submit">
                    로그인
                </n-button>
            </n-form>

            <p class="login-footer">
                계정이 없으신가요?
                <router-link
                    :to="{ name: 'register', query: redirectTo ? { redirect: redirectTo } : {} }"
                    class="login-link"
                >회원가입</router-link>
                <span class="login-footer__divider">·</span>
                <router-link
                    :to="{ name: 'password-reset', query: redirectTo ? { redirect: redirectTo } : {} }"
                    class="login-link"
                >비밀번호 찾기</router-link>
            </p>
        </UiCard>
    </div>
</template>

<style scoped>
.login-wrap {
    height: 100dvh;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow: hidden;
    background:
        radial-gradient(ellipse at top left, color-mix(in srgb, var(--brand) 12%, transparent), transparent 50%),
        radial-gradient(ellipse at bottom right, color-mix(in srgb, var(--status-accepted) 12%, transparent), transparent 50%),
        var(--bg);
}

/* 공용 UiCard에 패딩·크기 제약만 얹는다 — 표면/테두리/라운드는 UiCard가 담당 */
.login-card {
    width: 100%;
    max-width: 400px;
    margin: auto;
    padding: 36px 32px;
    /* 작은 모바일 화면에서 카드가 뷰포트를 넘으면 내부에서만 스크롤된다 */
    max-height: calc(100dvh - 40px);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
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

/* 다크 — 그라디언트가 밝은 틸로 바뀌어 흰 글자 대비가 ≈1.6:1로 떨어짐 → 앱 표준 어두운 글자 */
html.dark .login-mark {
    color: #07120e;
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

.login-footer__divider {
    margin: 0 6px;
    color: var(--border);
}
</style>
