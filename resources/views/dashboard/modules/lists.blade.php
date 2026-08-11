<x-layouts.app title="리스트">
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
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">리스트 유형 현황</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Blade + Vue</p>
                </div>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    현재 프로젝트에서 실제 사용 중인 리스트 유형을 한눈에 확인합니다.
                    항목 행 높이 40px 내외 · hover 시 배경 한 단계 변화 · 아이콘은 행동 보조 용도로만 사용합니다.
                </p>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Basic List</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">기본 리스트</h3>
                    </div>
                    <span class="meta-badge">기본</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    구분선 없이 행만 나열하는 가장 단순한 형태입니다. 항목이 짧고 우선순위 구분이 필요 없을 때 사용합니다.
                </p>
                <div class="mt-4 grid gap-3 xl:grid-cols-2">
                    <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <ul class="space-y-3">
                            <li class="flex items-center justify-between gap-3 rounded-lg px-3">
                                <span class="text-sm text-gray-900 dark:text-gray-100">운영 매뉴얼</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">12건</span>
                            </li>
                            <li class="flex items-center justify-between gap-3 rounded-lg px-3">
                                <span class="text-sm text-gray-900 dark:text-gray-100">공지사항</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">3건</span>
                            </li>
                            <li class="flex items-center justify-between gap-3 rounded-lg px-3">
                                <span class="text-sm text-gray-900 dark:text-gray-100">자유게시판</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">8건</span>
                            </li>
                        </ul>
                    </div>
                    <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <ul class="space-y-3">
                            <li class="flex items-center justify-between gap-3 rounded-lg px-3">
                                <span class="text-sm text-gray-900 dark:text-gray-100">총 오더</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">128건</span>
                            </li>
                            <li class="flex items-center justify-between gap-3 rounded-lg px-3">
                                <span class="text-sm text-gray-900 dark:text-gray-100">배차 완료</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">96건</span>
                            </li>
                            <li class="flex items-center justify-between gap-3 rounded-lg px-3">
                                <span class="text-sm text-gray-900 dark:text-gray-100">배차 대기</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">32건</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Divided List</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">구분선 리스트</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    항목 사이를 얇은 구분선(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">divide-y</code>)으로 나눕니다.
                    항목이 많고 각 행이 독립 정보일 때 사용합니다.
                </p>
                <div class="mt-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <ul class="divide-y divide-[#e5e5e5] dark:divide-[#262626]">
                        <li class="flex items-center justify-between gap-3 px-3 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">서울 → 인천</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">기사 김민수 · 7인승</p>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">09:00</span>
                        </li>
                        <li class="flex items-center justify-between gap-3 px-3 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">인천 → 부천</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">기사 이정훈 · 5인승</p>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">11:30</span>
                        </li>
                        <li class="flex items-center justify-between gap-3 px-3 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">부천 → 서울</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">기사 박서연 · 5인승</p>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">14:00</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Icon List</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">아이콘 리스트</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    행 앞에 아이콘을 두고 라벨 옆에 단축 번호/값을 배치합니다.
                    사이드바(AppSidebar)와 드롭다운 메뉴(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">shared-dropdown__item</code>)에서 사용 중입니다.
                </p>
                <div class="mt-4 grid gap-3 xl:grid-cols-2">
                    <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <nav class="space-y-1">
                            <a href="#" title="회원관리" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true">
                                    <circle cx="12" cy="8" r="3.4" />
                                    <path stroke-linecap="round" d="M4.5 19.5c1.2-3.2 4-4.5 7.5-4.5s6.3 1.3 7.5 4.5" />
                                </svg>
                                <span>회원관리</span>
                                <span class="text-xs">02</span>
                            </a>
                            <a href="#" title="게시판" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true">
                                    <path stroke-linecap="round" d="M4 5.5h16v13H4z" />
                                    <path stroke-linecap="round" d="M4 9.5h16" />
                                </svg>
                                <span>게시판</span>
                                <span class="text-xs">03</span>
                            </a>
                            <a href="#" title="파일관리" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true">
                                    <path stroke-linecap="round" d="M6 3.5h7l4 4v13H6z" />
                                    <path stroke-linecap="round" d="M13 3.5v4h4" />
                                </svg>
                                <span>파일관리</span>
                                <span class="text-xs">05</span>
                            </a>
                        </nav>
                    </div>
                    <div class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <ul class="space-y-1">
                            <li>
                                <button type="button" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]" title="상세보기">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true">
                                        <path stroke-linecap="round" d="M3 12c1.8-3 4.6-4.5 9-4.5s7.2 1.5 9 4.5c-1.8 3-4.6 4.5-9 4.5s-7.2-1.5-9-4.5z" />
                                        <circle cx="12" cy="12" r="2.4" />
                                    </svg>
                                    <span>상세보기</span>
                                </button>
                            </li>
                            <li>
                                <button type="button" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]" title="수정">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true">
                                        <path stroke-linecap="round" d="M14.5 4.5l5 5L8 21H3v-5z" />
                                    </svg>
                                    <span>수정</span>
                                </button>
                            </li>
                            <li>
                                <button type="button" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-[#512727] transition hover:bg-[#f3e9e9] dark:text-[#c47070] dark:hover:bg-[#2a1d1d]" title="삭제">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true">
                                        <path stroke-linecap="round" d="M4 6.5h16M9.5 6.5V4.5h5v2M7 6.5l1 14h8l1-14" />
                                    </svg>
                                    <span>삭제</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status List</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">상태 리스트</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    행 오른쪽에 상태 배지(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">meta-badge</code>)를 배치합니다.
                    대시보드 허브의 하위 모듈 링크에서 사용 중입니다. 그레이스케일만으로 상태를 표현합니다.
                </p>
                <div class="mt-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <ul class="space-y-1">
                        <li>
                            <a href="#" title="알림" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]">
                                <span>알림</span>
                                <span class="meta-badge">Active</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" title="회원관리" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]">
                                <span>회원관리</span>
                                <span class="meta-badge">Active</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" title="NoWhere 비즈니스 허브" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]">
                                <span>NoWhere 비즈니스 허브</span>
                                <span class="meta-badge">Ready</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Hover Action List</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">호버 액션 리스트</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    hover 시 행 배경이 한 단계 변하고, 오른쪽 점 3개 액션 트리거가 나타납니다.
                    테이블 행의 세로 점 3개(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">shared-table-actions__trigger</code>)와 동일한 규칙입니다.
                </p>
                <div class="mt-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <ul class="space-y-1">
                        <li class="group flex items-center justify-between gap-3 rounded-lg px-3 py-2 transition hover:bg-[#ededed] dark:hover:bg-[#222222]">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">오더 #20260803-001</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">서울 → 인천 · 09:00</p>
                            </div>
                            <span class="md:opacity-0 md:group-hover:opacity-100">
                                <button type="button" class="shared-table-actions__trigger" title="행 액션" aria-label="행 액션">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <circle cx="12" cy="5" r="1.6" />
                                        <circle cx="12" cy="12" r="1.6" />
                                        <circle cx="12" cy="19" r="1.6" />
                                    </svg>
                                </button>
                            </span>
                        </li>
                        <li class="group flex items-center justify-between gap-3 rounded-lg px-3 py-2 transition hover:bg-[#ededed] dark:hover:bg-[#222222]">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">오더 #20260803-002</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">인천 → 부천 · 11:30</p>
                            </div>
                            <span class="md:opacity-0 md:group-hover:opacity-100">
                                <button type="button" class="shared-table-actions__trigger" title="행 액션" aria-label="행 액션">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <circle cx="12" cy="5" r="1.6" />
                                        <circle cx="12" cy="12" r="1.6" />
                                        <circle cx="12" cy="19" r="1.6" />
                                    </svg>
                                </button>
                            </span>
                        </li>
                        <li class="group flex items-center justify-between gap-3 rounded-lg px-3 py-2 transition hover:bg-[#ededed] dark:hover:bg-[#222222]">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">오더 #20260803-003</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">부천 → 서울 · 14:00</p>
                            </div>
                            <span class="md:opacity-0 md:group-hover:opacity-100">
                                <button type="button" class="shared-table-actions__trigger" title="행 액션" aria-label="행 액션">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <circle cx="12" cy="5" r="1.6" />
                                        <circle cx="12" cy="12" r="1.6" />
                                        <circle cx="12" cy="19" r="1.6" />
                                    </svg>
                                </button>
                            </span>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Notification List</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">알림 리스트</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    도트(타입 표시) + 메시지 + 시간 구조입니다. 헤더 알림 드롭다운
                    (<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">notification-item</code>)에서 사용 중입니다.
                </p>
                <div class="mt-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <ul class="space-y-1">
                        <li class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 transition hover:bg-[#ededed] dark:hover:bg-[#222222]">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-[#8b8b8b] dark:bg-[#6d6d6d]" aria-hidden="true"></span>
                                <span class="truncate text-sm text-gray-900 dark:text-gray-100">새 오더가 등록되었습니다.</span>
                            </span>
                            <time class="shrink-0 text-xs text-gray-500 dark:text-gray-400">방금 전</time>
                        </li>
                        <li class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 transition hover:bg-[#ededed] dark:hover:bg-[#222222]">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-[#8b8b8b] dark:bg-[#6d6d6d]" aria-hidden="true"></span>
                                <span class="truncate text-sm text-gray-900 dark:text-gray-100">배차가 완료되었습니다.</span>
                            </span>
                            <time class="shrink-0 text-xs text-gray-500 dark:text-gray-400">10분 전</time>
                        </li>
                        <li class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 transition hover:bg-[#ededed] dark:hover:bg-[#222222]">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-[#e5e5e5] dark:bg-[#3a3a3a]" aria-hidden="true"></span>
                                <span class="truncate text-sm text-gray-500 dark:text-gray-400">주간 정산 보고서가 준비되었습니다.</span>
                            </span>
                            <time class="shrink-0 text-xs text-gray-500 dark:text-gray-400">1시간 전</time>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Check List</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">체크 리스트</h3>
                    </div>
                    <span class="meta-badge">구조</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    체크박스 + 라벨 구조로 다중 선택이 필요한 작업(오더 묶음 생성, 일괄 배차)에 사용합니다.
                    선택 상태는 체크박스로만 표현하고 행 hover는 기본 규칙을 따릅니다.
                </p>
                <div class="mt-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-2 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <ul class="space-y-1">
                        <li>
                            <label class="flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-[#ededed] dark:hover:bg-[#222222]" title="오더 #20260803-001 선택">
                                <input type="checkbox" class="h-4 w-4 shrink-0 rounded border border-[#cfcfcf]" title="오더 #20260803-001 선택">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">오더 #20260803-001</span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">서울 → 인천 · 09:00</span>
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">45,000원</span>
                            </label>
                        </li>
                        <li>
                            <label class="flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-[#ededed] dark:hover:bg-[#222222]" title="오더 #20260803-002 선택">
                                <input type="checkbox" class="h-4 w-4 shrink-0 rounded border border-[#cfcfcf]" title="오더 #20260803-002 선택">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">오더 #20260803-002</span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">인천 → 부천 · 11:30</span>
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">28,000원</span>
                            </label>
                        </li>
                        <li>
                            <label class="flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-[#ededed] dark:hover:bg-[#222222]" title="오더 #20260803-003 선택">
                                <input type="checkbox" class="h-4 w-4 shrink-0 rounded border border-[#cfcfcf]" title="오더 #20260803-003 선택">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">오더 #20260803-003</span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">부천 → 서울 · 14:00</span>
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">36,000원</span>
                            </label>
                        </li>
                    </ul>
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
                        <span>리스트 행은 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">hover:bg-[#ededed]</code> / <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">hover:bg-[#222222]</code> 패턴이 여러 화면에서 반복 — 공용 리스트 행 클래스로 분리 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>아이콘 리스트와 사이드바 내비게이션, 드롭다운 메뉴가 동일한 행 구조 — 하나의 공통 리스트 컴포넌트(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">List</code> / <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">ListItem</code>)로 통합할 수 있는지 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>호버 액션 리스트의 점 3개 트리거는 테이블(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">shared-table-actions__trigger</code>)과 규칙 공유 — 좌표/정렬 처리를 공통화할 수 있는지 검토.</span>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.app>
