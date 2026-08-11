<x-layouts.app title="모달">
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
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">공용 모달 현황</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Vue</p>
                </div>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    현재 사용 중인 공용 모달(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseModal</code>)을
                    실제 사용처 스타일로 확인합니다. 아래 버튼을 눌러 크기별 모달을 열어보세요.
                </p>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">BaseModal (Vue)</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">공용 모달 컴포넌트</h3>
                    </div>
                    <span class="meta-badge">Vue</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">size</code>: sm / md / lg / xl ·
                    헤더(eyebrow/title/description) · 본문(slot) · 푸터(slot)
                </p>
                <div class="mt-4" data-modal-playground></div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Modal Structure</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">모달 구조 유형</h3>
                    </div>
                    <span class="meta-badge">구조</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseModal</code>의 구조는
                    헤더(eyebrow/title/description + 닫기) · 본문(body) · 푸터(footer)로 나뉩니다.
                    헤더와 푸터는 유지한 채 본문만 스크롤되며, 배경 클릭/ESC 닫기는
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">close-on-backdrop</code>으로 제어합니다.
                </p>
                <div class="mt-4 max-w-3xl overflow-hidden rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <div class="flex items-start justify-between gap-3 border-b border-[#dddddd] p-5 dark:border-[#2a2a2a]">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#6a6a6a] dark:text-[#9ea1a8]">eyebrow</p>
                            <h4 class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">title</h4>
                            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">description — 모달의 용도와 주요 정보를 설명합니다.</p>
                        </div>
                        <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#6a6a6a] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#9ea1a8] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]" title="닫기" aria-label="닫기">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="max-h-[180px] overflow-y-auto p-5">
                        <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                            body — 본문 영역입니다. 내용이 길어지면 이 영역만 스크롤됩니다. 스크롤바는 프로젝트 공통 스타일을 따릅니다.
                        </p>
                        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            오더, 파일, 회원 상세처럼 본문이 긴 내용을 표시할 때 헤더/푸터를 고정하고 본문만 스크롤하는 구조를 사용합니다.
                        </p>
                        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            닫기 버튼(헤더 우측)은 항상 동일 위치에 두고, 푸터의 주요 액션은 오른쪽 정렬을 유지합니다.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-[#dddddd] p-4 dark:border-[#2a2a2a]">
                        <button type="button" class="btn-secondary" title="닫기">닫기</button>
                        <button type="button" class="btn-primary" title="확인">확인</button>
                    </div>
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
                        <span><code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseModal</code>은 Vue 전용 — Blade 템플릿에서는 별도 모달 컴포넌트가 없음. Blade용 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">x-modal</code> 추가 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>모달과 다이얼로그(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseDialog</code>)가 별도 컴포넌트로 분리되어 있음 — 공용 오버레이로 통합할지 결정 필요.</span>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.app>
