<x-layouts.app title="비밀번호 찾기">
    <section class="mx-auto max-w-4xl">
        <div class="mb-4 page-panel panel-gray">
            <h1 class="section-title">비밀번호 찾기</h1>
            <p class="mt-2 section-description">
                이메일로 인증코드를 발송한 뒤 새 비밀번호를 입력하여 계정을 복구합니다.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[0.75fr_1.25fr]">
            <section class="page-panel panel-gray">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">처리 방식</h2>
                <div class="mt-4 space-y-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <p>이메일 주소를 입력한 뒤 인증코드 발송 버튼을 눌러 코드를 받습니다.</p>
                    <p>수신한 인증번호와 새 비밀번호를 입력하면 즉시 재설정이 완료됩니다.</p>
                    <p>인증코드는 일정 시간 이후 만료되며, 만료 시 다시 발송해야 합니다.</p>
                </div>
            </section>

            <section class="page-panel panel-dark">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">인증 및 재설정</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">이메일 인증코드 확인 후 새 비밀번호를 저장합니다.</p>
                </div>

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-100">이메일</label>
                        <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="name@example.com"
                                class="input-field mt-0 flex-1"
                            >
                            <button type="submit" class="btn-secondary min-w-36">
                                인증코드 발송
                            </button>
                        </div>
                    </div>
                </form>

                <form method="POST" action="{{ route('password.store') }}" class="mt-5 space-y-5">
                    @csrf

                    <div>
                        <label for="reset_email" class="block text-sm font-medium text-gray-900 dark:text-gray-100">이메일</label>
                        <input
                            id="reset_email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            placeholder="name@example.com"
                            class="input-field"
                        >
                    </div>

                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-900 dark:text-gray-100">인증번호</label>
                        <input
                            id="code"
                            name="code"
                            type="text"
                            value="{{ old('code') }}"
                            required
                            autocomplete="one-time-code"
                            placeholder="6자리 인증번호"
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
                            placeholder="새 비밀번호 입력"
                            class="input-field"
                        >
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-900 dark:text-gray-100">비밀번호 확인</label>
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
