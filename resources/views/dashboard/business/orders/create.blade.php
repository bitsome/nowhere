@php
    $firstLineItem = $order->lineItems->first();
    $oldLineItems = old('line_items', []);
    $firstOldLineItem = is_array($oldLineItems) ? (reset($oldLineItems) ?: []) : [];
    $previewPickupLocation = $firstOldLineItem['pickup_location'] ?? $firstLineItem?->pickup_location ?? '서울 강남구';
    $previewDropoffLocation = $firstOldLineItem['dropoff_location'] ?? $firstLineItem?->dropoff_location ?? '인천공항';
    $previewScheduledTime = $firstOldLineItem['scheduled_time'] ?? $firstLineItem?->scheduled_time ?? '08:42';
    $previewFlightNumber = $firstOldLineItem['flight_number'] ?? $firstLineItem?->flight_number ?? 'KE123';
    $previewPassengerCount = $firstOldLineItem['passenger_count'] ?? $firstLineItem?->passenger_count ?? 4;
    $previewAmountValue = $firstOldLineItem['amount_value'] ?? $firstLineItem?->amount_value ?? 18000;
    $previewAmountText = $firstOldLineItem['amount_text'] ?? $firstLineItem?->amount_text ?? '';
    $previewStatus = old('status', $order->status);
    $previewRouteSummary = sprintf(
        '%s - %s %s',
        $previewPickupLocation ?: '서울 강남구',
        $previewDropoffLocation ?: '인천공항',
        $previewScheduledTime ?: '08:42',
    );
@endphp

<x-layouts.app title="예약 등록">
    <div data-order-toast></div>
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]" data-order-create-page>
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Order Create</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">예약 등록</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    현재는 참조 테이블 없이 직접 입력 기반으로 최소 예약등록만 먼저 시작합니다.
                </p>
            </div>

            <nav class="mt-4 space-y-2">
                <a
                    href="{{ route('dashboard.business.nowhere') }}"
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                    title="NoWhere 비즈니스 허브"
                >
                    <span>NoWhere 허브</span>
                    <span class="text-xs">00</span>
                </a>
                <a
                    href="{{ route('dashboard.business.order') }}"
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                    title="오더 워크스페이스"
                >
                    <span>오더 워크스페이스</span>
                    <span class="text-xs">01</span>
                </a>
                <a
                    href="{{ route('dashboard.business.order.create') }}"
                    class="flex items-center justify-between rounded-lg border border-[#cfcfcf] bg-[#ececec] px-3 py-2 text-sm text-[#1f1f1f] dark:border-[#343434] dark:bg-[#222222] dark:text-[#f3f3f3]"
                    title="예약 등록"
                >
                    <span>예약 등록</span>
                    <span class="text-xs">02</span>
                </a>
            </nav>

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">현재 기준</p>
                <p class="mt-2 text-sm leading-6 text-gray-900 dark:text-gray-100">
                    일정(라인 아이템) 단위로 운행 정보, 경로, 항공편, 인원, 금액을 입력하고 저장합니다.
                </p>
            </div>
        </aside>

        <div class="space-y-4">
            <section class="page-panel panel-dark">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">등록</p>
                <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">Order Create</h2>
                <p class="mt-3 text-sm leading-7 text-gray-500 dark:text-gray-400">
                    Business Foundation이 준비되기 전까지는 직접 입력 기반으로 예약을 저장하고, 이후 선택형 구조로 확장합니다.
                </p>
            </section>

            <section
                class="page-panel"
                data-order-ai-structure
                data-structure-url="{{ route('dashboard.business.order.structure') }}"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">AI 구조화</p>
                        <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">오더 요약 입력</h3>
                        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            여러 줄 입력도 가능합니다. 예: <br>
                            2号 一起出 카니발<br>
                            15:20 送机 江南区 4人<br>
                            17:25 接机 钟路区 2人
                        </p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">예약 구분</p>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300" title="단일 예약">
                        <input type="radio" name="group_type" value="단일" checked class="h-4 w-4 border border-[#cfcfcf]" data-order-ai-group-type>
                        <span>단일</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300" title="셋트 예약">
                        <input type="radio" name="group_type" value="셋트" class="h-4 w-4 border border-[#cfcfcf]" data-order-ai-group-type>
                        <span>셋트</span>
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400">직접 선택한 구분은 AI 구조화 결과보다 우선해 예약 저장에 반영됩니다. (기본: 단일)</p>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto]">
                    <div>
                        <label for="order_summary_input" class="text-sm font-medium text-gray-900 dark:text-gray-100">오더 요약 입력</label>
                        <textarea
                            id="order_summary_input"
                            title="오더 요약 입력"
                            class="input-field mt-2"
                            rows="5"
                            placeholder="2号 一起出 카니발&#10;&#10;15:20 送机 江南区 4人&#10;17:25 接机 钟路区 2人"
                            data-order-summary-input
                        ></textarea>
                    </div>

                    <div class="flex flex-wrap items-end gap-2">
                        <button
                            type="button"
                            class="btn-primary"
                            title="AI 구조화"
                            aria-label="AI 구조화"
                            data-order-summary-trigger
                        >
                            AI 구조화
                        </button>
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400" data-order-summary-status>
                    오더 요약을 입력하면 AI가 출발지, 도착지, 시간, 항공편, 차량 형태와 다중 일정까지 구조화합니다.
                </p>


                <form
                    method="POST"
                    action="{{ route('dashboard.business.order.storeStructured') }}"
                    class="mt-4"
                    data-order-structured-store
                >
                    @csrf
                    <input type="hidden" name="structured" value="" data-order-structured-payload>
                    <input type="hidden" name="original_summary" value="" data-order-original-summary>
                    <input type="hidden" name="group_type" value="" data-order-group-type-submit>
                    <button
                        type="submit"
                        class="btn-primary"
                        title="AI 구조화 결과를 예약으로 저장"
                        aria-label="AI 구조화 결과를 예약으로 저장"
                        data-order-structured-save
                        disabled
                    >
                        AI 결과 저장
                    </button>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        구조화된 내용을 정리해 예약(오더)과 다중 일정으로 저장한 뒤 상세 화면으로 이동합니다.
                    </p>
                </form>
            </section>

            <section class="page-panel panel-gray" data-order-create-summary>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">운행 요약</p>
                <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100" data-order-preview-summary>{{ $previewRouteSummary }}</h3>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="meta-badge" data-order-preview-flight>항공편 {{ $previewFlightNumber ?: 'KE123' }}</span>
                    <span class="meta-badge" data-order-preview-passenger>인원 {{ $previewPassengerCount ?: 0 }}명</span>
                    <span class="meta-badge" data-order-preview-amount>금액 {{ $previewAmountText ?: number_format((int) $previewAmountValue) . '원' }}</span>
                    <span class="meta-badge" data-order-preview-status>{{ $statusOptions[$previewStatus] ?? '수락 대기' }}</span>
                </div>
            </section>

            <section class="page-panel">
                @include('dashboard.business.orders._form', [
                    'formAction' => route('dashboard.business.order.store'),
                    'formSubmitLabel' => '예약 저장',
                    'formSubmitTitle' => '예약 저장',
                    'formCancelUrl' => route('dashboard.business.order'),
                    'formCancelLabel' => '취소',
                    'formCancelTitle' => '오더 워크스페이스',
                    'formShowLineItems' => true,
                ])
            </section>
        </div>
    </section>
</x-layouts.app>
