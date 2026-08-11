<x-layouts.app title="카드">
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
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">카드 유형 현황</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Blade</p>
                </div>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    현재 프로젝트에서 실제 사용 중인 카드 유형을 한눈에 확인합니다.
                    카드 공통 규칙은 테두리 1px · radius 10px · 그림자 없음 · padding 16px 입니다.
                </p>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Basic Card</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">기본 카드</h3>
                    </div>
                    <span class="meta-badge">surface</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">surface-card</code> 계열 —
                    라이트 모드는 주변보다 한 단계 밝은 중립 회색, 다크 모드는 깊은 다크그레이 레이어를 사용합니다.
                </p>
                <div class="mt-4 grid gap-3 xl:grid-cols-3">
                    <article class="surface-card">
                        <p class="text-xs text-gray-500 dark:text-gray-400">default</p>
                        <h4 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">기본 표면</h4>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            가장 널리 쓰이는 카드 표면입니다. 정보 묶음 하나를 담습니다.
                        </p>
                    </article>
                    <article class="surface-card surface-card--muted">
                        <p class="text-xs text-gray-500 dark:text-gray-400">muted</p>
                        <h4 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">보조 표면</h4>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            사이드 패널처럼 본문보다 낮은 중요도의 영역에 사용합니다.
                        </p>
                    </article>
                    <article class="surface-card surface-card--raised">
                        <p class="text-xs text-gray-500 dark:text-gray-400">raised</p>
                        <h4 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">강조 표면</h4>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            한 단계 더 채워진 표면으로 화면 시작 부분의 소개 영역에 사용합니다.
                        </p>
                    </article>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Stat Card</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">통계 카드</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    라벨 + 큰 값 + 설명 구조로 숫자 중심의 현황을 전달합니다.
                    파일관리 모듈에서 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">stat-card</code>로 사용 중입니다.
                </p>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <article class="stat-card">
                        <p class="text-sm text-gray-500 dark:text-gray-400">오늘 오더</p>
                        <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-gray-100">128건</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">어제 대비 12건 증가</p>
                    </article>
                    <article class="stat-card">
                        <p class="text-sm text-gray-500 dark:text-gray-400">배차 완료</p>
                        <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-gray-100">96건</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">전체의 75% 처리</p>
                    </article>
                    <article class="stat-card">
                        <p class="text-sm text-gray-500 dark:text-gray-400">대기 오더</p>
                        <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-gray-100">32건</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">가장 오래된 40분 대기</p>
                    </article>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Summary Card</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">요약 카드</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    라벨 + 요약 값 + 설명 구조로 항목별 기준 정보를 전달합니다.
                    홈(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">summary-card</code>)에서 사용 중입니다.
                </p>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <article class="summary-card">
                        <p class="text-sm text-gray-500 dark:text-gray-400">인증 방식</p>
                        <p class="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">Session Auth</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">Laravel 기본 세션 인증을 사용합니다.</p>
                    </article>
                    <article class="summary-card">
                        <p class="text-sm text-gray-500 dark:text-gray-400">UI 기준</p>
                        <p class="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">Business First</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">단순하고 일관된 업무용 UI를 우선합니다.</p>
                    </article>
                    <article class="summary-card">
                        <p class="text-sm text-gray-500 dark:text-gray-400">문서화</p>
                        <p class="mt-3 text-xl font-semibold text-gray-900 dark:text-gray-100">Docs Driven</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">운영 규칙과 아키텍처 문서를 기준으로 개발합니다.</p>
                    </article>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">List Card</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">리스트 카드</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    헤더 + 구분선(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">ui-divider</code>) 항목 구조로
                    상태 항목들을 나열할 때 사용합니다. 홈 대시보드에서 사용 중입니다.
                </p>
                <div class="mt-4 grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <article class="surface-card">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">현재 상태</h4>
                        <div class="mt-4 space-y-4">
                            <div class="ui-divider border-b pb-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">인증</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">세션 기반 로그인과 로그아웃 흐름이 구성되어 있습니다.</p>
                            </div>
                            <div class="ui-divider border-b pb-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">프론트엔드</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Blade와 plain CSS 기반의 업무용 UI 규칙을 적용합니다.</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">다음 작업</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">오더, 배차, 정산 운영 기능 확장이 예정되어 있습니다.</p>
                            </div>
                        </div>
                    </article>
                    <article class="surface-card">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">오늘 배차</h4>
                        <div class="mt-4 space-y-4">
                            <div class="ui-divider border-b pb-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">서울 → 인천</p>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">09:00</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">기사 김민수 · 7인승</p>
                            </div>
                            <div class="ui-divider border-b pb-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">인천 → 부천</p>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">11:30</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">기사 이정훈 · 5인승</p>
                            </div>
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">부천 → 서울</p>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">14:00</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">기사 박서연 · 5인승</p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Action Card</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">액션 카드</h3>
                    </div>
                    <span class="meta-badge">구조</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    헤더 + 본문 + 푸터(버튼) 구조입니다. 푸터는 구분선으로 분리하고 액션은 오른쪽 정렬합니다.
                    Vue 공용 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseCard</code>의 footer 슬롯과 동일한 규칙입니다.
                </p>
                <div class="mt-4 grid gap-3 xl:grid-cols-2">
                    <article class="surface-card">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">기사 등록</h4>
                                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                    새 기사를 등록하려면 등록 화면에서 시작합니다.
                                </p>
                            </div>
                            <span class="meta-badge">Operator</span>
                        </div>
                        <div class="mt-4 flex items-center justify-end gap-2 border-t border-[#dddddd] pt-4 dark:border-[#2a2a2a]">
                            <button type="button" class="btn-secondary" title="등록 취소">취소</button>
                            <button type="button" class="btn-primary" title="기사 등록">등록</button>
                        </div>
                    </article>
                    <article class="surface-card">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">정산 확인</h4>
                                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                    확정된 오더와 배차 데이터를 기준으로 정산 내역을 확인합니다.
                                </p>
                            </div>
                            <span class="meta-badge">Admin</span>
                        </div>
                        <div class="mt-4 flex items-center justify-end gap-2 border-t border-[#dddddd] pt-4 dark:border-[#2a2a2a]">
                            <button type="button" class="btn-secondary" title="정산 내역 내보내기">내보내기</button>
                            <button type="button" class="btn-primary" title="정산 확정">확정</button>
                        </div>
                    </article>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Clickable Card</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">클릭 카드</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    전체가 링크인 카드입니다. hover 시 배경과 보더가 한 단계 어두워져 클릭 가능함을 전달합니다.
                    대시보드 허브의 모듈 카드에서 사용 중입니다.
                </p>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <a
                        href="{{ route('dashboard.modules.dropdown') }}"
                        title="드롭다운 모듈로 이동"
                        class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 text-left transition hover:bg-[#ededed] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:hover:bg-[#202020]"
                    >
                        <p class="text-xs text-gray-500 dark:text-gray-400">06-1</p>
                        <p class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">드롭다운</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">공통 메뉴와 바로가기 액션 조합을 확인합니다.</p>
                    </a>
                    <a
                        href="{{ route('dashboard.modules.datatable') }}"
                        title="데이터 테이블 모듈로 이동"
                        class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 text-left transition hover:bg-[#ededed] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:hover:bg-[#202020]"
                    >
                        <p class="text-xs text-gray-500 dark:text-gray-400">06-2</p>
                        <p class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">데이터 테이블</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">컬럼, 검색, 필터, 빈 상태를 확인합니다.</p>
                    </a>
                    <a
                        href="{{ route('dashboard.modules.editor') }}"
                        title="에디터 모듈로 이동"
                        class="rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 text-left transition hover:bg-[#ededed] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:hover:bg-[#202020]"
                    >
                        <p class="text-xs text-gray-500 dark:text-gray-400">06-3</p>
                        <p class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">에디터</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">Markdown 입력과 미리보기 흐름을 확인합니다.</p>
                    </a>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status Card</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">상태 카드</h3>
                    </div>
                    <span class="meta-badge">구조</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    상태(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">meta-badge</code>)를
                    카드 상단에 배치해 한눈에 판단할 수 있게 합니다. 그레이스케일만으로 상태 차이를 표현합니다.
                </p>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <article class="surface-card">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">알림 모듈</h4>
                            <span class="meta-badge">Active</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">알림 보내기와 읽음 처리 흐름이 준비되었습니다.</p>
                    </article>
                    <article class="surface-card">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">배차 관리</h4>
                            <span class="meta-badge">준비중</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">오더, 기사, 차량 기준 정리 후 설계합니다.</p>
                    </article>
                    <article class="surface-card">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">정산 관리</h4>
                            <span class="meta-badge">대기</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">오더와 배차 확정 데이터가 쌓인 뒤 연결합니다.</p>
                    </article>
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
                        <span><code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">stat-card</code> / <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">summary-card</code>는 동일한 surface 스타일을 공유 — 통계/요약 구분을 클래스가 아닌 내용으로 유지 중.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>클릭 카드, 리스트 카드, 액션 카드는 조합형이므로 공용 CSS 클래스 없이 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">surface-card</code> + 유틸리티로 구성 — 반복되기 시작하면 전용 카드 클래스 분리 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>Vue의 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseCard</code>와 Blade의 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">surface-card</code>가 동일한 디자인 규칙 — 양쪽 구조를 한 곳에서 관리할 수 있는지 검토.</span>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.app>
