<x-layouts.app title="오더 관리">
    @php
        $orderTableRows = $orderRows;
    @endphp

    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]" data-dashboard-order-skeleton>
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">NoWhere Business</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $businessModule['title'] }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ $businessModule['description'] }}
                </p>
            </div>

            <nav class="mt-4 space-y-2">
                <a
                    href="{{ route('dashboard.business.nowhere') }}"
                    title="NoWhere 비즈니스 허브"
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                >
                    <span>NoWhere 허브</span>
                    <span class="text-xs">00</span>
                </a>

                @foreach ($businessModules as $businessModuleItem)
                    @if (! empty($businessModuleItem['href']))
                        <a
                            href="{{ $businessModuleItem['href'] }}"
                            title="{{ $businessModuleItem['title'] }}"
                            class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition {{ $businessModuleItem['key'] === $businessModule['key'] ? 'border-[#cfcfcf] bg-[#ececec] text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]' : 'border-[#d8d8d8] bg-[#f5f5f5] text-[#4f4f4f] hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]' }}"
                        >
                            <span>{{ $businessModuleItem['title'] }}</span>
                            <span class="text-xs">{{ strtoupper($businessModuleItem['key']) }}</span>
                        </a>
                    @else
                        <div
                            title="{{ $businessModuleItem['title'] }} 준비중"
                            aria-disabled="true"
                            class="flex items-center justify-between rounded-lg border border-dashed border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#8a8a8a] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#77797d]"
                        >
                            <span>{{ $businessModuleItem['title'] }}</span>
                            <span class="text-xs">{{ $businessModuleItem['status'] }}</span>
                        </div>
                    @endif
                @endforeach
            </nav>

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">현재 상태</p>
                <p class="mt-2 text-sm leading-6 text-gray-900 dark:text-gray-100">
                    오더 목록은 Single/Set 테이블로 구성되어 있으며, 등록·수정·취소는 상세 화면에서 이어집니다.
                </p>
            </div>
        </aside>

        <div class="space-y-4">
            <section class="page-panel">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Order Workspace</p>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">오더 워크스페이스</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-gray-500 dark:text-gray-400">
                            단일 오더(Single)와 셋트 오더(Set)를 한 화면에서 빠르게 판단하고 조작합니다.
                        </p>
                    </div>
                    <span class="status-badge">{{ $orders->count() }}건</span>
                </div>

                <section class="mt-6" data-dashboard-order-list>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">오더 목록</h3>
                        <div class="flex items-center gap-2">
                            <span class="status-badge">{{ $orders->count() }}건</span>
                            <div data-order-view-toggle></div>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('dashboard.business.order') }}" class="mt-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <input
                                id="order-search"
                                name="search"
                                type="text"
                                title="오더 검색"
                                class="input-field w-full sm:w-80"
                                placeholder="업체, 고객명, 경로 검색"
                                value="{{ $filters['search'] }}"
                            >
                            <a href="{{ route('dashboard.business.order.create') }}" class="btn-primary" title="오더 등록" aria-label="오더 등록">+ 오더 등록</a>
                        </div>
                    </form>

                    <div class="mt-6">
                        <div data-order-card-list></div>
                        <div class="hidden" data-order-datatable></div>
                    </div>

                    <script type="application/json" data-order-rows>
                        {!! json_encode($orderTableRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                    </script>
                </section>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
                <article class="page-panel panel-gray">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">선행 참조 데이터</p>
                    <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">Business Foundation 연결</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="meta-badge">Customer</span>
                        <span class="meta-badge">Company</span>
                        <span class="meta-badge">Vehicle</span>
                        <span class="meta-badge">Driver</span>
                        <span class="meta-badge">Common Code</span>
                    </div>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                        <li>오더는 직접 입력보다 선택 중심 구조로 시작합니다.</li>
                        <li>기초 데이터가 준비되면 검색, 필터, 상태 변경 흐름을 붙입니다.</li>
                        <li>배차와 정산은 오더 확정 데이터가 쌓인 뒤 연결합니다.</li>
                    </ul>
                </article>

                <article class="page-panel panel-gray">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">다음 확장</p>
                    <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">추가 예정 영역</h3>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-3 text-sm text-gray-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-100">1. SET · Single 묶음 조작</div>
                        <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-3 text-sm text-gray-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-100">2. 기사 · 차량 배차 연결</div>
                        <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] px-4 py-3 text-sm text-gray-900 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-100">3. 상태 변경 / 정산 연결</div>
                    </div>
                </article>
            </section>
        </div>
    </section>
</x-layouts.app>
