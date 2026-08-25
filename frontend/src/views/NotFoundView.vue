<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();
</script>

<template>
    <div class="nf-wrap">
        <div class="nf-card">
            <span class="nf-code">404</span>
            <h1 class="nf-title">페이지를 찾을 수 없습니다</h1>
            <p class="nf-desc">주소가 잘못되었거나 삭제된 페이지입니다.</p>

            <div class="nf-actions">
                <n-button type="primary" @click="router.push({ name: 'home' })">
                    홈으로
                </n-button>
                <n-button @click="router.back()">
                    뒤로가기
                </n-button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.nf-wrap {
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.nf-card {
    max-width: 360px;
    width: 100%;
    text-align: center;
    padding: 32px 24px;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: var(--surface);
}

.nf-code {
    display: inline-block;
    font-size: 40px;
    font-weight: 800;
    letter-spacing: 2px;
    color: var(--text-muted);
}

.nf-title {
    margin: 8px 0 6px;
    font-size: 16px;
    font-weight: 700;
}

.nf-desc {
    margin: 0 0 20px;
    color: var(--text-muted);
    font-size: 13px;
}

.nf-actions {
    display: flex;
    justify-content: center;
    gap: 8px;
}
</style>
