<x-layouts.app :title="config('app.name', 'Nowhere')">
    <section class="grid gap-4 lg:grid-cols-[1.3fr_0.7fr]">
            <div class="surface-card surface-card--raised">
                <div class="mb-6">
                    <span class="meta-badge">서비스 개요</span>
                    <h1 class="mt-4 text-[28px] font-semibold text-gray-900 dark:text-gray-100">
                        NoWhere 운영 시스템 시작 화면
                    </h1>
                    <p class="mt-3 max-w-3xl text-base leading-7 text-gray-500 dark:text-gray-400">
                        현재 프로젝트는 로그인, 기본 대시보드, 문서 기반 운영 규칙을 중심으로 구성되어 있습니다.
                        이 화면은 사용자가 시스템 상태를 빠르게 이해하고 필요한 작업으로 이동하기 위한 시작점입니다.
                    </p>
                </div>

                <x-alert variant="info" title="로그인 후 대시보드를 이용할 수 있습니다." message="로그인하면 오더 마켓, 내 오더, 비즈니스 대시보드를 확인할 수 있습니다." />

                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="action-button action-button--primary">로그인</a>
                    <a href="{{ route('register') }}" class="action-button action-button--secondary">회원가입</a>
                    <a href="https://laravel.com/docs/13.x/authentication" target="_blank" rel="noreferrer" class="action-button action-button--secondary">
                        인증 문서
                    </a>
                </div>
            </div>

            <div class="surface-card surface-card--muted">
                <h2 class="block-title">현재 상태</h2>
                <div class="mt-4 space-y-4">
                    <div class="ui-divider border-b pb-4">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">인증</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">세션 기반 로그인과 로그아웃 흐름이 구성되어 있습니다.</p>
                    </div>
                    <div class="ui-divider border-b pb-4">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">프론트엔드</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Blade와 plain CSS 기반의 업무용 UI 규칙을 적용합니다.</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">다음 작업</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">회원가입, 배차 화면, 운영/정산 기능 확장이 예정되어 있습니다.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-4 grid gap-4 md:grid-cols-3">
            <article class="summary-card">
                <p class="text-sm text-gray-500 dark:text-gray-400">인증 방식</p>
                <p class="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">Session Auth</p>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">Laravel 기본 세션 인증을 사용합니다.</p>
            </article>
            <article class="summary-card">
                <p class="text-sm text-gray-500 dark:text-gray-400">UI 기준</p>
                <p class="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">Business First</p>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">단순하고 일관된 업무용 UI를 우선합니다.</p>
            </article>
            <article class="summary-card">
                <p class="text-sm text-gray-500 dark:text-gray-400">문서화</p>
                <p class="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">Docs Driven</p>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">운영 규칙과 아키텍처 문서를 기준으로 개발합니다.</p>
            </article>
        </section>
</x-layouts.app>
