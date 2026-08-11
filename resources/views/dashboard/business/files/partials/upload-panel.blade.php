<section class="page-panel">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Upload UI</p>
            <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">파일 선택, 업로드, 이미지 미리보기</h3>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-500 dark:text-gray-400">
                공통 업로드 영역입니다. 다중 파일 업로드와 청크 전송을 기준으로 구성하고, 선택한 이미지는 업로드 전에 간단한 미리보기로 먼저 확인할 수 있습니다.
            </p>
        </div>
        <span class="status-badge">Upload</span>
    </div>

    <form
        method="POST"
        action="{{ route('dashboard.business.files.store') }}"
        enctype="multipart/form-data"
        class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_0.85fr]"
        data-chunk-upload-form
        data-csrf-token="{{ csrf_token() }}"
    >
        @csrf

        <div class="space-y-4">
            <div>
                <label for="files" class="text-sm font-medium text-gray-900 dark:text-gray-100">업로드 파일</label>
                <input
                    id="files"
                    name="files[]"
                    type="file"
                    title="업로드 파일"
                    aria-label="업로드 파일"
                    class="input-field mt-2"
                    accept="image/*,.pdf,.zip,.txt,.doc,.docx,.xls,.xlsx"
                    multiple
                    data-file-preview-input
                    required
                >
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    파일을 다중 선택할 수 있습니다. 업로드는 청크 단위로 전송하고, 선택한 이미지는 업로드 전에 미리보기를 제공합니다.
                </p>
            </div>

            <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">업로드 상태</p>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400" data-upload-status>파일을 선택한 뒤 업로드 버튼을 눌러주세요.</p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-[#e5e5e5] dark:bg-[#262626]">
                    <div class="h-full w-0 rounded-full bg-[#6a6a6a] transition-all" data-upload-progress-bar></div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary" title="파일 업로드" aria-label="파일 업로드" data-upload-button>파일 업로드</button>
                <a href="{{ route('dashboard.business.files') }}" class="btn-secondary" title="파일관리 새로고침">새로고침</a>
            </div>
        </div>

        <div
            class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]"
            data-file-preview-panel
        >
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">선택 미리보기</p>
            <div
                class="mt-4 flex min-h-[144px] flex-col items-center justify-center rounded-[10px] border border-dashed border-[#d8d8d8] bg-[#f3f3f3] px-4 py-5 text-center dark:border-[#2a2a2a] dark:bg-[#181818]"
                data-file-preview-empty
            >
                <p class="text-base font-semibold text-gray-900 dark:text-gray-100">선택된 파일이 없습니다.</p>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">좌측에서 파일을 다중 선택하면 이미지 미리보기와 파일 목록이 이 영역에 표시됩니다.</p>
            </div>

            <div class="mt-4 hidden grid gap-3 sm:grid-cols-2 xl:grid-cols-3" data-file-preview-list>
            </div>
        </div>
    </form>
</section>
