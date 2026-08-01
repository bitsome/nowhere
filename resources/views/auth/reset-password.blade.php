<x-layouts.app title="비밀번호 재설정">
    <section class="mx-auto max-w-4xl">
        <div class="mb-4 page-panel panel-gray">
            <h1 class="section-title">비밀번호 재설정</h1>
            <p class="mt-2 section-description">
                재설정 링크로 진입한 후 새 비밀번호를 입력하여 계정 접근을 복구합니다.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[0.75fr_1.25fr]">
            <section class="page-panel panel-gray">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">입력 항목</h2>
                <div class="mt-4 space-y-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <p>링크에 포함된 토큰과 입력한 이메일이 일치해야 합니다.</p>
                    <p>비밀번호와 비밀번호 확인 값이 동일해야 저장됩니다.</p>
                    <p>완료 후 로그인 페이지로 이동해 새 비밀번호로 로그인합니다.</p>
                </div>
            </section>

            <section class="page-panel panel-dark">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">새 비밀번호 입력</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">이메일과 새 비밀번호를 입력해 재설정을 완료하세요.</p>
                </div>

                <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-100">이메일</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', $email) }}"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="name@example.com"
                            class="input-field"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-100">새 비밀번호</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="8자 이상 입력"
                            class="input-field"
                        >
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-900 dark:text-gray-100">새 비밀번호 확인</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="비밀번호를 다시 입력"
                            class="input-field"
                        >
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="btn-primary min-w-36">
                            비밀번호 재설정
                        </button>
                        <a href="{{ route('login') }}" class="btn-secondary min-w-28">
                            로그인
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </section>
</x-layouts.app>
