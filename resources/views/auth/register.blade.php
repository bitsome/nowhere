<x-layouts.app title="회원가입">
    <section class="mx-auto max-w-4xl">
        <div class="mb-4 surface-card surface-card--muted">
            <h1 class="block-title">회원가입</h1>
            <p class="mt-2 block-description">
                새 계정을 생성하고 바로 인증된 상태로 메인 화면에 진입합니다.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[0.7fr_1.3fr]">
            <section class="surface-card surface-card--muted">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">생성 항목</h2>
                <div class="mt-4 space-y-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <p>이름, 이메일, 휴대폰, 비밀번호를 입력해 기본 계정을 생성합니다.</p>
                    <p>이메일과 휴대폰은 각각 중복 검사를 통과해야 합니다.</p>
                    <p>가입이 완료되면 바로 로그인된 상태로 대시보드로 이동합니다.</p>
                </div>
            </section>

            <section class="surface-card surface-card--raised">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">계정 정보 입력</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">운영 화면 접근에 사용할 기본 계정을 생성합니다.</p>
                </div>

                <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-900 dark:text-gray-100">이름</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="홍길동"
                            class="form-control"
                        >
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-100">이메일</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            placeholder="name@example.com"
                            class="form-control"
                        >
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-900 dark:text-gray-100">휴대폰</label>
                        <input
                            id="phone"
                            name="phone"
                            type="text"
                            value="{{ old('phone') }}"
                            required
                            autocomplete="tel"
                            placeholder="01012345678"
                            class="form-control"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-100">비밀번호</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="8자 이상 입력"
                            class="form-control"
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
                            class="form-control"
                        >
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="action-button action-button--primary min-w-28">
                            회원가입
                        </button>
                        <a href="{{ route('login') }}" class="action-button action-button--secondary min-w-28">
                            로그인
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </section>
</x-layouts.app>
