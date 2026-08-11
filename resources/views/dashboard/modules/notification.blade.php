<x-layouts.app title="알림">
    <div data-notification-toast></div>
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]" data-dashboard-notification-test>
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dashboard Module</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $module['title'] }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ $module['description'] }}
                </p>
            </div>

            @include('dashboard.partials.sidebar-nav', ['modules' => $modules, 'module' => $module])

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">테스트 규칙</p>
                <p class="mt-2 text-sm leading-6 text-gray-900 dark:text-gray-100">
                    실제 서버 없이도 알림 흐름을 확인할 수 있도록 UI 테스트를 우선 유지합니다.
                </p>
            </div>
        </aside>

        <div class="space-y-4">
            <section class="grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
                <div class="page-panel">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">기능 테스트</p>
                            <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">Notification 테스트 워크스페이스</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-500 dark:text-gray-400">
                                실제 서버 연동 없이 테스트용 알림을 보내고 받고, 읽음 처리와 초기화 흐름을 확인합니다.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-secondary" title="샘플 알림 받기" aria-label="샘플 알림 받기" data-notification-receive>샘플 받기</button>
                            <button type="button" class="btn-secondary" title="모든 알림 읽음 처리" aria-label="모든 알림 읽음 처리" data-notification-read-all>모두 읽음</button>
                            <button type="button" class="btn-primary" title="알림 목록 초기화" aria-label="알림 목록 초기화" data-notification-reset>초기화</button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <article class="rounded-[10px] border border-[#dddddd] bg-[#f4f4f4] p-4 dark:border-[#2a2a2a] dark:bg-[#1d1d1d]">
                            <p class="text-sm text-gray-500 dark:text-gray-400">전체 알림</p>
                            <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-notification-total>0</p>
                        </article>
                        <article class="rounded-[10px] border border-[#dddddd] bg-[#f4f4f4] p-4 dark:border-[#2a2a2a] dark:bg-[#1d1d1d]">
                            <p class="text-sm text-gray-500 dark:text-gray-400">읽지 않음</p>
                            <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-notification-unread>0</p>
                        </article>
                        <article class="rounded-[10px] border border-[#dddddd] bg-[#f4f4f4] p-4 dark:border-[#2a2a2a] dark:bg-[#1d1d1d]">
                            <p class="text-sm text-gray-500 dark:text-gray-400">마지막 액션</p>
                            <p class="mt-3 text-sm font-medium leading-6 text-gray-900 dark:text-gray-100" data-notification-last-action>대기 중</p>
                        </article>
                    </div>

                    <form class="mt-6 grid gap-4 md:grid-cols-[minmax(0,1fr)_180px]" data-notification-form>
                        <div class="space-y-4">
                            <div>
                                <label for="notification-message" class="text-sm font-medium text-gray-900 dark:text-gray-100">알림 메시지</label>
                                <input
                                    id="notification-message"
                                    name="message"
                                    type="text"
                                    title="알림 메시지"
                                    class="input-field"
                                    placeholder="예: 새 오더가 등록되었습니다."
                                    required
                                >
                            </div>
                            <div>
                                <label for="notification-time" class="text-sm font-medium text-gray-900 dark:text-gray-100">표시 시간</label>
                                <input
                                    id="notification-time"
                                    name="time"
                                    type="text"
                                    title="표시 시간"
                                    class="input-field"
                                    placeholder="예: 방금 전"
                                >
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="notification-type" class="text-sm font-medium text-gray-900 dark:text-gray-100">알림 유형</label>
                                <select id="notification-type" name="type" title="알림 유형" class="input-field mt-2">
                                    <option value="success">SUCCESS</option>
                                    <option value="warning">WARNING</option>
                                    <option value="info" selected>INFO</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-primary w-full" title="알림 보내기" aria-label="알림 보내기">알림 보내기</button>
                        </div>
                    </form>
                </div>

                <aside class="page-panel panel-gray">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">테스트 로그</p>
                            <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">받은 알림 수신함</h3>
                        </div>
                        <span class="status-badge" data-notification-badge>0건</span>
                    </div>

                    <div class="mt-4 space-y-3" data-notification-list></div>

                    <div
                        class="mt-4 rounded-[10px] border border-dashed border-[#d8d8d8] bg-[#f7f7f7] px-4 py-8 text-center dark:border-[#2a2a2a] dark:bg-[#1a1a1a]"
                        data-notification-empty
                    >
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100">알림이 없습니다.</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">좌측 테스트 워크스페이스에서 알림을 보내거나 샘플 받기로 수신 테스트를 진행하세요.</p>
                    </div>
                </aside>
            </section>
        </div>
    </section>
</x-layouts.app>
