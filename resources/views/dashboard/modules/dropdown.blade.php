<x-layouts.app title="Shared Dropdown 테스트">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
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
            <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="page-panel panel-dark">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Shared Component Test</p>
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
        </div>
    </section>
</x-layouts.app>
