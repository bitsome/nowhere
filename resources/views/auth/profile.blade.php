<x-layouts.app title="프로필">
    @php
        $profilePhotoUrl = auth()->user()->profile_photo_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url(auth()->user()->profile_photo_path)
            : null;
    @endphp

    <section class="mx-auto max-w-5xl space-y-4">
        <div class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="page-panel panel-dark">
                <h1 class="section-title">프로필</h1>
                <p class="mt-2 section-description">
                    현재 로그인한 계정의 기본 정보를 수정하고 필요 시 비밀번호를 변경합니다.
                </p>

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <p class="block text-sm font-medium text-gray-900 dark:text-gray-100">프로필 사진</p>
                        <div class="mt-2 flex items-center gap-4">
                            @if ($profilePhotoUrl)
                                <img src="{{ $profilePhotoUrl }}" alt="프로필 사진" class="h-[72px] w-[72px] rounded-full border border-[#d6d6d6] object-cover dark:border-[#343434]">
                            @else
                                <div class="flex h-[72px] w-[72px] items-center justify-center rounded-full border border-[#d6d6d6] bg-[#f1f1f1] text-lg font-semibold text-[#555555] dark:border-[#343434] dark:bg-[#202020] dark:text-[#b9bbc0]">
                                    {{ mb_substr(auth()->user()->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="flex-1">
                                <input
                                    id="profile_photo"
                                    name="profile_photo"
                                    type="file"
                                    accept="image/*"
                                    class="input-field"
                                >
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">선택 시 새 프로필 사진으로 교체됩니다.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-900 dark:text-gray-100">이름</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', auth()->user()->name) }}"
                            required
                            autocomplete="name"
                            class="input-field"
                        >
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-100">이메일</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', auth()->user()->email) }}"
                            required
                            autocomplete="email"
                            class="input-field"
                        >
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-900 dark:text-gray-100">휴대폰</label>
                        <input
                            id="phone"
                            name="phone"
                            type="text"
                            value="{{ old('phone', auth()->user()->phone) }}"
                            required
                            autocomplete="tel"
                            class="input-field"
                        >
                    </div>

                    <div class="ui-divider border-t pt-5">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">비밀번호 변경</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">변경이 필요할 때만 현재 비밀번호와 새 비밀번호를 입력하세요.</p>
                    </div>

                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-900 dark:text-gray-100">현재 비밀번호</label>
                        <input
                            id="current_password"
                            name="current_password"
                            type="password"
                            autocomplete="current-password"
                            class="input-field"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-100">새 비밀번호</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            class="input-field"
                        >
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-900 dark:text-gray-100">새 비밀번호 확인</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="input-field"
                        >
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="btn-primary min-w-28">
                            저장
                        </button>
                        <a href="{{ route('dashboard') }}" class="btn-secondary min-w-28">
                            대시보드
                        </a>
                    </div>
                </form>
            </section>

            <aside class="page-panel panel-gray">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">계정 상태</h2>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">사용자 ID</dt>
                        <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ auth()->id() }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">현재 이메일</dt>
                        <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ auth()->user()->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">휴대폰</dt>
                        <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ auth()->user()->phone ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">세션 상태</dt>
                        <dd class="status-badge mt-1">인증됨</dd>
                    </div>
                </dl>

                <div class="ui-divider mt-6 border-t pt-6">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-secondary w-full">
                            로그아웃
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.app>
