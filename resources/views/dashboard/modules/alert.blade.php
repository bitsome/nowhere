<x-layouts.app title="알림 배너">
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
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">알림 배너 유형 현황</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Blade</p>
                </div>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    페이지 안에 인라인으로 표시되는 공통 알림 배너(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">x-alert</code>)의
                    유형과 구조를 확인합니다. 토스트(우측 상단 팝업)와 달리 화면 흐름 안에 남는 피드백입니다.
                </p>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Alert Types</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">알림 배너 유형</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">variant</code>은
                    info(안내) / success(완료) / warning(경고) / error(오류) 네 가지를 지원합니다.
                    의미 색상 지정 전까지는 그레이스케일로 유지하고 아이콘으로만 구분합니다.
                </p>
                <div class="mt-4 max-w-3xl space-y-3">
                    <x-alert variant="info" title="안내" message="새 오더가 등록되었습니다. 배차 대기 목록에서 확인할 수 있습니다." />
                    <x-alert variant="success" title="완료" message="오더가 성공적으로 저장되었습니다." />
                    <x-alert variant="warning" title="경고" message="배차 마감 시간이 30분 남았습니다." />
                    <x-alert variant="error" title="오류 발생" message="오더 저장에 실패했습니다. 입력값을 확인해 주세요." />
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Alert Structure</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">알림 배너 구조</h3>
                    </div>
                    <span class="meta-badge">구조</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    아이콘 + 제목 + 메시지 + (닫기) 구조입니다. 슬롯으로 제목/메시지 외에 목록이나 버튼을 넣을 수 있으며,
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">dismissible</code> 속성으로 닫기 버튼을 표시합니다.
                </p>
                <div class="mt-4 max-w-3xl space-y-3">
                    <x-alert variant="error" title="입력값을 확인하세요." class="mb-4" dismissible>
                        <ul class="space-y-1">
                            <li>예약 회사는 필수 입력입니다.</li>
                            <li>서비스 날짜는 오늘 이후여야 합니다.</li>
                            <li>탑승 인원은 1명 이상이어야 합니다.</li>
                        </ul>
                    </x-alert>

                    <x-alert variant="info" dismissible>
                        이 알림은 닫기 버튼으로 사라집니다. 닫기 동작은 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">data-alert-close</code>로 처리합니다.
                    </x-alert>
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
                        <span>알림 배너는 Blade(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">x-alert</code>) 구현 — Vue에서도 필요해지면 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseAlert</code>로 동일 구조를 공유하도록 확장 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>타입 구분은 그레이스케일 + 아이콘 — 의미 색상 지정 시 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">alerts.css</code> 한 곳에서만 수정하도록 구성.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>토스트와 알림 배너는 같은 피드백 항목이지만 표시 방식(팝업/인라인)이 다름 — 사용처 규칙을 정리해 한쪽으로 치우치지 않게 운영.</span>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.app>
