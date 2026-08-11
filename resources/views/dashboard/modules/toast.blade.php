<x-layouts.app title="토스트">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="surface-card surface-card--muted lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dashboard Module</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $module['title'] }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ $module['description'] }}
                </p>
            </div>

            @include('dashboard.partials.sidebar-nav', ['modules' => $modules, 'module' => $module])
        </aside>

        <div class="space-y-4">
            <section class="surface-card surface-card--raised">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">피드백 컴포넌트</p>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">토스트 유형 현황</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Vue</p>
                </div>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    공용 토스트(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">ToastContainer</code> / <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">ToastItem</code>)의
                    유형과 구조를 확인합니다. 토스트는 우측 상단에 쌓이며 기본 3.2초 후 자동으로 닫힙니다.
                </p>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Toast Types</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">토스트 유형</h3>
                    </div>
                    <span class="meta-badge">Vue</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">type</code>은
                    info(안내) / success(완료) / error(오류) 세 가지를 지원합니다.
                    아래 버튼을 눌러 실제 토스트를 확인해 보세요.
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" data-toast-push="info" data-toast-message="새 오더가 등록되었습니다." data-toast-title="안내" title="안내 토스트 보내기">안내(info)</button>
                    <button type="button" class="btn-secondary" data-toast-push="success" data-toast-message="배차가 완료되었습니다." data-toast-title="완료" title="완료 토스트 보내기">완료(success)</button>
                    <button type="button" class="btn-primary" data-toast-push="error" data-toast-message="오더 저장에 실패했습니다." data-toast-title="오류 발생" title="오류 토스트 보내기">오류(error)</button>
                </div>
                <div data-toast-playground></div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Toast Structure</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">토스트 구조</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    아이콘 + 제목 + 메시지 + 닫기 구조입니다. 타입별 아이콘은 info=bell, success=check, error=close를 사용하며
                    닫기 버튼으로 개별 토스트를 바로 닫을 수 있습니다.
                </p>
                <div class="mt-4 max-w-md space-y-3">
                    <article class="toast-card is-info">
                        <div class="toast-card__row">
                            <span class="toast-card__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                                    <path stroke-linecap="round" d="M14.86 17H9.14a2 2 0 0 0-1.94 1.53L7 19h10l-.2-.47A2 2 0 0 0 14.86 17Z" />
                                    <path stroke-linecap="round" d="M18 11a6 6 0 1 0-12 0c0 2.18-.7 3.41-1.37 4.28-.32.42-.13 1.04.41 1.22h13.92c.54-.18.73-.8.41-1.22C18.7 14.41 18 13.18 18 11Z" />
                                </svg>
                            </span>
                            <div class="toast-card__body">
                                <p class="toast-card__title">안내</p>
                                <p class="toast-card__message">새 오더가 등록되었습니다.</p>
                            </div>
                            <button type="button" class="toast-card__close" title="Toast 닫기" aria-label="Toast 닫기">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </button>
                        </div>
                    </article>

                    <article class="toast-card is-success">
                        <div class="toast-card__row">
                            <span class="toast-card__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                                    <path stroke-linecap="round" d="M5 12l4.5 4.5L19 7" />
                                </svg>
                            </span>
                            <div class="toast-card__body">
                                <p class="toast-card__title">완료</p>
                                <p class="toast-card__message">배차가 완료되었습니다.</p>
                            </div>
                            <button type="button" class="toast-card__close" title="Toast 닫기" aria-label="Toast 닫기">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </button>
                        </div>
                    </article>

                    <article class="toast-card is-error">
                        <div class="toast-card__row">
                            <span class="toast-card__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </span>
                            <div class="toast-card__body">
                                <p class="toast-card__title">오류 발생</p>
                                <p class="toast-card__message">오더 저장에 실패했습니다.</p>
                            </div>
                            <button type="button" class="toast-card__close" title="Toast 닫기" aria-label="Toast 닫기">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Review</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">확인 포인트 (개선 후보)</h3>
                    </div>
                    <span class="meta-badge">관찰</span>
                </div>
                <ul class="mt-4 space-y-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>토스트 타입은 그레이스케일로만 구분 — 의미 색상 지정 전까지 유지하고, 지정 시 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">toast.css</code> 한 곳에서만 수정하도록 구성.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span><code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">autoCloseDelay</code> 기본 3.2초 — 오류 토스트처럼 길게 보여야 하는 케이스는 항목 단위로 조정할 수 있도록 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">closeable: false</code> 또는 개별 delay 확장 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>현재 알림 페이지에서 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">createToastBridge</code>로 토스트를 띄움 — 다른 화면에서도 같은 브리지 방식을 재사용하면 토스트 규칙이 통일됩니다.</span>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.app>
