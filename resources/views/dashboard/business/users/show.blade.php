@php
    use App\Models\User;

    $statusLabels = User::statusOptions();
    $resolvedPermissions = $user->resolvedPermissions();
@endphp

<x-layouts.app title="회원상세">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">User Detail</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    기본정보, Role, 상태, Permission 요약을 확인하는 회원상세 화면입니다.
                </p>
            </div>

            <nav class="mt-4 space-y-2">
                <a href="{{ route('dashboard.business.users') }}" class="flex items-center justify-between rounded-lg border border-[#cfcfcf] bg-[#ececec] px-3 py-2 text-sm text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]" title="회원목록">
                    <span>회원목록</span>
                    <span class="text-xs">01</span>
                </a>
                <a href="{{ route('dashboard.business.users.show', $user) }}" class="flex items-center justify-between rounded-lg border border-[#cfcfcf] bg-[#ececec] px-3 py-2 text-sm text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]" title="회원상세">
                    <span>회원상세</span>
                    <span class="text-xs">02</span>
                </a>
                <a href="{{ route('dashboard.business.users.permissions', $user) }}" class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]" title="권한관리">
                    <span>권한관리</span>
                    <span class="text-xs">03</span>
                </a>
            </nav>
        </aside>

        <div class="space-y-4">
            <section class="grid gap-4 xl:grid-cols-[1fr_0.9fr]">
                <div class="page-panel">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">기본정보</p>
                    <div class="mt-4 flex items-start gap-4">
                        @if ($user->profile_photo_path)
                            <img src="{{ asset('storage/'.$user->profile_photo_path) }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-full object-cover">
                        @else
                            <div class="flex h-20 w-20 items-center justify-center rounded-full border border-[#d8d8d8] bg-[#ececec] text-xl font-semibold text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]">
                                {{ str($user->name)->substr(0, 1)->upper() }}
                            </div>
                        @endif

                        <div class="grid flex-1 gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">이름</p>
                                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">이메일</p>
                                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $user->email }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">닉네임</p>
                                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">-</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">전화번호</p>
                                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $user->phone }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">가입일</p>
                                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $user->created_at?->format('Y-m-d H:i') }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">최근 로그인</p>
                                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $user->last_login_at?->format('Y-m-d H:i') ?? '기록 없음' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">로그인 횟수</p>
                                <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ number_format($user->login_count) }}회</p>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="page-panel panel-gray">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">활동정보</p>
                    <div class="mt-4 space-y-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">마지막 로그인</p>
                            <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $user->last_login_at?->format('Y-m-d H:i') ?? '기록 없음' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">로그인 횟수</p>
                            <p class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ number_format($user->login_count) }}회</p>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                <div class="page-panel">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Role</p>
                    @if ($canManageUser)
                        <form method="POST" action="{{ route('dashboard.business.users.role.update', $user) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PATCH')
                            <x-form-group label="Role 변경" for="role" :error="$errors->first('role')">
                                <select id="role" name="role" title="Role 변경" class="input-field">
                                    @foreach ($assignableRoles as $roleOption)
                                        <option value="{{ $roleOption }}" @selected($user->role === $roleOption)>{{ $roleOption }}</option>
                                    @endforeach
                                </select>
                            </x-form-group>
                            <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                                고유번호 1의 `Super Admin`은 `Super Admin`을 제외한 하위 Role을 부여할 수 있고, 그 외 사용자는 자신의 권한보다 낮은 Role만 부여할 수 있습니다.
                            </p>
                            <button type="submit" class="btn-primary" title="Role 저장">Role 저장</button>
                        </form>

                        <form method="POST" action="{{ route('dashboard.business.users.status.update', $user) }}" class="mt-6 space-y-4">
                            @csrf
                            @method('PATCH')
                            <x-form-group label="상태 변경" for="status" :error="$errors->first('status')">
                                <select id="status" name="status" title="상태 변경" class="input-field">
                                    @foreach ($statusLabels as $statusKey => $statusLabel)
                                        <option value="{{ $statusKey }}" @selected($user->status === $statusKey)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </x-form-group>
                            <button type="submit" class="btn-secondary" title="상태 저장">상태 저장</button>
                        </form>
                    @else
                        <div class="mt-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-4 text-sm leading-6 text-gray-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-400">
                            자신의 권한과 동등하거나 상위 권한을 가진 사용자는 수정할 수 없습니다.
                        </div>
                    @endif
                </div>

                <div class="page-panel panel-gray">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Permission</p>
                            <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">현재 권한 요약</h3>
                        </div>
                        <a href="{{ route('dashboard.business.users.permissions', $user) }}" class="btn-secondary" title="권한관리 이동">권한관리</a>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ($permissionOptions as $permission)
                            <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-3 text-sm dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                                        <span class="{{ in_array($permission, $resolvedPermissions, true) ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500' }}">
                                            {{ $permission }}
                                        </span>
                                        @if (in_array($permission, $assignablePermissions, true))
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">부여 가능</p>
                                        @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
