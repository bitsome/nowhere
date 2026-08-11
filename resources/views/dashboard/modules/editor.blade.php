<x-layouts.app title="에디터">
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
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">에디터 프레임워크</p>
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
