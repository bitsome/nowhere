<x-layouts.app title="드롭다운">
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
            <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="page-panel panel-dark">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">공통 컴포넌트</p>
                    <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">Dropdown Playground</h2>
                    <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                        공통 드롭다운 컴포넌트를 이용해 대시보드, 프로필, 로그아웃 액션을 하나의 메뉴로 테스트합니다.
                    </p>

                    <div
                        class="mt-6"
                        data-dropdown-playground
                        data-trigger-label="바로가기 메뉴"
                        data-title="Dashboard Menu"
                        data-description="{{ auth()->user()->email }}"
                        data-dashboard-url="{{ route('dashboard') }}"
                        data-notification-url="{{ route('dashboard.modules.notification') }}"
                        data-profile-url="{{ route('profile.edit') }}"
                        data-logout-url="{{ route('logout') }}"
                        data-csrf-token="{{ csrf_token() }}"
                    ></div>
                </div>

                <aside class="page-panel panel-gray">
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">테스트 체크리스트</p>
                    <div class="mt-4 space-y-3">
                        <article class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">1. 트리거 버튼</p>
                            <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">아이콘 기반 트리거와 title/aria-label이 정상 적용되는지 확인합니다.</p>
                        </article>
                        <article class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">2. 메뉴 항목</p>
                            <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">메뉴 항목이 아이콘 + 텍스트 형태로 동작하는지 확인합니다.</p>
                        </article>
                        <article class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">3. 링크 이동/로그아웃</p>
                            <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">대시보드 이동, 프로필 이동, 로그아웃 POST 요청이 정상 동작하는지 확인합니다.</p>
                        </article>
                    </div>
                </aside>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Trigger Types</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">클릭 트리거 유형</h3>
                    </div>
                    <span class="meta-badge">Vue</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    드롭다운은 클릭으로 열고 닫습니다. 트리거는 아이콘, 버튼, 텍스트 형태를 사용할 수 있으며
                    각 트리거는 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">title</code>/<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">aria-label</code>을 제공합니다.
                </p>
                <div class="mt-4" data-dropdown-types-playground="trigger"></div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Position</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">메뉴 포지션</h3>
                    </div>
                    <span class="meta-badge">Vue</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">align</code> 속성으로 메뉴를 트리거 왼쪽(
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">left</code>) 또는 오른쪽(
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">right</code>) 끝에 정렬합니다. 기본값은 right입니다.
                </p>
                <div class="mt-4" data-dropdown-types-playground="position"></div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Menu Structure</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">헤더 · 구분선 · danger 항목</h3>
                    </div>
                    <span class="meta-badge">Vue</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    메뉴는 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">DropdownHeader</code>(제목/설명),
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">DropdownDivider</code>,
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">DropdownItem</code>(아이콘 + 텍스트, danger 변형)로 구성합니다.
                    공용 액션 메뉴(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">DropdownActions</code>)의 items 구조와 동일한 규칙입니다.
                </p>
                <div class="mt-4" data-dropdown-types-playground="structure"></div>
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
                        <span>트리거는 현재 클릭 기반만 지원 — hover 트리거는 공용 컴포넌트에 없으므로, 필요 시 확장 전에 사용처 요구를 먼저 정리해야 합니다.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>메뉴 폭은 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">width</code> prop(기본 240px)으로 제어 — 항목이 길어지면 260px 이상으로 조정하고, 헤더/아이템 패딩과 함께 관리합니다.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span><code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">DropdownHeader</code>/<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">DropdownDivider</code>는 현재 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">DropdownActions</code>의 items 구조에서만 사용 — 개별 조합 사용처가 늘면 공통 구조로 정리 검토.</span>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.app>
