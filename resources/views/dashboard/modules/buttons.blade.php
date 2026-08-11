<x-layouts.app title="버튼&아이콘">
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
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">버튼 & 아이콘 현황</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Vue + Blade</p>
                </div>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    현재 프로젝트에서 실제로 사용 중인 공용 버튼 유형을 한눈에 확인합니다.
                    중복되거나 빠진 유형을 파악하고 개선 방향을 정할 수 있습니다.
                </p>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">BaseButton (Vue)</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">Vue 공용 버튼 컴포넌트</h3>
                    </div>
                    <span class="meta-badge">Vue</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">variant</code>: primary / secondary / ghost / danger ·
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">size</code>: sm / md / lg
                </p>
                <div class="mt-4" data-button-playground></div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Blade Utility</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">Blade 유틸리티 버튼</h3>
                    </div>
                    <span class="meta-badge">Blade</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    Blade 템플릿에서 class만으로 사용하는 버튼 유형입니다.
                </p>

                <div class="mt-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">btn-primary</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">주요 액션 · 게시판 검색/저장/수정, 로그인 제출</p>
                        </div>
                        <button type="button" class="btn-primary" title="btn-primary" aria-label="btn-primary">btn-primary</button>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">btn-secondary</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">보조 액션 · 검색 초기화, 다이얼로그 닫기</p>
                        </div>
                        <button type="button" class="btn-secondary" title="btn-secondary" aria-label="btn-secondary">btn-secondary</button>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">action-button--primary</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">링크형 주요 액션 · 헤더 로그인, 인증 화면 제출/이동</p>
                        </div>
                        <a href="#" class="action-button action-button--primary" title="action-button--primary">로그인</a>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">action-button--secondary</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">링크형 보조 액션 · 헤더 홈/회원가입, 비밀번호 찾기</p>
                        </div>
                        <a href="#" class="action-button action-button--secondary" title="action-button--secondary">회원가입</a>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">icon-button</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">아이콘 전용 정사각 버튼 · 헤더 테마 토글</p>
                        </div>
                        <button type="button" class="icon-button" title="icon-button" aria-label="icon-button">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
                                <circle cx="12" cy="12" r="4" />
                                <path stroke-linecap="round" d="M12 2v2.2M12 19.8V22M4.93 4.93l1.55 1.55M17.52 17.52l1.55 1.55M2 12h2.2M19.8 12H22M4.93 19.07l1.55-1.55M17.52 6.48l1.55-1.55" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">shared-dropdown__icon-trigger</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">드롭다운 트리거(아이콘형) · 헤더 바로가기 메뉴</p>
                        </div>
                        <button type="button" class="btn-secondary shared-dropdown__icon-trigger" title="shared-dropdown__icon-trigger" aria-label="shared-dropdown__icon-trigger">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
                                <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">shared-table-actions__trigger</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">테이블 행 액션 트리거 · 오더/회원/게시판/파일/데이터테이블</p>
                        </div>
                        <button type="button" class="shared-table-actions__trigger" title="shared-table-actions__trigger" aria-label="shared-table-actions__trigger">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                <circle cx="12" cy="5" r="1.6" />
                                <circle cx="12" cy="12" r="1.6" />
                                <circle cx="12" cy="19" r="1.6" />
                            </svg>
                        </button>
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">BaseIcon (Vue)</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">공용 SVG 아이콘</h3>
                    </div>
                    <span class="meta-badge">Vue</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseIcon</code>에 정의되어 현재 사용 중인 SVG 아이콘 전체 목록입니다.
                </p>
                <div class="mt-4" data-icon-playground></div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Button States</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">상태 유형</h3>
                    </div>
                    <span class="meta-badge">상태</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    비활성(disabled)은 회색 배경/텍스트로, 처리 중(loading)은 스피너 아이콘과 함께 표현합니다.
                    의미가 명확한 공통 액션은 아이콘 단독 버튼(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">icon-button</code>)을 사용합니다.
                </p>
                <div class="mt-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">disabled</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">비활성 상태는 회색 배경/텍스트로 표현하며 클릭을 차단합니다.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn-primary" disabled title="저장(비활성)">저장</button>
                            <button type="button" class="btn-secondary" disabled title="취소(비활성)">취소</button>
                            <button type="button" class="icon-button" disabled title="새로고침(비활성)" aria-label="새로고침(비활성)">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" d="M20 6v5h-5" />
                                    <path stroke-linecap="round" d="M4 18v-5h5" />
                                    <path stroke-linecap="round" d="M6.5 9A7 7 0 0 1 18 11" />
                                    <path stroke-linecap="round" d="M17.5 15A7 7 0 0 1 6 13" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">loading</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">처리 중 상태는 스피너 아이콘을 함께 표시하고 입력을 차단합니다.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn-primary" disabled title="처리 중">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 animate-spin" aria-hidden="true">
                                    <path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9" />
                                </svg>
                                처리 중...
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">icon only</p>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">의미가 명확한 공통 액션은 아이콘 단독 버튼을 사용하고 title/aria-label을 제공합니다.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="icon-button" title="검색" aria-label="검색">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7" />
                                    <path stroke-linecap="round" d="M20 20l-3.5-3.5" />
                                </svg>
                            </button>
                            <button type="button" class="icon-button" title="새로고침" aria-label="새로고침">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" d="M20 6v5h-5" />
                                    <path stroke-linecap="round" d="M4 18v-5h5" />
                                    <path stroke-linecap="round" d="M6.5 9A7 7 0 0 1 18 11" />
                                    <path stroke-linecap="round" d="M17.5 15A7 7 0 0 1 6 13" />
                                </svg>
                            </button>
                            <button type="button" class="icon-button" title="다운로드" aria-label="다운로드">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" d="M12 4v12" />
                                    <path stroke-linecap="round" d="M7 11l5 5l5-5" />
                                    <path stroke-linecap="round" d="M4 20h16" />
                                </svg>
                            </button>
                            <button type="button" class="icon-button" title="추가" aria-label="추가">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" d="M12 5v14" />
                                    <path stroke-linecap="round" d="M5 12h14" />
                                </svg>
                            </button>
                        </div>
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
                        <span><code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">btn-primary</code> / <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">btn-secondary</code>(Blade)와 BaseButton의 primary/secondary가 사실상 동일 스타일 — 중복이므로 한쪽으로 통합 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span><code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">ghost</code> / <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">danger</code>는 Vue(BaseButton)에만 존재 — Blade용 유틸리티 클래스는 없음.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>액션 트리거(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">shared-dropdown__icon-trigger</code>, <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">shared-table-actions__trigger</code>)는 공용 버튼 모듈에서 관리되지 않고 개별 CSS로 분산되어 있음.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>크기 체계 불일치 — Blade btn-*는 44px 고정, BaseButton은 sm 36px / md 44px / lg 48px. 단일 크기 스펙으로 정리 필요.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>아이콘은 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseIcon</code>(Vue)에만 존재 — Blade 템플릿에서는 인라인 SVG를 직접 넣어야 하므로 Blade용 아이콘 컴포넌트(x-icon) 추가 검토.</span>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.app>
