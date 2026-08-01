<x-layouts.app title="게시글 등록">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Board Create</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">게시글 등록</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    공통 게시판에 새 게시글을 등록하는 화면입니다.
                </p>
            </div>

            <nav class="mt-4 space-y-2">
                <a href="{{ route('dashboard.modules.boards') }}" class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]" title="게시글 목록">
                    <span>게시글 목록</span>
                    <span class="text-xs">01</span>
                </a>
                <a href="{{ route('dashboard.modules.boards.create') }}" class="flex items-center justify-between rounded-lg border border-[#cfcfcf] bg-[#ececec] px-3 py-2 text-sm text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]" title="게시글 등록">
                    <span>게시글 등록</span>
                    <span class="text-xs">02</span>
                </a>
            </nav>
        </aside>

        <div class="space-y-4">
            <section class="page-panel panel-dark">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">등록</p>
                <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">Board Create</h2>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    제목, 내용, 게시판 구분, 상태와 첨부파일까지 함께 등록합니다. 댓글은 다음 단계에서 확장합니다.
                </p>
            </section>

            <section class="page-panel">
                <form method="POST" action="{{ route('dashboard.modules.boards.store') }}" enctype="multipart/form-data">
                    @csrf

                    @include('dashboard.modules.boards.form', [
                        'attachmentFiles' => $attachmentFiles,
                        'board' => $board,
                        'statusOptions' => $statusOptions,
                        'typeLabels' => $typeLabels,
                        'typeOptions' => $typeOptions,
                    ])

                    <div class="mt-6 flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary" title="게시글 저장" aria-label="게시글 저장">저장</button>
                        <a href="{{ route('dashboard.modules.boards') }}" class="btn-secondary" title="게시글 목록">취소</a>
                    </div>
                </form>
            </section>
        </div>
    </section>
</x-layouts.app>
