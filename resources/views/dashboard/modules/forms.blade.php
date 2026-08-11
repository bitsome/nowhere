<x-layouts.app title="폼">
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
                        <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">폼 유형 현황</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Blade + Vue</p>
                </div>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    현재 프로젝트에서 실제 사용 중인 폼 유형을 한눈에 확인합니다.
                    입력 높이는 44px · 라벨은 텍스트 위 · 필수/설명/오류는 폼그룹 구조로 정리합니다.
                </p>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Input</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">기본 입력</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    한 줄 텍스트 입력입니다. <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">input-field</code> 클래스를
                    게시판, 오더, 프로필 폼에서 사용 중입니다. Input에는 가능한 한 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">placeholder</code>와 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">title</code>을 함께 제공합니다.
                </p>
                <div class="mt-4 max-w-2xl space-y-4">
                    <div>
                        <label for="form-input-title" class="text-sm font-medium text-gray-900 dark:text-gray-100">제목</label>
                        <input id="form-input-title" name="title" type="text" title="제목" class="input-field" placeholder="제목을 입력하세요">
                    </div>
                    <div>
                        <label for="form-input-phone" class="text-sm font-medium text-gray-900 dark:text-gray-100">휴대폰</label>
                        <input id="form-input-phone" name="phone" type="tel" title="휴대폰" class="input-field" placeholder="010-0000-0000">
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Date / Time / Weekday</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">날짜·시간·요일</h3>
                    </div>
                    <span class="meta-badge">구조</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    날짜, 시간, 요일 선택형 입력입니다. 오더의 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">service_date</code> /
                    <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">service_time</code>과 정기 운행 요일 지정에 사용합니다.
                    네이티브 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">type="date"</code> / <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">type="time"</code>과 셀렉트를 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">input-field</code> 스타일로 통일하며,
                    입력 폭은 내용 크기에 맞추고(<code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">input-field--compact</code>) 값 텍스트는 수직 중앙 정렬합니다.
                </p>
                <div class="mt-4 flex max-w-2xl flex-wrap items-end gap-4">
                    <div>
                        <label for="form-date-service" class="text-sm font-medium text-gray-900 dark:text-gray-100">서비스 날짜</label>
                        <input id="form-date-service" name="service_date" type="date" title="서비스 날짜" class="input-field input-field--compact">
                    </div>
                    <div>
                        <label for="form-time-service" class="text-sm font-medium text-gray-900 dark:text-gray-100">출발 시간</label>
                        <input id="form-time-service" name="service_time" type="time" title="출발 시간" class="input-field input-field--compact">
                    </div>
                    <div>
                        <label for="form-weekday" class="text-sm font-medium text-gray-900 dark:text-gray-100">운행 요일</label>
                        <select id="form-weekday" name="weekday" title="운행 요일" class="input-field input-field--compact">
                            <option>월요일</option>
                            <option>화요일</option>
                            <option>수요일</option>
                            <option>목요일</option>
                            <option>금요일</option>
                            <option>토요일</option>
                            <option>일요일</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Select</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">셀렉트</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    옵션 선택 입력입니다. 게시판 폼에서 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">input-field</code> 클래스를 셀렉트에 재사용하고,
                    Vue 공용 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseSelect</code>는 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">form-select</code>를 사용합니다.
                </p>
                <div class="mt-4 grid max-w-2xl gap-4 md:grid-cols-2">
                    <div>
                        <label for="form-select-type" class="text-sm font-medium text-gray-900 dark:text-gray-100">게시판 구분</label>
                        <select id="form-select-type" name="type" title="게시판 구분" class="input-field">
                            <option>공지사항</option>
                            <option>자유게시판</option>
                            <option>문의하기</option>
                        </select>
                    </div>
                    <div>
                        <label for="form-select-status" class="text-sm font-medium text-gray-900 dark:text-gray-100">상태</label>
                        <select id="form-select-status" name="status" title="상태" class="input-field">
                            <option>대기</option>
                            <option>진행중</option>
                            <option>완료</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Textarea</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">텍스트에어리어</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    여러 줄 텍스트 입력입니다. Vue 공용 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">BaseTextarea</code>(form-framework__textarea)와
                    Blade의 인라인 클래스가 동일한 시각 규칙을 사용합니다. 세로 크기는 내용에 맞춰 조절할 수 있습니다.
                </p>
                <div class="mt-4 max-w-2xl">
                    <label for="form-textarea-note" class="text-sm font-medium text-gray-900 dark:text-gray-100">메모</label>
                    <textarea
                        id="form-textarea-note"
                        name="note"
                        title="메모"
                        class="mt-2 block min-h-[144px] w-full rounded-lg border border-[#d6d6d6] bg-[#f5f5f5] px-4 py-3 text-[#1f1f1f] placeholder:text-[#8b8b8b] focus:border-[#b9b9b9] focus:outline-none dark:border-[#2a2a2a] dark:bg-[#171717] dark:text-[#d6d6dd] dark:placeholder:text-[#6d6d6d] dark:focus:border-[#3a3a3a]"
                        placeholder="배차 시 참고할 메모를 입력하세요"
                    ></textarea>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Checkbox</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">체크박스</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    다중 선택/동의형 입력입니다. 게시판의 비공개 설정, 회원 권한 화면에서 사용 중입니다.
                    라벨은 체크박스 오른쪽에 배치하고 전체 영역을 클릭 가능하게 만듭니다.
                </p>
                <div class="mt-4 max-w-2xl space-y-2 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <label class="flex items-center gap-3 text-sm text-gray-900 dark:text-gray-100" title="비공개 게시글">
                        <input type="checkbox" name="is_private" value="1" title="비공개 게시글" class="h-4 w-4">
                        <span>비공개 게시글</span>
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-900 dark:text-gray-100" title="상단 고정">
                        <input type="checkbox" name="is_pinned" value="1" title="상단 고정" class="h-4 w-4">
                        <span>상단 고정</span>
                    </label>
                    <label class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400" title="문자 알림 수신">
                        <input type="checkbox" name="sms_alerts" value="1" title="문자 알림 수신" class="h-4 w-4" disabled>
                        <span>문자 알림 수신 (비활성)</span>
                    </label>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">FormGroup</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">폼 그룹</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    라벨 + 필수 + 설명 + 입력 + 오류를 묶는 공통 구조입니다.
                    Vue 공용 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">FormGroup</code>(form-framework__group) 기준이며,
                    폼 라벨·설명·오류 출력은 이 구조로 통일합니다.
                </p>
                <div class="mt-4 max-w-2xl rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <div class="form-framework__group">
                        <div class="form-framework__head">
                            <label class="form-framework__label" for="form-group-name">
                                <span>기사 이름</span>
                                <span class="form-framework__required">필수</span>
                            </label>
                            <p class="form-framework__description">배차 화면에서 사용할 기사의 표시 이름입니다.</p>
                        </div>
                        <div class="form-framework__body">
                            <input id="form-group-name" type="text" title="기사 이름" class="input-field" placeholder="이름을 입력하세요">
                            <p class="form-framework__error">이름은 2자 이상 입력해 주세요.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">States</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">폼 상태</h3>
                    </div>
                    <span class="meta-badge">구조</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    비활성은 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">disabled</code>,
                    필수는 라벨 옆 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">필수</code> 배지로 표현합니다.
                    의미 색상 지정 전까지 상태 차이는 그레이스케일로 유지합니다.
                </p>
                <div class="mt-4 max-w-2xl space-y-4">
                    <div>
                        <label for="form-state-disabled" class="text-sm font-medium text-gray-900 dark:text-gray-100">수정 불가 항목</label>
                        <input id="form-state-disabled" type="text" title="수정 불가 항목" class="input-field" value="Super Admin" disabled>
                    </div>
                    <div>
                        <label for="form-state-required" class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                            <span>예약 회사</span>
                            <span class="form-framework__required">필수</span>
                        </label>
                        <input id="form-state-required" type="text" title="예약 회사" class="input-field" placeholder="예약 회사명을 입력하세요" required>
                    </div>
                </div>
            </section>

            <section class="page-panel panel-gray">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Actions</p>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">액션 폼</h3>
                    </div>
                    <span class="meta-badge">실사용</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    입력 영역 아래에 액션 버튼을 배치합니다. 보조 액션(취소)은 왼쪽, 주요 액션(저장)은 오른쪽 끝에 둡니다.
                    게시판 등록/수정 폼의 저장 흐름에서 사용 중입니다.
                </p>
                <form class="mt-4 max-w-2xl space-y-4 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                    <div>
                        <label for="form-action-title" class="text-sm font-medium text-gray-900 dark:text-gray-100">오더 제목</label>
                        <input id="form-action-title" name="title" type="text" title="오더 제목" class="input-field" placeholder="오더 제목을 입력하세요">
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" class="btn-secondary" title="입력 취소">취소</button>
                        <button type="button" class="btn-primary" title="오더 저장">저장</button>
                    </div>
                </form>
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
                        <span>Blade는 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">input-field</code>를, Vue는 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">form-framework__control</code>을 사용 — 동일 스타일이므로 한쪽 기준으로 통합 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>셀렉트는 게시판에서 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">input-field</code>를 재사용하고 Vue는 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">form-select</code>를 사용 — 전용 셀렉트 클래스로 통일 검토.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="text-gray-400 dark:text-gray-500">•</span>
                        <span>폼 오류 색상은 <code class="rounded bg-[#efefef] px-1 py-0.5 text-xs dark:bg-[#202020]">#5f5f5f</code> 그레이 계열 — 의미 색상 지정 전까지 유지하고, 지정 시 폼그룹 한 곳에서만 수정하도록 구성.</span>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</x-layouts.app>
