<x-layouts.app title="Toast UI Editor 테스트">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="surface-card surface-card--muted lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dashboard Module</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $module['title'] }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ $module['description'] }}
                </p>
            </div>

            <nav class="mt-4 space-y-2">
                <a
                    href="{{ route('dashboard') }}"
                    title="대시보드 허브"
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                >
                    <span>허브 홈</span>
                    <span class="text-xs">00</span>
                </a>

                @foreach ($modules as $moduleItem)
                    <a
                        href="{{ $moduleItem['href'] }}"
                        title="{{ $moduleItem['title'] }}"
                        class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition {{ $moduleItem['key'] === $module['key'] ? 'border-[#cfcfcf] bg-[#ececec] text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]' : 'border-[#d8d8d8] bg-[#f5f5f5] text-[#4f4f4f] hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]' }}"
                    >
                        <span>{{ $moduleItem['title'] }}</span>
                        <span class="text-xs">{{ $moduleItem['order'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="space-y-4">
            <section class="surface-card surface-card--raised">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Shared Editor Framework</p>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">Toast UI Editor / Viewer</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Live Test</p>
                </div>

                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">입력, 미리보기, 저장 문자열만 바로 확인할 수 있게 구성했습니다.</p>

                <div class="mt-5" data-toast-editor-playground data-base-markdown-editor-playground>
                    <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 text-sm text-gray-500 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-400">
                        에디터를 불러오는 중입니다. 화면이 비어 있으면 `npm run dev` 또는 `npm run build`를 확인하세요.
                    </div>
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
