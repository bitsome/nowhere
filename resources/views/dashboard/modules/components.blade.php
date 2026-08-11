<x-layouts.app title="컴포넌트">
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
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">공통 컴포넌트</p>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">공통 컴포넌트 테스트</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($module['children']) }}개 하위 페이지</p>
                </div>

                <div class="mt-6 grid gap-3 xl:grid-cols-2">
                    @foreach ($module['children'] as $child)
                        <a
                            href="{{ $child['href'] }}"
                            title="{{ $child['title'] }}"
                            class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 text-left transition hover:bg-[#ededed] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:hover:bg-[#202020]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $child['order'] }}</p>
                                    <p class="mt-2 truncate text-base font-semibold text-gray-900 dark:text-gray-100">{{ $child['title'] }}</p>
                                    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $child['description'] }}</p>
                                </div>
                                <span class="meta-badge">{{ $child['status'] }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
