<x-layouts.app title="다이얼로그">
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
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">오버레이 컴포넌트</p>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">Dialog Playground</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Vue + Blade</p>
                </div>

                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    파괴적 작업 전 확인 문구를 노출하는 공통 다이얼로그입니다. Vue(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseDialog</code>)와 Blade(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">x-dialog</code>) 두 구현체가 같은 스타일을 공유합니다.
                </p>

                <div class="mt-6" data-dialog-playground></div>

                <div class="mt-6 page-panel">
                    <h3 class="text-sm font-semibold">Blade x-dialog 샘플</h3>
                    <p class="mt-1 text-sm text-gray-500">서버 사이드에서 렌더되는 다이얼로그는 data 속성으로 열고 닫습니다.</p>

                    <div class="mt-4">
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-4 text-sm font-medium text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                            data-dialog-open="#blade-sample-dialog"
                            title="Blade 다이얼로그 열기"
                        >
                            Blade 다이얼로그 열기
                        </button>
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dialog Types</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">크기 · 확인만 유형</h3>
                    </div>
                    <span class="meta-badge">Blade</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">x-dialog</code>의
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">size</code>(sm 기본 / md)와
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">show-cancel="false"</code>(확인만) 변형을 확인합니다.
                    열기/닫기는 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">data-dialog-open</code> 속성으로 제어합니다.
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" data-dialog-open="#dialog-sm-sample" title="기본 크기 다이얼로그 열기">기본(sm)</button>
                    <button type="button" class="btn-secondary" data-dialog-open="#dialog-md-sample" title="중간 크기 다이얼로그 열기">중간(md)</button>
                    <button type="button" class="btn-primary" data-dialog-open="#dialog-confirm-only" title="확인만 다이얼로그 열기">확인만</button>
                </div>
            </section>
        </div>
    </section>

    <x-dialog
        id="blade-sample-dialog"
        title="Blade 다이얼로그"
        description="이 다이얼로그는 Blade 컴포넌트로 렌더되었습니다."
        confirm-label="확인"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">data-dialog-open / data-dialog-close / data-dialog-confirm 속성으로 제어합니다.</p>
    </x-dialog>

    <x-dialog
        id="dialog-sm-sample"
        title="기본 크기 다이얼로그"
        description="size 기본값(sm)이 적용된 다이얼로그입니다."
        confirm-label="확인"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">기본 폭 420px, 확인/취소 푸터를 제공합니다.</p>
    </x-dialog>

    <x-dialog
        id="dialog-md-sample"
        size="md"
        title="중간 크기 다이얼로그"
        description="size md가 적용된 다이얼로그입니다."
        confirm-label="저장"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">폭 540px로 넓은 본문이 필요할 때 사용합니다.</p>
    </x-dialog>

    <x-dialog
        id="dialog-confirm-only"
        title="확인만 다이얼로그"
        description="show-cancel=false로 취소 버튼 없이 확인만 제공합니다."
        :show-cancel="false"
        confirm-label="확인"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">단일 확인 흐름(정보 안내 등)에 사용합니다.</p>
    </x-dialog>
</x-layouts.app>
