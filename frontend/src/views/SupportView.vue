<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useMessage } from 'naive-ui';
import { getApiErrorMessage } from '../api/client';
import {
    apiCreateSupportTicket,
    apiMySupportTickets,
    apiSupportPosts,
} from '../api/support';
import BaseIcon from '../components/common/BaseIcon.vue';
import EmptyState from '../components/common/EmptyState.vue';

// keep-alive 캐시 매칭용 이름
defineOptions({ name: 'SupportView' });

const router = useRouter();
const message = useMessage();

const tab = ref('notice');

// 공지·FAQ — 펼침 상태(아코디언)
const notices = ref([]);
const faqs = ref([]);
const postsLoading = ref(true);
const openedPostId = ref(null);

const loadPosts = async () => {
    postsLoading.value = true;

    try {
        const [{ data: noticeData }, { data: faqData }] = await Promise.all([
            apiSupportPosts({ kind: 'notice' }),
            apiSupportPosts({ kind: 'faq' }),
        ]);
        notices.value = noticeData.data;
        faqs.value = faqData.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '공지·FAQ를 불러오지 못했습니다.'));
    } finally {
        postsLoading.value = false;
    }
};

// 1:1 문의
const tickets = ref([]);
const ticketsLoading = ref(true);
const ticketForm = ref({ title: '', body: '' });
const submitting = ref(false);

const loadMyTickets = async () => {
    ticketsLoading.value = true;

    try {
        const { data } = await apiMySupportTickets();
        tickets.value = data.data;
    } catch (e) {
        message.error(getApiErrorMessage(e, '내 문의를 불러오지 못했습니다.'));
    } finally {
        ticketsLoading.value = false;
    }
};

const submitTicket = async () => {
    const title = ticketForm.value.title.trim();
    const body = ticketForm.value.body.trim();

    if (!title) {
        message.warning('문의 제목을 입력해 주세요.');

        return;
    }
    if (!body) {
        message.warning('문의 내용을 입력해 주세요.');

        return;
    }
    submitting.value = true;

    try {
        await apiCreateSupportTicket({ title, body });
        ticketForm.value.title = '';
        ticketForm.value.body = '';
        message.success('문의가 접수되었습니다. 답변은 알림으로 알려 드릴게요.');
        await loadMyTickets();
    } catch (e) {
        message.error(getApiErrorMessage(e, '문의 접수에 실패했습니다.'));
    } finally {
        submitting.value = false;
    }
};

const onTabChange = (name) => {
    tab.value = name;

    if (name === 'ticket') {
        loadMyTickets();
    }
};

onMounted(() => {
    loadPosts();
});
</script>

<template>
    <div class="support-page page-shell">
        <button type="button" class="support-back" @click="router.back()"><BaseIcon name="arrow-back" :size="16" /> 뒤로</button>

        <div class="page-head">
            <div>
                <h1 class="page-head__title">고객지원</h1>
                <p class="page-head__desc">공지·자주 묻는 질문을 확인하고, 해결되지 않는 문제는 1:1 문의를 남겨 주세요.</p>
            </div>
        </div>

        <n-tabs v-model:value="tab" type="segment" animated size="small" @update:value="onTabChange">
            <!-- 공지 -->
            <n-tab-pane name="notice" tab="공지">
                <div v-if="postsLoading" class="support-skeleton">
                    <div v-for="n in 3" :key="n" class="support-card">
                        <div class="sk-line" style="width: 60%" />
                    </div>
                </div>

                <EmptyState
                    v-else-if="notices.length === 0"
                    icon="inbox"
                    title="등록된 공지가 없습니다"
                    hint="새 공지가 올라오면 이곳에 표시됩니다"
                />

                <div v-else class="support-list">
                    <article v-for="post in notices" :key="post.id" class="support-card">
                        <button type="button" class="support-card__head" @click="openedPostId = openedPostId === post.id ? null : post.id">
                            <strong>{{ post.title }}</strong>
                            <span class="support-card__date">{{ post.created_at }}</span>
                            <BaseIcon name="chevron-down" :size="14" class="support-card__chevron" :class="{ 'support-card__chevron--open': openedPostId === post.id }" />
                        </button>
                        <p v-if="openedPostId === post.id" class="support-card__body">{{ post.body }}</p>
                    </article>
                </div>
            </n-tab-pane>

            <!-- FAQ -->
            <n-tab-pane name="faq" tab="FAQ">
                <div v-if="postsLoading" class="support-skeleton">
                    <div v-for="n in 3" :key="n" class="support-card">
                        <div class="sk-line" style="width: 60%" />
                    </div>
                </div>

                <EmptyState
                    v-else-if="faqs.length === 0"
                    icon="inbox"
                    title="등록된 FAQ가 없습니다"
                    hint="자주 묻는 질문이 올라오면 이곳에 표시됩니다"
                />

                <div v-else class="support-list">
                    <article v-for="post in faqs" :key="post.id" class="support-card">
                        <button type="button" class="support-card__head" @click="openedPostId = openedPostId === post.id ? null : post.id">
                            <strong>Q. {{ post.title }}</strong>
                            <BaseIcon name="chevron-down" :size="14" class="support-card__chevron" :class="{ 'support-card__chevron--open': openedPostId === post.id }" />
                        </button>
                        <div v-if="openedPostId === post.id" class="support-card__body support-card__body--faq">A. {{ post.body }}</div>
                    </article>
                </div>
            </n-tab-pane>

            <!-- 1:1 문의 -->
            <n-tab-pane name="ticket" tab="1:1 문의">
                <form class="support-ticket" @submit.prevent="submitTicket">
                    <div class="support-ticket__field">
                        <label class="support-ticket__label">제목</label>
                        <n-input
                            v-model:value="ticketForm.title"
                            :maxlength="200"
                            placeholder="문의 제목을 간단히 적어 주세요."
                        />
                    </div>
                    <div class="support-ticket__field">
                        <label class="support-ticket__label">내용</label>
                        <n-input
                            v-model:value="ticketForm.body"
                            type="textarea"
                            :rows="4"
                            :maxlength="5000"
                            placeholder="어떤 문제인지 구체적으로 적어 주세요. (운행 번호·날짜 포함 시 확인이 빠릅니다)"
                        />
                    </div>
                    <button type="submit" class="support-ticket__submit" :disabled="submitting">
                        {{ submitting ? '접수 중...' : '문의 보내기' }}
                    </button>
                </form>

                <p class="support-section-title">내 문의</p>

                <div v-if="ticketsLoading" class="support-skeleton">
                    <div v-for="n in 2" :key="n" class="support-card">
                        <div class="sk-line" style="width: 60%" />
                    </div>
                </div>

                <EmptyState
                    v-else-if="tickets.length === 0"
                    icon="inbox"
                    title="문의 내역이 없습니다"
                    hint="위에서 문의를 남기면 이곳에서 답변을 확인할 수 있습니다"
                />

                <div v-else class="support-list">
                    <article v-for="ticket in tickets" :key="ticket.id" class="support-card support-ticket-item">
                        <header class="support-ticket-item__head">
                            <strong>{{ ticket.title }}</strong>
                            <span class="support-ticket-item__status" :class="`support-ticket-item__status--${ticket.status}`">
                                {{ ticket.status_label }}
                            </span>
                        </header>
                        <p class="support-ticket-item__body">{{ ticket.body }}</p>
                        <p class="support-ticket-item__meta">{{ ticket.created_at }}</p>
                        <div v-if="ticket.answer" class="support-ticket-item__answer">
                            <strong>운영팀 답변</strong>
                            <p>{{ ticket.answer }}</p>
                            <span>{{ ticket.answerer_name ?? '' }} · {{ ticket.answered_at }}</span>
                        </div>
                    </article>
                </div>
            </n-tab-pane>
        </n-tabs>
    </div>
</template>

<style scoped>
.support-page {
    padding-bottom: 24px;
}

.support-back {
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

.support-back:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

/* 공지/FAQ 카드 */
.support-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.support-card {
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}

.support-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.support-card__head {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 0;
    border: 0;
    background: none;
    font-family: inherit;
    color: var(--text);
    text-align: left;
    cursor: pointer;
}

.support-card__head strong {
    flex: 1;
    min-width: 0;
    font-size: 12px;
    font-weight: 800;
    line-height: 1.5;
}

.support-card__date {
    flex-shrink: 0;
    color: var(--text-muted);
    font-size: 10px;
}

.support-card__chevron {
    flex-shrink: 0;
    color: var(--text-muted);
    transition: transform 0.15s ease;
}

.support-card__chevron--open {
    transform: rotate(180deg);
}

.support-card__body {
    margin: 10px 0 0;
    padding: 12px;
    border-radius: 10px;
    background: var(--bg);
    font-size: 12px;
    line-height: 1.7;
    white-space: pre-wrap;
    word-break: break-word;
}

.support-card__body--faq {
    background: color-mix(in srgb, var(--brand) 6%, var(--surface));
}

/* 1:1 문의 작성 */
.support-ticket {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: var(--card-pad);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    background: var(--surface);
}

.support-ticket__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.support-ticket__label {
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
}

.support-ticket__submit {
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

.support-ticket__submit:disabled {
    opacity: 0.5;
    cursor: default;
}

.support-section-title {
    margin: 18px 0 8px;
    font-size: 12px;
    font-weight: 800;
}

/* 내 문의 목록 */
.support-ticket-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.support-ticket-item__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.support-ticket-item__head strong {
    font-size: 12px;
    font-weight: 800;
}

.support-ticket-item__status {
    flex-shrink: 0;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
}
.support-ticket-item__status--open {
    background: #f0a800;
    color: #101418;
}
.support-ticket-item__status--answered {
    background: var(--status-completed);
    color: #101418;
}

.support-ticket-item__body {
    margin: 0;
    font-size: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}

.support-ticket-item__meta {
    margin: 0;
    color: var(--text-muted);
    font-size: 10px;
}

.support-ticket-item__answer {
    margin-top: 4px;
    padding: 10px 12px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--brand) 6%, var(--surface));
    border: 1px solid color-mix(in srgb, var(--brand) 22%, var(--border));
}

.support-ticket-item__answer strong {
    display: block;
    color: var(--text-muted);
    font-size: 10px;
}

.support-ticket-item__answer p {
    margin: 4px 0 0;
    font-size: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}

.support-ticket-item__answer span {
    display: block;
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 10px;
}
</style>
