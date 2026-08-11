<x-layouts.app title="NoWhere 비즈니스 허브">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]" data-dashboard-nowhere-hub>
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dashboard Module</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $module['title'] }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ $module['description'] }}
                </p>
            </div>

            @include('dashboard.partials.sidebar-nav', ['modules' => $modules, 'module' => $module])

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">개발 원칙</p>
                <p class="mt-2 text-sm leading-6 text-gray-900 dark:text-gray-100">
                    기능을 바로 붙이지 않고, 비즈니스 허브 UI와 준비 상태를 먼저 정리한 뒤 Foundation 순서에 맞춰 진행합니다.
                </p>
            </div>
        </aside>

        <div class="space-y-4">
            <section class="page-panel">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">NoWhere Business</p>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">핵심 비즈니스 모듈</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-gray-500 dark:text-gray-400">
                            `Order`, `Dispatch`, `Settlement`는 NoWhere의 핵심 비즈니스 모듈이지만, 지금은 허브와 준비 상태만 먼저 두고 실제 개발은 Foundation과 Business Foundation 순서를 따라 진행합니다.
                        </p>
                    </div>
                    <span class="status-badge">{{ count($businessModules) }}개 모듈</span>
                </div>

                <div class="mt-6 grid gap-4 xl:grid-cols-3">
                    @foreach ($businessModules as $businessModule)
                        <article class="rounded-[10px] border border-[#dddddd] bg-[#f4f4f4] p-4 dark:border-[#2a2a2a] dark:bg-[#1d1d1d]">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ strtoupper($businessModule['key']) }}</p>
                                    <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $businessModule['title'] }}</h3>
                                </div>
                                <span class="meta-badge">{{ $businessModule['status'] }}</span>
                            </div>

                            <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                {{ $businessModule['description'] }}
                            </p>

                            @if (! empty($businessModule['href']))
                                <a
                                    href="{{ $businessModule['href'] }}"
                                    title="{{ $businessModule['title'] }} 골격 보기"
                                    class="mt-4 inline-flex items-center rounded-lg border border-[#cfcfcf] bg-[#ececec] px-3 py-2 text-sm text-[#1f1f1f] transition hover:bg-[#e4e4e4] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3] dark:hover:bg-[#2a2a2a]"
                                >
                                    골격 보기
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <article class="page-panel panel-gray">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">현재 방향</p>
                    <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">Business Foundation 우선</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        <li>Customer, Company, Vehicle, Driver, Common Code를 먼저 준비합니다.</li>
                        <li>오더 화면은 직접 입력보다 기존 데이터를 선택하는 구조로 시작합니다.</li>
                        <li>배차와 정산은 선행 데이터와 확정 흐름이 준비된 뒤에 붙입니다.</li>
                    </ul>
                </article>

                <article class="page-panel panel-gray">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">다음 연결</p>
                    <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">개발 순서</h3>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-3 text-sm text-gray-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-100">1. Foundation 마감</div>
                        <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-3 text-sm text-gray-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-100">2. Business Foundation 구축</div>
                        <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-3 text-sm text-gray-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-100">3. Order → Dispatch → Settlement</div>
                    </div>
                </article>
            </section>
        </div>
    </section>
</x-layouts.app>
