<x-layouts.app title="로그인">
    <section class="mx-auto max-w-4xl">
        <div class="mb-4 surface-card surface-card--muted">
            <h1 class="block-title">로그인</h1>
            <p class="mt-2 block-description">
                등록된 계정으로 로그인하여 대시보드와 운영 기능에 접근합니다.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[0.7fr_1.3fr]">
            <section class="surface-card surface-card--muted">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">안내</h2>
                <div class="mt-4 space-y-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <p>로그인 후 인증이 필요한 화면에 접근할 수 있습니다.</p>
                    <p>Remember Me를 선택하면 로그인 상태를 유지할 수 있습니다.</p>
                    <p>입력 오류가 있으면 필드 아래에 즉시 표시됩니다.</p>
                    <p>로그인 상태 유지를 선택하면 세션이 유지됩니다.</p>
                </div>
            </section>

            <section class="surface-card surface-card--raised">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">계정 정보 입력</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">이메일과 비밀번호를 순서대로 입력하세요.</p>
                </div>

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-100">이메일</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="name@example.com"
                            class="form-control"
                        >
                        @error('email')
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-100">비밀번호</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="form-control"
                        >
                        @error('password')
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            @checked(old('remember'))
                            class="h-4 w-4 rounded border-gray-300 bg-transparent text-gray-900 focus:ring-0 dark:border-gray-700 dark:bg-transparent dark:text-gray-100"
                        >
                        로그인 상태 유지
                    </label>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="action-button action-button--primary min-w-28">
                            로그인
                        </button>
                        <a href="{{ route('password.request') }}" class="action-button action-button--secondary min-w-36">
                            비밀번호 찾기
                        </a>
                        <a href="{{ route('register') }}" class="action-button action-button--secondary min-w-28">
                            회원가입
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </section>
</x-layouts.app>
