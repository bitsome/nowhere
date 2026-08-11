<x-layouts.app title="게시판">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dashboard Module</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $module['title'] }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    공지, 자유, 문의 게시판을 하나의 공통 게시판 구조로 운영하는 1차 화면입니다.
                </p>
            </div>

            @include('dashboard.partials.sidebar-nav', ['modules' => $modules, 'module' => $module])

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">1차 제외 항목</p>
                <p class="mt-2 text-sm leading-6 text-gray-900 dark:text-gray-100">
                    댓글, 첨부파일, 좋아요, 신고, 북마크, FAQ, 상단 고정, 실시간 알림은 이번 범위에서 제외합니다.
                </p>
            </div>
        </aside>

        <div class="space-y-4">
            <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="page-panel panel-dark">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">게시판 1차</p>
                    <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">공통 게시글 운영</h2>
                    <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                        목록, 상세, 등록, 수정, 삭제, 검색만 먼저 구현하고 `type` 기준으로 공지사항, 자유게시판, 문의하기를 재사용합니다.
                    </p>
                </div>

                <aside class="page-panel panel-gray">
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">현재 요약</p>
                    <dl class="mt-4 space-y-4">
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">총 게시글 수</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $boards->count() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">게시판 구분</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ implode(' / ', array_values($typeLabels)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">내 권한</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ implode(' / ', $user->resolvedPermissions()) }}</dd>
                        </div>
                    </dl>
                </aside>
            </section>

            <section class="page-panel">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">게시글 목록</p>
                        <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">목록, 검색, 구분 필터</h3>
                    </div>

                    @if ($user->hasPermission('board.create') || $user->hasPermission('board.view'))
                        <a href="{{ route('dashboard.business.boards.create') }}" class="btn-primary" title="게시글 등록" aria-label="게시글 등록">게시글 등록</a>
                    @endif
                </div>

                <form method="GET" action="{{ route('dashboard.business.boards') }}" class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_220px_220px]">
                    <div>
                        <label for="search" class="text-sm font-medium text-gray-900 dark:text-gray-100">게시글 검색</label>
                        <input
                            id="search"
                            name="search"
                            type="text"
                            title="게시글 검색"
                            class="input-field mt-2"
                            placeholder="제목, 작성자, 내용 검색"
                            value="{{ $filters['search'] }}"
                        >
                    </div>
                    <div>
                        <label for="type" class="text-sm font-medium text-gray-900 dark:text-gray-100">게시판 필터</label>
                        <select id="type" name="type" title="게시판 필터" class="input-field mt-2">
                            <option value="">전체</option>
                            @foreach ($typeOptions as $typeOption)
                                <option value="{{ $typeOption }}" @selected($filters['type'] === $typeOption)>{{ $typeLabels[$typeOption] ?? $typeOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="text-sm font-medium text-gray-900 dark:text-gray-100">상태 필터</label>
                        <select id="status" name="status" title="상태 필터" class="input-field mt-2">
                            <option value="">전체</option>
                            @foreach ($statusOptions as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-3 flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary" title="게시글 검색" aria-label="게시글 검색">검색</button>
                        <a href="{{ route('dashboard.business.boards') }}" class="btn-secondary" title="검색 초기화">초기화</a>
                    </div>
                </form>

                <div class="mt-6 overflow-x-auto rounded-[10px] border border-[#dddddd] dark:border-[#2a2a2a]">
                    <table class="min-w-full border-collapse text-left">
                        <thead class="bg-[#f1f1f1] dark:bg-[#1f1f1f]">
                            <tr>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">번호</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">제목</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">작성자</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">조회수</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">작성일</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">구분</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">상태</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">관리</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e5e5e5] dark:divide-[#262626]">
                            @forelse ($boards as $board)
                                <tr class="bg-[#f7f7f7] align-top dark:bg-[#1a1a1a]">
                                    <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $board->id }}</td>
                                    <td class="px-4 py-4 text-sm font-medium">
                                        <a
                                            href="{{ route('dashboard.business.boards.show', $board) }}"
                                            title="{{ $board->title }}"
                                            class="text-gray-900 underline-offset-4 transition hover:underline dark:text-gray-100"
                                        >
                                            {{ $board->title }}
                                        </a>
                                        @if ($board->is_private)
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">비공개</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $board->user?->name }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ number_format($board->view_count) }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $board->created_at?->format('Y-m-d') }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $typeLabels[$board->type] ?? $board->type }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $statusOptions[$board->status] ?? $board->status }}</td>
                                    <td class="px-4 py-4 text-sm">
                                        @php
                                            $boardActions = [
                                                ['icon' => 'view', 'label' => '상세', 'href' => route('dashboard.business.boards.show', $board)],
                                            ];
                                            if ($user->hasPermission('board.update')) {
                                                $boardActions[] = ['icon' => 'edit', 'label' => '수정', 'href' => route('dashboard.business.boards.edit', $board)];
                                            }
                                        @endphp
                                        <div class="flex justify-end">
                                            <div
                                                data-table-actions
                                                data-trigger-label="게시글 관리"
                                                data-items='@json($boardActions)'
                                            ></div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                                    <td colspan="8" class="px-4 py-12 text-center">
                                        <div class="mx-auto inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#d8d8d8] bg-[#efefef] text-[#555555] dark:border-[#343434] dark:bg-[#202020] dark:text-[#b9bbc0]">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                                                <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
                                                <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
                                                <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
                                                <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
                                            </svg>
                                        </div>
                                        <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-gray-100">게시글이 없습니다.</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">조건에 맞는 게시글이 없습니다.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
