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

            @include('dashboard.partials.sidebar-nav', ['modules' => $modules, 'module' => $module])

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

                <form method="GET" action="{{ route('dashboard.business.users') }}" class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_220px_220px]">
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
                        <a href="{{ route('dashboard.business.users') }}" class="btn-secondary" title="검색 초기화">초기화</a>
                    </div>
                </form>

                <div class="mt-6" data-user-datatable></div>
            </section>
        </div>
    </section>

    {{-- 회원 상세보기 모달 (상세보기/편집 공용) --}}
    <x-modal data-user-detail-modal size="lg" title="회원 상세보기">
        <div class="flex items-start gap-4">
            <div
                class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-full border border-[#d8d8d8] bg-[#ececec] text-xl font-semibold text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]"
                data-user-detail-initial
            >?</div>

            <div class="grid flex-1 gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">이름</p>
                    <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100" data-user-detail="name">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">이메일</p>
                    <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100" data-user-detail="email">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">전화번호</p>
                    <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100" data-user-detail="phone">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Role</p>
                    <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100" data-user-detail="role">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">상태</p>
                    <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100" data-user-detail="statusLabel">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">가입일</p>
                    <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100" data-user-detail="createdAt">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">최근 로그인</p>
                    <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100" data-user-detail="lastLoginAt">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">로그인 횟수</p>
                    <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100" data-user-detail="loginCount">-</p>
                </div>
            </div>
        </div>

        <div class="mt-6 border-t border-[#dddddd] pt-5 dark:border-[#2a2a2a]" data-user-manage-only>
            <div class="grid gap-4 lg:grid-cols-2">
                <form method="POST" data-user-role-form class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="modal-role" class="text-sm font-medium text-gray-900 dark:text-gray-100">Role 변경</label>
                        <select id="modal-role" name="role" title="Role 변경" class="input-field mt-2" data-user-role-select>
                            @foreach ($assignableRoles as $roleOption)
                                <option value="{{ $roleOption }}">{{ $roleOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                        고유번호 1의 `Super Admin`은 `Super Admin`을 제외한 하위 Role을 부여할 수 있고, 그 외 사용자는 자신의 권한보다 낮은 Role만 부여할 수 있습니다.
                    </p>
                    <button type="submit" class="btn-primary" title="Role 저장">Role 저장</button>
                </form>

                <form method="POST" data-user-status-form class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="modal-status" class="text-sm font-medium text-gray-900 dark:text-gray-100">상태 변경</label>
                        <select id="modal-status" name="status" title="상태 변경" class="input-field mt-2" data-user-status-select>
                            @foreach ($statusOptions as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}">{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-secondary" title="상태 저장">상태 저장</button>
                </form>
            </div>
        </div>

        <div
            class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-4 text-sm leading-6 text-gray-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-400"
            data-user-detail-no-manage
            hidden
        >
            자신의 권한과 동등하거나 상위 권한을 가진 사용자는 수정할 수 없습니다.
        </div>

        <input type="hidden" data-user-role-action value="{{ route('dashboard.business.users.role.update', ['user' => ':id']) }}">
        <input type="hidden" data-user-status-action value="{{ route('dashboard.business.users.status.update', ['user' => ':id']) }}">

        <x-slot name="footer">
            <a href="#" data-user-detail-permissions class="btn-secondary" title="권한관리 이동">권한관리</a>
            <button type="button" class="btn-primary" data-modal-close title="닫기">닫기</button>
        </x-slot>
    </x-modal>

    <script type="application/json" data-user-detail-data>
        {!! json_encode($userDetailData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>
</x-layouts.app>
