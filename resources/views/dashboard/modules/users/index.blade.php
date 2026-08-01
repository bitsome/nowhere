@php
    use App\Models\User;

    $statusLabels = User::statusOptions();
@endphp

<x-layouts.app title="회원관리">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dashboard Module</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $module['title'] }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    권한 부여 목적의 회원관리 1차 화면입니다. 회원 조회, Role, Permission, 상태 관리에 집중합니다.
                </p>
            </div>

            <nav class="mt-4 space-y-2">
                <a
                    href="{{ route('dashboard') }}"
                    title="대시보드 허브"
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                >
                    <span>허브 홈</span>
                    <span class="text-xs">00</span>
                </a>

                @foreach ($modules as $moduleItem)
                    <a
                        href="{{ $moduleItem['href'] }}"
                        title="{{ $moduleItem['title'] }}"
                        class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition {{ $moduleItem['key'] === $module['key'] ? 'border-[#cfcfcf] bg-[#ececec] text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]' : 'border-[#d8d8d8] bg-[#f5f5f5] text-[#4f4f4f] hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]' }}"
                    >
                        <span>{{ $moduleItem['title'] }}</span>
                        <span class="text-xs">{{ $moduleItem['order'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">1차 제외 항목</p>
                <p class="mt-2 text-sm leading-6 text-gray-900 dark:text-gray-100">
                    회원등록, 회원삭제, 일반정보 수정, 파일 업로드, 조직/부서/직급 관리는 이번 범위에서 제외합니다.
                </p>
            </div>
        </aside>

        <div class="space-y-4">
            <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="page-panel panel-dark">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">회원관리</p>
                    <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">권한 중심 회원 운영</h2>
                    <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                        회원 자체가 목적이 아니라 Role과 Permission 부여가 목적이므로, 목록에서 바로 조회하고 상세/권한 화면으로 빠르게 이동할 수 있게 구성합니다.
                    </p>
                </div>

                <aside class="page-panel panel-gray">
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">현재 요약</p>
                    <dl class="mt-4 space-y-4">
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">총 회원수</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $users->count() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Role 종류</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ implode(' / ', $roleOptions) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">상태 종류</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ implode(' / ', array_values($statusLabels)) }}</dd>
                        </div>
                    </dl>
                </aside>
            </section>

            <section class="page-panel">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">회원목록</p>
                        <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">회원 조회 및 권한 관리 시작점</h3>
                    </div>
                </div>

                <form method="GET" action="{{ route('dashboard.modules.users') }}" class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_220px_220px]">
                    <div>
                        <label for="search" class="text-sm font-medium text-gray-900 dark:text-gray-100">회원검색</label>
                        <input
                            id="search"
                            name="search"
                            type="text"
                            title="회원검색"
                            class="input-field mt-2"
                            placeholder="이름, 이메일, 전화번호 검색"
                            value="{{ $filters['search'] }}"
                        >
                    </div>
                    <div>
                        <label for="role" class="text-sm font-medium text-gray-900 dark:text-gray-100">Role 필터</label>
                        <select id="role" name="role" title="Role 필터" class="input-field mt-2">
                            <option value="">전체</option>
                            @foreach ($roleOptions as $roleOption)
                                <option value="{{ $roleOption }}" @selected($filters['role'] === $roleOption)>{{ $roleOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="text-sm font-medium text-gray-900 dark:text-gray-100">상태 필터</label>
                        <select id="status" name="status" title="상태 필터" class="input-field mt-2">
                            <option value="">전체</option>
                            @foreach ($statusLabels as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-3 flex flex-wrap gap-2">
                        <button type="submit" class="btn-primary" title="회원 검색" aria-label="회원 검색">검색</button>
                        <a href="{{ route('dashboard.modules.users') }}" class="btn-secondary" title="검색 초기화">초기화</a>
                    </div>
                </form>

                <div class="mt-6 overflow-x-auto rounded-[10px] border border-[#dddddd] dark:border-[#2a2a2a]">
                    <table class="min-w-full border-collapse text-left">
                        <thead class="bg-[#f1f1f1] dark:bg-[#1f1f1f]">
                            <tr>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">고유번호</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">이름</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">전화번호</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Role</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">상태</th>
                                <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">가입일</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e5e5e5] dark:divide-[#262626]">
                            @forelse ($users as $user)
                                <tr class="bg-[#f7f7f7] align-top dark:bg-[#1a1a1a]">
                                    <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $user->id }}
                                    </td>
                                    <td class="px-4 py-4 text-sm font-medium">
                                        <a
                                            href="{{ route('dashboard.modules.users.show', $user) }}"
                                            title="{{ $user->name }} 상세페이지"
                                            class="text-gray-900 underline-offset-4 transition hover:underline dark:text-gray-100"
                                        >
                                            {{ $user->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $user->phone }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $user->role }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $statusLabels[$user->status] ?? $user->status }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $user->created_at?->format('Y-m-d') }}</td>
                                </tr>
                            @empty
                                <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        검색 결과가 없습니다.
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
