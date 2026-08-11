<x-layouts.app title="게시글 상세">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Board Detail</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $typeLabels[$board->type] ?? $board->type }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    게시글 제목, 작성자, 작성일, 조회수, 내용까지 확인하는 게시글 상세 화면입니다.
                </p>
            </div>

            <nav class="mt-4 space-y-2">
                <a href="{{ route('dashboard.business.boards') }}" class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]" title="게시글 목록">
                    <span>게시글 목록</span>
                    <span class="text-xs">01</span>
                </a>
                <a href="{{ route('dashboard.business.boards.show', $board) }}" class="flex items-center justify-between rounded-lg border border-[#cfcfcf] bg-[#ececec] px-3 py-2 text-sm text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]" title="게시글 상세">
                    <span>게시글 상세</span>
                    <span class="text-xs">02</span>
                </a>
                @if ($canUpdate)
                    <a href="{{ route('dashboard.business.boards.edit', $board) }}" class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]" title="게시글 수정">
                        <span>게시글 수정</span>
                        <span class="text-xs">03</span>
                    </a>
                @endif
            </nav>
        </aside>

        <div class="space-y-4">
            <section class="grid gap-4 xl:grid-cols-[1fr_0.8fr]">
                <div class="page-panel panel-dark">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">제목</p>
                    <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $board->title }}</h2>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="status-badge">{{ $typeLabels[$board->type] ?? $board->type }}</span>
                        <span class="status-badge">{{ $statusOptions[$board->status] ?? $board->status }}</span>
                        @if ($board->is_private)
                            <span class="status-badge">비공개</span>
                        @endif
                    </div>
                </div>

                <aside class="page-panel panel-gray">
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">게시글 정보</p>
                    <dl class="mt-4 space-y-3">
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">작성자</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $board->user?->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">작성일</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $board->created_at?->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">조회수</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ number_format($board->view_count) }}</dd>
                        </div>
                    </dl>
                </aside>
            </section>

            <section class="page-panel">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">내용</p>
                <div class="mt-4" data-toast-viewer-field>
                    <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-5 text-sm leading-7 text-gray-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-100">
                        {!! nl2br(e($board->content)) !!}
                    </div>
                    <textarea hidden data-toast-viewer-source>{{ $board->content }}</textarea>
                </div>
            </section>

            <section class="page-panel">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">첨부파일</p>
                        <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">게시글 파일</h3>
                    </div>
                    <span class="status-badge">{{ $attachmentFiles->count() }}개</span>
                </div>

                @if ($attachmentFiles->isEmpty())
                    <div class="mt-4 rounded-[10px] border border-dashed border-[#d8d8d8] bg-[#f7f7f7] px-4 py-5 text-sm text-gray-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-400">
                        첨부된 파일이 없습니다.
                    </div>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($attachmentFiles as $attachmentFile)
                            <article class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $attachmentFile->name }}</p>
                                    <p class="mt-1 truncate text-sm leading-6 text-gray-500 dark:text-gray-400">
                                        {{ $attachmentFile->file_name }} / {{ number_format($attachmentFile->size / 1024, 1) }} KB
                                    </p>
                                </div>

                                <a
                                    href="{{ route('dashboard.business.files.download', $attachmentFile) }}"
                                    class="btn-secondary"
                                    title="첨부파일 다운로드"
                                    aria-label="첨부파일 다운로드"
                                >
                                    다운로드
                                </a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('dashboard.business.boards') }}" class="btn-secondary" title="게시글 목록">목록</a>
                    @if ($canUpdate)
                        <a href="{{ route('dashboard.business.boards.edit', $board) }}" class="btn-primary" title="게시글 수정">수정</a>
                    @endif
                    @if ($canDelete)
                        <form method="POST" action="{{ route('dashboard.business.boards.destroy', $board) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-secondary" title="게시글 삭제" aria-label="게시글 삭제">삭제</button>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
