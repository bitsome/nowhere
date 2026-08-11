@php
    $sampleCardClass = 'surface-card';
    $sampleTableWrapClass = 'mt-4 overflow-x-auto rounded-[10px] border border-[#dddddd] dark:border-[#2a2a2a]';
    $sampleTableClass = 'min-w-full border-collapse bg-[#f7f7f7] dark:bg-[#1a1a1a]';
    $sampleHeadCellClass = 'bg-[#efefef] px-4 py-3 text-sm font-semibold text-[#1f1f1f] dark:bg-[#181818] dark:text-[#d6d6dd]';
    $sampleCellClass = 'px-4 py-3 text-sm text-[#4f4f4f] dark:text-[#b9bbc0]';
    $sampleActionButtonClass = 'inline-flex h-9 items-center justify-center rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 text-sm text-[#4f4f4f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0]';
    $sampleToolbarClass = 'mt-4 flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#d8d8d8] bg-[#efefef] px-4 py-3 dark:border-[#343434] dark:bg-[#202020]';
    $sampleInputClass = 'h-9 rounded-lg border border-[#d6d6d6] bg-[#f5f5f5] px-3 text-sm text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#171717] dark:text-[#d6d6dd]';
    $sampleBadgeStrongClass = 'inline-flex rounded-full border border-[#d0d0d0] bg-[#ececec] px-2.5 py-1 text-xs font-medium text-[#1f1f1f] dark:border-[#343434] dark:bg-[#252526] dark:text-[#d6d6dd]';
    $sampleBadgeMutedClass = 'inline-flex rounded-full border border-[#d8d8d8] bg-[#f1f1f1] px-2.5 py-1 text-xs font-medium text-[#555555] dark:border-[#343434] dark:bg-[#202020] dark:text-[#b9bbc0]';
    $multiSelectActions = ['상태 변경', '권한 변경', '삭제'];
    $hoverMenuPrimaryItems = ['이름 변경', '공유 링크', '삭제'];
    $hoverMenuSecondaryItems = ['다운로드', '복제', '삭제'];
    $rowActionItems = [
        ['icon' => 'view', 'label' => '상세', 'href' => '#'],
        ['icon' => 'settings', 'label' => '권한', 'href' => '#'],
    ];
    $hoverMenuIcons = [
        '이름 변경' => 'edit',
        '공유 링크' => 'settings',
        '삭제' => 'trash',
        '다운로드' => 'download',
        '복제' => 'copy',
    ];
    $hoverMenuPrimaryActionItems = collect($hoverMenuPrimaryItems)
        ->map(fn ($label) => ['icon' => $hoverMenuIcons[$label] ?? 'settings', 'label' => $label, 'href' => '#'])
        ->all();
    $hoverMenuSecondaryActionItems = collect($hoverMenuSecondaryItems)
        ->map(fn ($label) => ['icon' => $hoverMenuIcons[$label] ?? 'settings', 'label' => $label, 'href' => '#'])
        ->all();
@endphp

<x-layouts.app title="데이터 테이블">
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
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">목록 프레임워크</p>
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">DataTable Playground</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Samples + Live</p>
                </div>

                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    자주 쓰이는 셀 요소와 실제 공통 DataTable 동작만 빠르게 확인할 수 있도록 정리했습니다.
                </p>

                <section class="mt-6 space-y-4">
                    <section class="{{ $sampleCardClass }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Selectable Rows</p>
                                    <h4 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">체크박스 + 배지 + 액션 버튼 샘플</h4>
                                </div>
                                <span class="meta-badge">Checkbox</span>
                            </div>

                            <div class="{{ $sampleToolbarClass }}">
                                <div>
                                    <p class="text-sm font-semibold text-[#1f1f1f] dark:text-[#f3f3f3]">다중선택 툴바 샘플</p>
                                    <p class="mt-1 text-sm text-[#6a6a6a] dark:text-[#9ea1a8]">선택된 행 3개 · 일괄 상태 변경, 권한 변경, 삭제 같은 액션을 노출합니다.</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach ($multiSelectActions as $actionLabel)
                                        <button type="button" class="{{ $sampleActionButtonClass }}" title="{{ $actionLabel }}" aria-label="{{ $actionLabel }}">{{ $actionLabel }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="{{ $sampleTableWrapClass }}">
                                <table class="{{ $sampleTableClass }}" title="체크박스와 액션 요소 샘플">
                                    <thead class="border-b border-[#dddddd] dark:border-[#2a2a2a]">
                                        <tr>
                                            <th class="{{ $sampleHeadCellClass }} text-center">
                                                <input type="checkbox" title="전체 선택" aria-label="전체 선택" class="h-4 w-4 rounded border border-[#cfcfcf]">
                                            </th>
                                            <th class="{{ $sampleHeadCellClass }} text-left">이름</th>
                                            <th class="{{ $sampleHeadCellClass }} text-center">상태</th>
                                            <th class="{{ $sampleHeadCellClass }} text-center">권한</th>
                                            <th class="{{ $sampleHeadCellClass }} text-center">관리</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#dddddd] dark:divide-[#2a2a2a]">
                                        <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                                            <td class="{{ $sampleCellClass }} text-center"><input type="checkbox" title="행 선택" aria-label="행 선택" class="h-4 w-4 rounded border border-[#cfcfcf]" checked></td>
                                            <td class="{{ $sampleCellClass }}">홍길동</td>
                                            <td class="{{ $sampleCellClass }} text-center"><span class="{{ $sampleBadgeStrongClass }}">활성</span></td>
                                            <td class="{{ $sampleCellClass }} text-center"><span class="{{ $sampleBadgeMutedClass }}">Admin</span></td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <div class="flex justify-end">
                                                    <div
                                                        data-table-actions
                                                        data-trigger-label="행 관리"
                                                        data-items='@json($rowActionItems)'
                                                    ></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                                            <td class="{{ $sampleCellClass }} text-center"><input type="checkbox" title="행 선택" aria-label="행 선택" class="h-4 w-4 rounded border border-[#cfcfcf]"></td>
                                            <td class="{{ $sampleCellClass }}">김철수</td>
                                            <td class="{{ $sampleCellClass }} text-center"><span class="{{ $sampleBadgeMutedClass }}">비활성</span></td>
                                            <td class="{{ $sampleCellClass }} text-center"><span class="inline-flex rounded-full border border-[#d8d8d8] bg-transparent px-2.5 py-1 text-xs font-medium text-[#555555] dark:border-[#343434] dark:text-[#b9bbc0]">Operator</span></td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <div class="flex justify-end">
                                                    <div
                                                        data-table-actions
                                                        data-trigger-label="행 관리"
                                                        data-items='@json($rowActionItems)'
                                                    ></div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                    <section class="{{ $sampleCardClass }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Inputs In Cell</p>
                                    <h4 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">셀렉트 + 라디오 버튼 + 날짜 선택형 셀 샘플</h4>
                                </div>
                                <span class="meta-badge">Inputs</span>
                            </div>

                            <div class="{{ $sampleTableWrapClass }}">
                                <table class="{{ $sampleTableClass }}" title="셀렉트와 인라인 입력 요소 샘플">
                                    <thead class="border-b border-[#dddddd] dark:border-[#2a2a2a]">
                                        <tr>
                                            <th class="{{ $sampleHeadCellClass }} text-left">오더번호</th>
                                            <th class="{{ $sampleHeadCellClass }} text-left">기사</th>
                                            <th class="{{ $sampleHeadCellClass }} text-center">상태</th>
                                            <th class="{{ $sampleHeadCellClass }} text-center">우선순위</th>
                                            <th class="{{ $sampleHeadCellClass }} text-center">배차방식</th>
                                            <th class="{{ $sampleHeadCellClass }} text-center">희망일</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#dddddd] dark:divide-[#2a2a2a]">
                                        <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                                            <td class="{{ $sampleCellClass }}">ORD-20260801-001</td>
                                            <td class="{{ $sampleCellClass }}">정다은</td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <select class="{{ $sampleInputClass }}" title="상태 선택">
                                                    <option selected>대기</option>
                                                    <option>진행중</option>
                                                    <option>완료</option>
                                                </select>
                                            </td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <select class="{{ $sampleInputClass }}" title="우선순위 선택">
                                                    <option>낮음</option>
                                                    <option selected>보통</option>
                                                    <option>높음</option>
                                                </select>
                                            </td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <div class="flex items-center justify-center gap-3 text-sm text-[#4f4f4f] dark:text-[#b9bbc0]">
                                                    <label class="inline-flex items-center gap-1.5">
                                                        <input type="radio" name="dispatch-type-1" checked title="수동 배차" aria-label="수동 배차" class="h-4 w-4 border border-[#cfcfcf]">
                                                        <span>수동</span>
                                                    </label>
                                                    <label class="inline-flex items-center gap-1.5">
                                                        <input type="radio" name="dispatch-type-1" title="자동 배차" aria-label="자동 배차" class="h-4 w-4 border border-[#cfcfcf]">
                                                        <span>자동</span>
                                                    </label>
                                                </div>
                                            </td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <input type="date" value="2026-08-04" title="희망일 선택" aria-label="희망일 선택" class="{{ $sampleInputClass }}">
                                            </td>
                                        </tr>
                                        <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                                            <td class="{{ $sampleCellClass }}">ORD-20260801-002</td>
                                            <td class="{{ $sampleCellClass }}">이영희</td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <select class="{{ $sampleInputClass }}" title="상태 선택">
                                                    <option>대기</option>
                                                    <option selected>진행중</option>
                                                    <option>완료</option>
                                                </select>
                                            </td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <select class="{{ $sampleInputClass }}" title="우선순위 선택">
                                                    <option>낮음</option>
                                                    <option>보통</option>
                                                    <option selected>높음</option>
                                                </select>
                                            </td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <div class="flex items-center justify-center gap-3 text-sm text-[#4f4f4f] dark:text-[#b9bbc0]">
                                                    <label class="inline-flex items-center gap-1.5">
                                                        <input type="radio" name="dispatch-type-2" title="수동 배차" aria-label="수동 배차" class="h-4 w-4 border border-[#cfcfcf]">
                                                        <span>수동</span>
                                                    </label>
                                                    <label class="inline-flex items-center gap-1.5">
                                                        <input type="radio" name="dispatch-type-2" checked title="자동 배차" aria-label="자동 배차" class="h-4 w-4 border border-[#cfcfcf]">
                                                        <span>자동</span>
                                                    </label>
                                                </div>
                                            </td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <input type="date" value="2026-08-06" title="희망일 선택" aria-label="희망일 선택" class="{{ $sampleInputClass }}">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                    <section class="{{ $sampleCardClass }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Hover And Menu</p>
                                    <h4 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">행 hover 액션 + 드롭다운 메뉴 샘플</h4>
                                </div>
                                <span class="meta-badge">Hover</span>
                            </div>

                            <div class="{{ $sampleTableWrapClass }}">
                                <table class="{{ $sampleTableClass }}" title="행 hover 액션과 드롭다운 메뉴 샘플">
                                    <thead class="border-b border-[#dddddd] dark:border-[#2a2a2a]">
                                        <tr>
                                            <th class="{{ $sampleHeadCellClass }} text-left">파일</th>
                                            <th class="{{ $sampleHeadCellClass }} text-left">설명</th>
                                            <th class="{{ $sampleHeadCellClass }} text-center">hover 액션</th>
                                            <th class="{{ $sampleHeadCellClass }} text-center">드롭다운</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#dddddd] dark:divide-[#2a2a2a]">
                                        <tr class="group bg-[#f7f7f7] transition hover:bg-[#efefef] dark:bg-[#1a1a1a] dark:hover:bg-[#202020]">
                                            <td class="{{ $sampleCellClass }}">
                                                <div class="flex items-center gap-3">
                                                    <span class="inline-flex h-10 w-14 items-center justify-center rounded-[8px] border border-[#d8d8d8] bg-[#efefef] text-xs text-[#555555] dark:border-[#343434] dark:bg-[#202020] dark:text-[#b9bbc0]">IMG</span>
                                                    <span>driver-profile-02.jpg</span>
                                                </div>
                                            </td>
                                            <td class="{{ $sampleCellClass }}">기사 프로필 이미지</td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <div class="flex items-center justify-center gap-2 opacity-100 transition md:opacity-0 md:group-hover:opacity-100">
                                                    @foreach (['미리보기', '다운로드'] as $actionLabel)
                                                        <button type="button" class="{{ $sampleActionButtonClass }}" title="{{ $actionLabel }}" aria-label="{{ $actionLabel }}">{{ $actionLabel }}</button>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <div class="flex justify-end">
                                                    <div
                                                        data-table-actions
                                                        data-trigger-label="더보기 메뉴"
                                                        data-items='@json($hoverMenuPrimaryActionItems)'
                                                    ></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="group bg-[#f7f7f7] transition hover:bg-[#efefef] dark:bg-[#1a1a1a] dark:hover:bg-[#202020]">
                                            <td class="{{ $sampleCellClass }}">
                                                <div class="flex items-center gap-3">
                                                    <span class="inline-flex h-10 w-14 items-center justify-center rounded-[8px] border border-[#d8d8d8] bg-[#efefef] text-xs text-[#555555] dark:border-[#343434] dark:bg-[#202020] dark:text-[#b9bbc0]">XLSX</span>
                                                    <span>order-sheet-01.xlsx</span>
                                                </div>
                                            </td>
                                            <td class="{{ $sampleCellClass }}">오더 정산 자료</td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <div class="flex items-center justify-center gap-2 opacity-100 transition md:opacity-0 md:group-hover:opacity-100">
                                                    @foreach (['다운로드', '삭제'] as $actionLabel)
                                                        <button type="button" class="{{ $sampleActionButtonClass }}" title="{{ $actionLabel }}" aria-label="{{ $actionLabel }}">{{ $actionLabel }}</button>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="{{ $sampleCellClass }} text-center">
                                                <div class="flex justify-end">
                                                    <div
                                                        data-table-actions
                                                        data-trigger-label="더보기 메뉴"
                                                        data-items='@json($hoverMenuSecondaryActionItems)'
                                                    ></div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                    </section>

                <section class="{{ $sampleCardClass }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sortable Headers</p>
                            <h4 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">정렬 가능 헤더 + 상태 셀 샘플</h4>
                        </div>
                        <span class="meta-badge">Sort</span>
                    </div>

                    <div class="{{ $sampleTableWrapClass }}">
                        <table class="{{ $sampleTableClass }}" title="정렬 가능 헤더와 상태 셀 샘플">
                            <thead class="border-b border-[#dddddd] dark:border-[#2a2a2a]">
                                <tr>
                                    <th class="{{ $sampleHeadCellClass }} text-left">오더번호</th>
                                    <th class="{{ $sampleHeadCellClass }} text-left">
                                        <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-[#1f1f1f] transition hover:text-[#6a6a6a] dark:text-[#d6d6dd] dark:hover:text-[#9ea1a8]" title="기사명 오름차순 정렬 중">
                                            기사명
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5" aria-hidden="true">
                                                <path stroke-linecap="round" d="M6 15l6-6l6 6" />
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="{{ $sampleHeadCellClass }} text-center">
                                        <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-[#1f1f1f] transition hover:text-[#6a6a6a] dark:text-[#d6d6dd] dark:hover:text-[#9ea1a8]" title="상태 내림차순 정렬 중">
                                            상태
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5" aria-hidden="true">
                                                <path stroke-linecap="round" d="M6 9l6 6l6-6" />
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="{{ $sampleHeadCellClass }} text-left">
                                        <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-[#8b8b8b] dark:text-[#6d6d6d]" title="금액 정렬 안 함">
                                            금액
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5" aria-hidden="true">
                                                <path stroke-linecap="round" d="M6 9l6 6l6-6" />
                                            </svg>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#dddddd] dark:divide-[#2a2a2a]">
                                <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                                    <td class="{{ $sampleCellClass }}">ORD-20260801-001</td>
                                    <td class="{{ $sampleCellClass }}">정다은</td>
                                    <td class="{{ $sampleCellClass }} text-center"><span class="{{ $sampleBadgeStrongClass }}">배차 대기</span></td>
                                    <td class="{{ $sampleCellClass }}">45,000원</td>
                                </tr>
                                <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                                    <td class="{{ $sampleCellClass }}">ORD-20260801-002</td>
                                    <td class="{{ $sampleCellClass }}">이영희</td>
                                    <td class="{{ $sampleCellClass }} text-center"><span class="{{ $sampleBadgeMutedClass }}">배차 완료</span></td>
                                    <td class="{{ $sampleCellClass }}">36,000원</td>
                                </tr>
                                <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]">
                                    <td class="{{ $sampleCellClass }}">ORD-20260801-003</td>
                                    <td class="{{ $sampleCellClass }}">박서연</td>
                                    <td class="{{ $sampleCellClass }} text-center"><span class="{{ $sampleBadgeMutedClass }}">배차 완료</span></td>
                                    <td class="{{ $sampleCellClass }}">28,000원</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                        헤더 클릭 시 정렬 방향(asc/desc)이 전환되고, 현재 정렬 상태를 화살표 아이콘으로 표시합니다. 정렬되지 않은 컬럼은 흐린 아이콘을 유지합니다.
                    </p>
                </section>

                <section class="{{ $sampleCardClass }}">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Interactive Playground</p>
                            <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">실행형 DataTable</h3>
                        </div>
                        <span class="meta-badge">Live</span>
                    </div>

                    <div class="mt-6" data-datatable-playground></div>
                </section>
            </section>
        </div>
    </section>
</x-layouts.app>
