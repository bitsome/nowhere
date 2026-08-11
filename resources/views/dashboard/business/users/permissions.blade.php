@php
    $resolvedPermissions = $user->resolvedPermissions();
@endphp

<x-layouts.app title="권한관리">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">User Permission</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    회원별 Permission을 직접 보고 수정하는 화면입니다.
                </p>
            </div>

            <nav class="mt-4 space-y-2">
                <a href="{{ route('dashboard.business.users') }}" class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]" title="회원목록">
                    <span>회원목록</span>
                    <span class="text-xs">01</span>
                </a>
                <a href="{{ route('dashboard.business.users.show', $user) }}" class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]" title="회원상세">
                    <span>회원상세</span>
                    <span class="text-xs">02</span>
                </a>
                <a href="{{ route('dashboard.business.users.permissions', $user) }}" class="flex items-center justify-between rounded-lg border border-[#cfcfcf] bg-[#ececec] px-3 py-2 text-sm text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]" title="권한관리">
                    <span>권한관리</span>
                    <span class="text-xs">03</span>
                </a>
            </nav>
        </aside>

        <div class="space-y-4">
            <section class="grid gap-4 xl:grid-cols-[1fr_0.8fr]">
                <div class="page-panel panel-dark">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Permission 관리</p>
                    <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }} 권한 설정</h2>
                    <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                        현재 회원의 Permission을 직접 선택하고 저장합니다. 고유번호 1의 `Super Admin`은 전체 하위 Permission을 부여할 수 있고, 그 외 사용자는 자신이 가진 Permission 범위 안에서만 다른 사용자에게 부여할 수 있습니다.
                    </p>
                </div>

                <aside class="page-panel panel-gray">
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">회원 요약</p>
                    <dl class="mt-4 space-y-3">
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">이름</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">Role</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $user->role }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 dark:text-gray-400">현재 권한 수</dt>
                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ count($resolvedPermissions) }}개</dd>
                        </div>
                    </dl>
                </aside>
            </section>

            <section class="page-panel">
                @if ($canManageUser)
                    <form method="POST" action="{{ route('dashboard.business.users.permissions.update', $user) }}">
                        @csrf
                        @method('PATCH')

                        <x-form-group
                            label="부여할 권한"
                            for="permissions"
                            :error="$errors->first('permissions')"
                        >
                            <div class="grid gap-4 md:grid-cols-2">
                                @foreach ($permissionOptions as $permission)
                                    <label class="flex items-center gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-4 text-sm dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission }}"
                                            title="{{ $permission }}"
                                            class="h-4 w-4"
                                            @checked(in_array($permission, $resolvedPermissions, true))
                                            @disabled(! in_array($permission, $assignablePermissions, true))
                                        >
                                        <span class="{{ in_array($permission, $assignablePermissions, true) ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500' }}">{{ $permission }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </x-form-group>

                        <p class="mt-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            비활성화된 항목은 현재 로그인 사용자가 다른 사용자에게 부여할 수 없는 상위 Permission입니다.
                        </p>

                        <div class="mt-6 flex flex-wrap gap-2">
                            <button type="submit" class="btn-primary" title="권한 저장">권한 저장</button>
                            <a href="{{ route('dashboard.business.users.show', $user) }}" class="btn-secondary" title="회원상세로 돌아가기">회원상세</a>
                        </div>
                    </form>
                @else
                    <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-4 text-sm leading-6 text-gray-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-400">
                        자신의 권한과 동등하거나 상위 권한을 가진 사용자의 Permission은 수정할 수 없습니다.
                    </div>
                @endif
            </section>
        </div>
    </section>
</x-layouts.app>
