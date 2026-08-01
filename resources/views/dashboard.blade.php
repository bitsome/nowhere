<x-layouts.app title="대시보드">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="surface-card surface-card--muted lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dashboard Hub</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">모듈 허브</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">기능별 페이지로 바로 이동합니다.</p>
            </div>

            <nav class="mt-4 space-y-2">
                <a
                    href="{{ route('dashboard') }}"
                    title="대시보드 허브"
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#1f1f1f] transition hover:bg-[#ededed] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#d6d6dd] dark:hover:bg-[#222222]"
                >
                    <span>허브 홈</span>
                    <span class="text-xs">00</span>
                </a>

                @foreach ($modules as $moduleItem)
                    <a
                        href="{{ $moduleItem['href'] }}"
                        title="{{ $moduleItem['title'] }}"
                        class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                    >
                        <span>{{ $moduleItem['title'] }}</span>
                        <span class="text-xs">{{ $moduleItem['order'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">계정</p>
                <p class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</p>
            </div>
        </aside>

        <div>
            <section class="surface-card surface-card--raised">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Modules</p>
                        <h2 class="mt-2 text-[28px] font-semibold text-gray-900 dark:text-gray-100">현재 테스트 가능한 페이지</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($modules) }}개 모듈</p>
                </div>

                <div class="mt-6 grid gap-3 xl:grid-cols-2">
                    @foreach ($modules as $moduleItem)
                        <a
                            href="{{ $moduleItem['href'] }}"
                            title="{{ $moduleItem['title'] }}"
                            class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 text-left transition hover:bg-[#ededed] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:hover:bg-[#202020]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $moduleItem['order'] }}</p>
                                    <p class="mt-2 truncate text-base font-semibold text-gray-900 dark:text-gray-100">{{ $moduleItem['title'] }}</p>
                                    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $moduleItem['description'] }}</p>
                                </div>
                                <span class="meta-badge">{{ $moduleItem['status'] }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
