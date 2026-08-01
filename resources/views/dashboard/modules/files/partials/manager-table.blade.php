<section class="page-panel">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Manager UI</p>
            <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">업로드된 파일 관리</h3>
            <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                업로드된 파일 전체를 조회하고, 다운로드, 삭제, 사용처 확인 흐름을 별도 관리 영역에서 처리합니다.
            </p>
        </div>
        <span class="status-badge">{{ $managerFiles->count() }}건</span>
    </div>

    <div class="mt-6 overflow-x-auto rounded-[10px] border border-[#dddddd] dark:border-[#2a2a2a]">
        <table class="min-w-full border-collapse text-left">
            <thead class="bg-[#f1f1f1] dark:bg-[#1f1f1f]">
                <tr>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">번호</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">파일명</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">컬렉션</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">사용처</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">타입</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">크기</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">업로드일</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">관리</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e5e5e5] dark:divide-[#262626]">
                @forelse ($managerFiles as $media)
                    <tr class="bg-[#f7f7f7] align-top dark:bg-[#1a1a1a]">
                        <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $media->id }}</td>
                        <td class="px-4 py-4 text-sm">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $media->file_name }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $media->name }}</p>
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $media->collection_name }}</td>
                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ class_basename($media->model_type) }} #{{ $media->model_id }}
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $media->mime_type ?? '알 수 없음' }}</td>
                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ number_format($media->size / 1024, 1) }} KB</td>
                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $media->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-4 text-sm">
                            <div class="flex flex-wrap gap-2">
                                <a
                                    href="{{ route('dashboard.modules.files.download', $media) }}"
                                    class="btn-secondary"
                                    title="파일 다운로드"
                                    aria-label="파일 다운로드"
                                >
                                    다운로드
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('dashboard.modules.files.destroy', $media) }}"
                                    onsubmit="return confirm('파일을 삭제하시겠습니까?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="btn-secondary"
                                        title="파일 삭제"
                                        aria-label="파일 삭제"
                                    >
                                        삭제
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                        <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            관리할 파일이 없습니다.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
