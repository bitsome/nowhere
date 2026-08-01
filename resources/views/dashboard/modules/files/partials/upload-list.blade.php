<section class="page-panel panel-gray">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">파일 목록</p>
            <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">내 업로드 목록</h3>
        </div>
        <span class="status-badge">{{ $uploadFiles->count() }}건</span>
    </div>

    <div class="mt-4 grid gap-2.5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @forelse ($uploadFiles as $media)
            <article class="flex h-full flex-col rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2.5 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                @if (str_starts_with($media->mime_type ?? '', 'image/'))
                    <img
                        src="{{ $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl() }}"
                        alt="{{ $media->name }}"
                        title="{{ $media->name }}"
                        class="mx-auto h-[64px] w-[96px] rounded-[10px] border border-[#dddddd] bg-[#f3f3f3] object-contain dark:border-[#2a2a2a] dark:bg-[#181818]"
                    >
                @else
                    <div class="flex h-[80px] items-center justify-center rounded-[10px] border border-dashed border-[#d8d8d8] bg-[#f3f3f3] px-3 text-center dark:border-[#2a2a2a] dark:bg-[#181818]">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">미리보기 없음</p>
                            <p class="mt-1 truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $media->file_name }}</p>
                        </div>
                    </div>
                @endif

                <div class="mt-3 min-h-[70px]">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100" title="{{ $media->name }}">{{ $media->name }}</p>
                    <p class="mt-1 truncate text-xs leading-5 text-gray-500 dark:text-gray-400" title="{{ $media->file_name }}">{{ $media->file_name }}</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                        {{ number_format($media->size / 1024, 1) }} KB / {{ $media->mime_type ?? '알 수 없음' }}
                    </p>
                </div>

                <div class="mt-auto grid grid-cols-2 gap-2 pt-3">
                    <a
                        href="{{ route('dashboard.modules.files.download', $media) }}"
                        class="btn-secondary justify-center text-center"
                        title="파일 다운로드"
                        aria-label="파일 다운로드"
                    >
                        다운로드
                    </a>

                    <form
                        method="POST"
                        action="{{ route('dashboard.modules.files.destroy', $media) }}"
                        class="w-full"
                        onsubmit="return confirm('파일을 삭제하시겠습니까?');"
                    >
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="btn-secondary w-full justify-center text-center"
                            title="파일 삭제"
                            aria-label="파일 삭제"
                        >
                            삭제
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <div class="md:col-span-2 rounded-[10px] border border-dashed border-[#d8d8d8] bg-[#f7f7f7] px-4 py-10 text-center dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-base font-semibold text-gray-900 dark:text-gray-100">업로드된 파일이 없습니다.</p>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">상단 Upload UI에서 파일을 업로드하면 이 목록에 바로 표시됩니다.</p>
            </div>
        @endforelse
    </div>
</section>
