@php
    $attachmentFiles = $attachmentFiles ?? collect();
    $typeLabels = $typeLabels ?? [];
    $typeOptions = $typeOptions ?? [];
    $statusOptions = $statusOptions ?? [];
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label for="type" class="text-sm font-medium text-gray-900 dark:text-gray-100">게시판 구분</label>
        <select id="type" name="type" title="게시판 구분" class="input-field mt-2">
            @foreach ($typeOptions as $typeOption)
                <option value="{{ $typeOption }}" @selected(old('type', $board->type) === $typeOption)>{{ $typeLabels[$typeOption] ?? $typeOption }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="status" class="text-sm font-medium text-gray-900 dark:text-gray-100">상태</label>
        <select id="status" name="status" title="상태" class="input-field mt-2">
            @foreach ($statusOptions as $statusKey => $statusLabel)
                <option value="{{ $statusKey }}" @selected(old('status', $board->status) === $statusKey)>{{ $statusLabel }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-4">
    <label for="title" class="text-sm font-medium text-gray-900 dark:text-gray-100">제목</label>
    <input
        id="title"
        name="title"
        type="text"
        title="제목"
        class="input-field mt-2"
        placeholder="게시글 제목을 입력하세요"
        value="{{ old('title', $board->title) }}"
        required
    >
</div>

<div class="mt-4">
    <label for="content" class="text-sm font-medium text-gray-900 dark:text-gray-100">내용</label>
    <div
        class="mt-2"
        data-toast-editor-field
        data-allow-images="true"
        data-height="520px"
        data-input-id="content"
        data-input-name="content"
        data-library-url="{{ route('dashboard.modules.files.library') }}"
        data-placeholder="게시글 내용을 입력하세요"
        data-upload-url="{{ route('dashboard.modules.files.store') }}"
    >
        <textarea
            id="content"
            name="content"
            title="내용"
            class="mt-2 block min-h-[260px] w-full rounded-lg border border-[#d6d6d6] bg-[#f5f5f5] px-4 py-3 text-[#1f1f1f] placeholder:text-[#8b8b8b] focus:border-[#b9b9b9] focus:outline-none dark:border-[#2a2a2a] dark:bg-[#141414] dark:text-[#d6d6dd] dark:placeholder:text-[#6d6d6d] dark:focus:border-[#3a3a3a]"
            placeholder="게시글 내용을 입력하세요"
            required
            data-toast-editor-source
        >{{ old('content', $board->content) }}</textarea>
    </div>
    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
        게시글 본문은 공통 ToastEditor 기준으로 작성하며, NoWhere 이미지 버튼은 File Manager 모달을 열어 다중 선택 이미지를 Markdown으로 삽입합니다.
    </p>
</div>

<div class="mt-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
    <input type="hidden" name="is_private" value="0">
    <label class="flex items-center gap-3 text-sm text-gray-900 dark:text-gray-100">
        <input
            type="checkbox"
            name="is_private"
            value="1"
            title="비공개 게시글"
            class="h-4 w-4"
            @checked((bool) old('is_private', $board->is_private))
        >
        <span>비공개 게시글</span>
    </label>
    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
        공지사항, 자유게시판, 문의하기를 하나의 공통 게시판 구조로 운영하고 type 값으로 구분합니다.
    </p>
</div>

<div class="mt-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">첨부파일</p>
            <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                게시글에 필요한 파일을 공통 File Module 기준으로 업로드합니다. 이미지, 문서, 압축파일을 함께 첨부할 수 있습니다.
            </p>
        </div>
        <span class="status-badge">File Module</span>
    </div>

    <div class="mt-4">
        <label for="attachments" class="text-sm font-medium text-gray-900 dark:text-gray-100">파일 추가</label>
        <input
            id="attachments"
            name="attachments[]"
            type="file"
            class="input-field mt-2"
            title="첨부파일 업로드"
            aria-label="첨부파일 업로드"
            multiple
        >
        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">파일은 최대 10MB까지 업로드할 수 있습니다.</p>
    </div>

    @if ($attachmentFiles->isNotEmpty())
        <div class="mt-4">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">현재 첨부된 파일</p>
            <div class="mt-3 space-y-3">
                @foreach ($attachmentFiles as $attachmentFile)
                    <label class="flex items-start justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f3f3f3] px-4 py-3 text-sm dark:border-[#2a2a2a] dark:bg-[#181818]">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $attachmentFile->name }}</span>
                            <span class="mt-1 block truncate text-sm leading-6 text-gray-500 dark:text-gray-400">
                                {{ $attachmentFile->file_name }} / {{ number_format($attachmentFile->size / 1024, 1) }} KB
                            </span>
                        </span>

                        <span class="flex shrink-0 items-center gap-3">
                            <a
                                href="{{ route('dashboard.modules.files.download', $attachmentFile) }}"
                                class="btn-secondary"
                                title="첨부파일 다운로드"
                                aria-label="첨부파일 다운로드"
                            >
                                다운로드
                            </a>

                            <span class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                                <input
                                    type="checkbox"
                                    name="remove_attachment_ids[]"
                                    value="{{ $attachmentFile->id }}"
                                    class="h-4 w-4"
                                    title="첨부파일 제거"
                                    aria-label="첨부파일 제거"
                                >
                                제거
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif
</div>
