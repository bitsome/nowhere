<x-layouts.app title="탭 메뉴">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
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
            <section class="page-panel panel-dark">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">공통 컴포넌트</p>
                <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">TabMenu</h2>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    items(라벨·URL·활성·개수)만 넘기면 렌더링되는 공용 탭 메뉴입니다.
                    클릭 시 URL로 이동하며, 개수 배지를 함께 표시할 수 있습니다.
                </p>

                <div class="mt-6" data-order-tabs data-tabs='@json($demoTabs, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT)'></div>
            </section>

            <section class="page-panel">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">공통 컴포넌트</p>
                <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">ViewToggle</h2>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    그리드·리스트 보기 전환. 텍스트 + 파이프 구분자, 테두리·배경 없이 심플하게 동작합니다.
                </p>

                <div class="mt-6" data-view-toggle-demo-group>
                    <div data-view-toggle-demo></div>

                    <div class="mt-6 grid gap-3 lg:grid-cols-2">
                        <div class="rounded-lg border border-[#dddddd] bg-[#f7f7f7] p-4 text-sm text-gray-600 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-300" data-card-view>
                            그리드(카드) 보기
                        </div>
                        <div class="hidden rounded-lg border border-[#dddddd] bg-[#f7f7f7] p-4 text-sm text-gray-600 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-300" data-list-view>
                            리스트(테이블) 보기
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
