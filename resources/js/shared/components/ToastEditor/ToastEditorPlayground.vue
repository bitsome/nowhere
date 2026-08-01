<script setup>
import { computed, ref } from 'vue';

import BaseButton from '../Button/BaseButton.vue';
import BaseMarkdownEditor from '../Form/BaseMarkdownEditor.vue';
import ToastViewer from './ToastViewer.vue';

const defaultMarkdown = `# 공지사항

## 업데이트

- 배차 모듈 준비
- 공통 목록 구조 재사용
- 파일 모듈 연동 유지

### 안내

게시판, 공지사항, FAQ, 문의, 운영 매뉴얼은 모두 공통 Editor / Viewer 기준으로 확장합니다.

\`\`\`php
echo "NoWhere";
\`\`\`
`;

const markdown = ref(defaultMarkdown);
const imageHelpMessage = ref('공통 BaseMarkdownEditor는 ToastEditor 기반 입력 모듈로 재사용합니다.');

const lineCount = computed(() => {
    if (markdown.value === '') {
        return 0;
    }

    return markdown.value.split('\n').length;
});

const textLength = computed(() => {
    return markdown.value.length;
});

function clearMarkdown() {
    markdown.value = '';
}

function resetMarkdown() {
    markdown.value = defaultMarkdown;
    imageHelpMessage.value = '기본 샘플 본문을 다시 불러왔습니다.';
}
</script>

<template>
    <div class="space-y-4">
        <section class="rounded-[10px] border border-[#d8d8d8] bg-[#efefef] px-4 py-3 dark:border-[#343434] dark:bg-[#202020]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[#1f1f1f] dark:text-[#f3f3f3]">실행형 Editor 테스트</p>
                    <p class="mt-1 text-sm text-[#6a6a6a] dark:text-[#9ea1a8]">Markdown {{ textLength }}자 · {{ lineCount }}줄</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <BaseButton
                        size="sm"
                        title="샘플 복원"
                        aria-label="샘플 복원"
                        variant="secondary"
                        @click="resetMarkdown"
                    >
                        샘플 복원
                    </BaseButton>

                    <BaseButton
                        size="sm"
                        title="내용 비우기"
                        aria-label="내용 비우기"
                        variant="secondary"
                        @click="clearMarkdown"
                    >
                        내용 비우기
                    </BaseButton>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <section class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Editor</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">Toast UI Editor</h3>
                    </div>
                    <span class="status-badge">Markdown</span>
                </div>

                <div class="mt-4">
                    <BaseMarkdownEditor
                        v-model="markdown"
                        height="520px"
                        input-name="content"
                        placeholder="문서 내용을 입력하세요."
                    />
                </div>
            </section>

            <div class="space-y-4">
                <section class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Viewer</p>
                            <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">Toast UI Viewer</h3>
                        </div>
                        <span class="status-badge">Preview</span>
                    </div>

                    <div class="mt-4">
                        <ToastViewer :content="markdown" />
                    </div>
                </section>

                <section class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Raw Markdown</p>
                            <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">저장 문자열</h3>
                        </div>
                        <span class="status-badge">Sync</span>
                    </div>

                    <pre class="mt-4 max-h-[220px] overflow-auto rounded-[10px] border border-[#d8d8d8] bg-[#efefef] p-3 text-sm leading-6 text-[#4f4f4f] dark:border-[#343434] dark:bg-[#202020] dark:text-[#b9bbc0]">{{ markdown }}</pre>
                </section>
            </div>
        </div>

        <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
            {{ imageHelpMessage }}
        </p>
    </div>
</template>
