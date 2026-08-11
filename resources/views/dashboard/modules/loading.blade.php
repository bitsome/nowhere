<x-layouts.app title="로딩·빈 상태">
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
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">로딩 · 빈 상태 유형 현황</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Vue + Blade</p>
                </div>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    공용 로딩(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseLoading</code>)의
                    인라인/전체 화면 유형과 빈 상태(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">datatable-empty</code>) 유형을 확인합니다.
                </p>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Inline Loading</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">인라인 로딩</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    영역 안에서 스피너 + 라벨로 진행 상태를 표현합니다. <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseLoading</code>의
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">inline</code> 모드와 동일한 규칙입니다.
                </p>
                <div class="mt-4 max-w-2xl space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">기본</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">스피너 + 기본 라벨(Loading...)</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-[#cfcfcf] border-t-[#555555] dark:border-[#343434] dark:border-t-[#d6d6dd]" aria-hidden="true"></span>
                            <span class="text-sm text-[#555555] dark:text-[#b9bbc0]">Loading...</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">라벨 지정</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400"><code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">label</code>로 문구를 지정합니다.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-[#cfcfcf] border-t-[#555555] dark:border-[#343434] dark:border-t-[#d6d6dd]" aria-hidden="true"></span>
                            <span class="text-sm text-[#555555] dark:text-[#b9bbc0]">오더 목록을 불러오는 중...</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Fullscreen Loading</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">전체 화면 로딩</h3>
                    </div>
                    <span class="meta-badge">Vue</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    화면 전체를 반투명 배경으로 덮고 중앙에 로딩 카드를 표시합니다.
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseLoading</code>의 기본(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">active</code>) 모드입니다.
                    아래 버튼으로 2초간 전체 화면 로딩을 확인해 보세요.
                </p>
                <div class="mt-4">
                    <button type="button" class="btn-primary" data-loading-toggle title="전체 화면 로딩 표시">전체 화면 로딩 표시</button>
                </div>
                <div data-loading-playground></div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Empty State</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">빈 상태</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    데이터가 없을 때 아이콘 + 제목 + 설명으로 상태를 안내합니다. 공통
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">DataTable</code>의
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">TableEmpty</code>(datatable-empty)에서 사용 중입니다.
                </p>
                <div class="mt-4 overflow-hidden rounded-[10px] border border-[#dddddd] dark:border-[#2a2a2a]">
                    <div class="datatable-empty">
                        <div class="datatable-empty__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                                <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
                                <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
                                <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
                                <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
                            </svg>
                        </div>
                        <p class="datatable-empty__title">데이터가 없습니다.</p>
                        <p class="datatable-empty__description">조건에 맞는 데이터가 없습니다. 필터를 조정하거나 새 항목을 등록해 주세요.</p>
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
                        <span><code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseLoading</code>의 인라인/전체 화면 두 모드를 한 컴포넌트가 처리 — 사용처에서는 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">inline</code> 여부만 구분해 사용.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>빈 상태는 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">datatable-empty</code>이 DataTable 전용으로 묶여 있음 — 목록 외 영역에서도 쓰이면 공통 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">EmptyState</code> 컴포넌트로 분리 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>로딩 스피너 색상은 그레이 계열 — 의미 색상 지정 전까지 유지하고, 지정 시 공통 CSS 한 곳에서만 수정하도록 구성.</span>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.app>
