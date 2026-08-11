@php
    $isSetOrder = filled($order->group_type) && $order->group_type !== '-';

    // 셋트 오더는 그룹 전체 일정을 보여준다.
    $scheduleLineItems = $order->group_id
        ? $order->group->orders()->with('lineItems')->get()->flatMap(fn ($setOrder) => $setOrder->lineItems)
        : $order->lineItems;

    $serviceTypeLabels = [
        'pickup' => '픽업',
        'sending' => '공항샌딩',
        'landing' => '공항랜딩',
    ];
    $serviceTypeLabel = $serviceTypeLabels[$order->service_type] ?? ($order->service_type ?: '-');

    $amountLabel = $order->amount_text
        ?: ($order->amount_value !== null ? number_format($order->amount_value).'원' : '-');

    // 명시 금액이 없으면 파생 수익(expected_revenue)을 폴백으로 사용한다.
    $summaryAmountLabel = $amountLabel !== '-'
        ? $amountLabel
        : ($order->expected_revenue !== null ? number_format($order->expected_revenue).'원' : '-');

    $hasStructuredInfo = $order->request_label
        || $order->service_date
        || $order->group_type
        || $order->vehicle_type
        || $order->service_type
        || $order->amount_text
        || $order->amount_value !== null;
@endphp

<x-layouts.app title="예약 상세">
    <section class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]" data-order-show-page>
        <aside class="page-panel panel-gray lg:sticky lg:top-6 lg:self-start">
            <div class="ui-divider border-b pb-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Order Detail</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $order->order_number }}</h1>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    오더의 핵심 정보를 한눈에 확인합니다.
                </p>
            </div>

            <nav class="mt-4 space-y-2">
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
                    class="flex items-center justify-between rounded-lg border border-[#d8d8d8] bg-[#f5f5f5] px-3 py-2 text-sm text-[#4f4f4f] transition hover:bg-[#ededed] hover:text-[#1f1f1f] dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-[#b9bbc0] dark:hover:bg-[#222222] dark:hover:text-[#d6d6dd]"
                    title="예약 등록"
                >
                    <span>예약 등록</span>
                    <span class="text-xs">02</span>
                </a>
            </nav>

            <div class="mt-6 rounded-[10px] border border-[#dddddd] bg-[#f7f7f7] p-4 dark:border-[#2a2a2a] dark:bg-[#1a1a1a]">
                <p class="text-sm text-gray-500 dark:text-gray-400">등록자</p>
                <p class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $order->user?->name ?? '-' }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $order->created_at?->format('Y-m-d H:i') }}</p>
            </div>
        </aside>

        <div class="space-y-4">
            {{-- 핵심 요약 (노선 → · 시간 · 상태 · 금액) --}}
            <section class="page-panel panel-dark">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="meta-badge">{{ $isSetOrder ? '셋트' : '단일' }} · {{ $serviceTypeLabel }}</span>
                        </div>
                        <h2 class="mt-3 text-2xl font-semibold leading-snug text-gray-900 dark:text-gray-100">
                            {{ $order->pickup_location ?: '-' }} → {{ $order->dropoff_location ?: '-' }}
                        </h2>
                        <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                            <span class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-gray-400 dark:text-gray-500" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M12 7v5l3 2" />
                                </svg>
                                {{ $order->scheduled_at?->format('Y-m-d H:i') ?: '-' }}
                            </span>
                            @if ($order->flight_number)
                                <span>· 항공편 {{ $order->flight_number }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-3">
                        <span class="status-badge">{{ $statusOptions[$order->status] ?? $order->status }}</span>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $summaryAmountLabel }}</p>
                    </div>
                </div>
            </section>

            {{-- 주요 정보 (간소화: 2열 그리드) --}}
            <section class="page-panel">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">주요 정보</p>
                <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">차량 형태</dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $order->vehicle_type ?: '-' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">운행 구분</dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $serviceTypeLabel }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">인원 · 짐</dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $order->passenger_count !== null ? number_format($order->passenger_count).'명' : '-' }}
                            @if ($order->luggage_count !== null)
                                · {{ number_format($order->luggage_count) }}개
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">항공편</dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $order->flight_number ?: '-' }}</dd>
                    </div>
                    @if (is_array($order->extra_options) && count($order->extra_options) > 0)
                        <div class="flex items-start justify-between gap-4 sm:col-span-2">
                            <dt class="text-sm text-gray-500 dark:text-gray-400">기타 선택사항</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100">{{ implode(', ', $order->extra_options) }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- 일정 목록 --}}
            @if ($isSetOrder || $scheduleLineItems->isNotEmpty())
                <section class="page-panel">
                    @if ($isSetOrder)
                        <div class="overflow-hidden rounded-[10px] border border-[#dddddd] dark:border-[#2a2a2a]" data-order-set-table>
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#e5e5e5] bg-[#f1f1f1] px-4 py-3 dark:border-[#262626] dark:bg-[#1f1f1f]">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">셋트 일정</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $amountLabel !== '-' ? $amountLabel : $order->group_type }}
                                </p>
                            </div>

                            {{-- 모바일: 2줄 좌우 대칭 카드 --}}
                            <div class="divide-y divide-[#e5e5e5] dark:divide-[#262626] md:hidden" data-order-set-cards>
                                @foreach ($scheduleLineItems as $lineItem)
                                    <div class="bg-[#f7f7f7] px-4 py-3 dark:bg-[#1a1a1a]">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="min-w-0 truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $lineItem->service_month ? $lineItem->service_month.'월' : '' }}{{ $lineItem->service_day ? $lineItem->service_day.'일' : '' }}{{ $lineItem->service_weekday ? '('.$lineItem->service_weekday.')' : '' }} {{ $lineItem->scheduled_time ?: '-' }}
                                            </p>
                                            <p class="shrink-0 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $lineItem->amount_text ?: ($lineItem->amount_value !== null ? number_format($lineItem->amount_value).'원' : '-') }}
                                            </p>
                                        </div>
                                        <div class="mt-1 flex items-center justify-between gap-3">
                                            <p class="min-w-0 truncate text-sm text-gray-600 dark:text-gray-300">
                                                {{ $lineItem->pickup_location ?: '?' }} → {{ $lineItem->dropoff_location ?: '?' }}
                                            </p>
                                            <p class="flex shrink-0 items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                                @if ($lineItem->passenger_count !== null)
                                                    <span>{{ number_format($lineItem->passenger_count) }}명</span>
                                                @endif
                                                @if ($lineItem->luggage_count !== null)
                                                    <span>· {{ number_format($lineItem->luggage_count) }}개</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- 데스크톱: 테이블 --}}
                            <div class="hidden overflow-x-auto md:block">
                                <table class="min-w-full border-collapse text-left">
                                    <thead class="bg-[#f1f1f1] dark:bg-[#1f1f1f]">
                                        <tr>
                                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">시간</th>
                                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">날짜</th>
                                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">운행 구분</th>
                                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">경로</th>
                                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">항공편</th>
                                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">인원</th>
                                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">짐</th>
                                            <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">금액</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#e5e5e5] dark:divide-[#262626]">
                                        @foreach ($scheduleLineItems as $lineItem)
                                            <tr class="bg-[#f7f7f7] align-top dark:bg-[#1a1a1a]">
                                                <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $lineItem->scheduled_time ?: '-' }}</td>
                                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $lineItem->service_month ? $lineItem->service_month.'월' : '' }}{{ $lineItem->service_day ? $lineItem->service_day.'일' : '' }}
                                                    @if ($lineItem->service_weekday)
                                                        <span class="text-gray-400 dark:text-gray-500">({{ $lineItem->service_weekday }})</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $lineItem->service_type ?: '-' }}</td>
                                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $lineItem->pickup_location ?: '?' }} → {{ $lineItem->dropoff_location ?: '?' }}
                                                </td>
                                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $lineItem->flight_number ?: '-' }}</td>
                                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $lineItem->passenger_count !== null ? number_format($lineItem->passenger_count).'명' : '-' }}</td>
                                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $lineItem->luggage_count !== null ? number_format($lineItem->luggage_count).'개' : '-' }}</td>
                                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $lineItem->amount_text ?: ($lineItem->amount_value !== null ? number_format($lineItem->amount_value).'원' : '-') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">일정 목록</p>

                        {{-- 데스크톱: 테이블 --}}
                        <div class="mt-3 hidden overflow-x-auto rounded-[10px] border border-[#dddddd] dark:border-[#2a2a2a] md:block">
                            <table class="min-w-full border-collapse text-left">
                                <thead class="bg-[#f1f1f1] dark:bg-[#1f1f1f]">
                                    <tr>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">시간</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">날짜</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">경로</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">인원</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">금액</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e5e5e5] dark:divide-[#262626]">
                                    @foreach ($scheduleLineItems as $lineItem)
                                        <tr class="bg-[#f7f7f7] align-top dark:bg-[#1a1a1a]">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $lineItem->scheduled_time ?: '-' }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $lineItem->service_month ? $lineItem->service_month.'월' : '' }}{{ $lineItem->service_day ? $lineItem->service_day.'일' : '' }}
                                                @if ($lineItem->service_weekday)
                                                    <span class="text-gray-400 dark:text-gray-500">({{ $lineItem->service_weekday }})</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $lineItem->pickup_location ?: '?' }} → {{ $lineItem->dropoff_location ?: '?' }}
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $lineItem->passenger_count !== null ? number_format($lineItem->passenger_count).'명' : '-' }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $lineItem->amount_text ?: ($lineItem->amount_value !== null ? number_format($lineItem->amount_value).'원' : '-') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- 모바일: 2줄 좌우 대칭 카드 --}}
                        <div class="mt-3 divide-y overflow-hidden rounded-[10px] border border-[#dddddd] dark:border-[#2a2a2a] md:hidden" data-order-line-cards>
                            @foreach ($scheduleLineItems as $lineItem)
                                <div class="bg-[#f7f7f7] px-4 py-3 dark:bg-[#1a1a1a]">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="min-w-0 truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $lineItem->service_month ? $lineItem->service_month.'월' : '' }}{{ $lineItem->service_day ? $lineItem->service_day.'일' : '' }}{{ $lineItem->service_weekday ? '('.$lineItem->service_weekday.')' : '' }} {{ $lineItem->scheduled_time ?: '-' }}
                                        </p>
                                        <p class="shrink-0 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $lineItem->amount_text ?: ($lineItem->amount_value !== null ? number_format($lineItem->amount_value).'원' : '-') }}
                                        </p>
                                    </div>
                                    <div class="mt-1 flex items-center justify-between gap-3">
                                        <p class="min-w-0 truncate text-sm text-gray-600 dark:text-gray-300">
                                            {{ $lineItem->pickup_location ?: '?' }} → {{ $lineItem->dropoff_location ?: '?' }}
                                        </p>
                                        <p class="flex shrink-0 items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                            @if ($lineItem->passenger_count !== null)
                                                <span>{{ number_format($lineItem->passenger_count) }}명</span>
                                            @endif
                                            @if ($lineItem->luggage_count !== null)
                                                <span>· {{ number_format($lineItem->luggage_count) }}개</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            {{-- 구조화 정보 (접힘) --}}
            @if ($hasStructuredInfo)
                <details class="page-panel" data-order-structured-info>
                    <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-gray-100">AI 구조화 · 구조화 정보</summary>

                    @if ($order->request_label)
                        <div class="mt-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">원문 요약</p>
                            <p class="mt-1 text-sm leading-6 text-gray-900 dark:text-gray-100">{{ $order->request_label }}</p>
                        </div>
                    @endif

                    <div class="mt-3 divide-y divide-[#e5e5e5] dark:divide-[#262626]">
                        <div class="flex items-start justify-between gap-4 py-3">
                            <p class="text-sm text-gray-500 dark:text-gray-400">서비스 날짜</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $order->service_date ?: '-' }}</p>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-3">
                            <p class="text-sm text-gray-500 dark:text-gray-400">서비스 시간</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $order->service_time ?: '-' }}</p>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-3">
                            <p class="text-sm text-gray-500 dark:text-gray-400">묶음 유형</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $order->group_type ?: '-' }}</p>
                        </div>
                    </div>
                </details>
            @endif

            {{-- 원본 / AI JSON (접힘) --}}
            @if ($order->original_summary)
                <details class="page-panel" data-order-original-summary>
                    <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-gray-100">원본 입력 내용</summary>
                    <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-gray-900 dark:text-gray-100">{{ $order->original_summary }}</p>
                </details>
            @endif

            @if ($order->structured_payload)
                <details class="page-panel" data-order-structured-payload>
                    <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-gray-100">AI 반환 JSON</summary>
                    <pre class="mt-3 overflow-x-auto whitespace-pre-wrap break-all text-xs leading-6 text-gray-900 dark:text-gray-100">{{ json_encode($order->structured_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            @endif

            {{-- 액션 / 상태 --}}
            <section class="page-panel">
                <div data-order-detail-actions>
                    @if ($canClaimOrder)
                        <form
                            method="POST"
                            action="{{ route('dashboard.business.order.claim', $order) }}"
                            class="mb-4"
                            data-order-claim
                        >
                            @csrf
                            <button type="submit" class="btn-primary" title="내 오더로 가져오기">내 오더로 가져오기</button>
                        </form>
                    @endif

                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">상세 액션</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a
                            href="{{ route('dashboard.business.order.edit', $order) }}"
                            class="btn-secondary"
                            title="오더 수정"
                        >
                            오더 수정
                        </a>
                        <a href="{{ route('dashboard.business.order.create') }}" class="btn-primary" title="새 오더 등록">새 오더 등록</a>
                        <a href="{{ route('dashboard.business.order') }}" class="btn-secondary" title="오더 워크스페이스">워크스페이스</a>
                    </div>
                </div>

                <div class="ui-divider mt-6 border-t pt-6" data-order-status>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">오더 상태</p>

                    @php
                        $mainFlow = [
                            \App\Models\Order::STATUS_DRAFT => '초안',
                            \App\Models\Order::STATUS_PUBLISHED => '공개',
                            \App\Models\Order::STATUS_TRADING => '거래중',
                            \App\Models\Order::STATUS_ACCEPTED => '수락',
                            \App\Models\Order::STATUS_DRIVING => '운행중',
                            \App\Models\Order::STATUS_COMPLETED => '완료',
                            \App\Models\Order::STATUS_SETTLED => '정산',
                        ];
                        $flowKeys = array_keys($mainFlow);
                        $currentIndex = array_search($order->status, $flowKeys, true);
                    @endphp

                    <div class="mt-3 flex items-center gap-2">
                        <span class="status-badge">{{ $statusOptions[$order->status] ?? $order->status }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">현재 단계</span>
                    </div>

                    {{-- 생명주기 스텝 (취소/수락 대기 상태는 흐름 밖) --}}
                    @if ($currentIndex !== false)
                        <ol class="mt-4 flex items-center gap-1 overflow-x-auto pb-1" data-order-status-stepper>
                            @foreach ($mainFlow as $statusKey => $statusLabel)
                                <li class="flex shrink-0 items-center">
                                    @php
                                        $index = array_search($statusKey, $flowKeys, true);
                                        $isCurrent = $index === $currentIndex;
                                        $isDone = $index < $currentIndex;
                                    @endphp
                                    <span
                                        class="flex h-7 items-center whitespace-nowrap rounded-full px-3 text-xs font-semibold transition
                                            {{ $isCurrent
                                                ? 'bg-[#e8e8e8] text-gray-900 ring-1 ring-[#cfcfcf] dark:bg-[#262626] dark:text-gray-100 dark:ring-[#3a3a3a]'
                                                : ($isDone
                                                    ? 'bg-[#f4f4f4] text-gray-500 dark:bg-[#1c1c1c] dark:text-gray-400'
                                                    : 'text-gray-400 dark:text-gray-600') }}"
                                    >
                                        {{ $statusLabel }}
                                    </span>
                                    @if (! $loop->last)
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mx-1 h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" aria-hidden="true">
                                            <path d="m9 6 6 6-6 6" />
                                        </svg>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @php
                        $nextStatuses = \App\Models\Order::STATUS_FLOW[$order->status] ?? [];
                    @endphp

                    @if (count($nextStatuses) > 0)
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($nextStatuses as $nextStatus)
                                @if ($nextStatus === \App\Models\Order::STATUS_CANCELLED)
                                    <form
                                        method="POST"
                                        action="{{ route('dashboard.business.order.status.transition', $order) }}"
                                        data-confirm-message="오더를 취소하시겠습니까? 취소한 오더는 복구할 수 없습니다."
                                    >
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                                        <button type="submit" class="btn-secondary text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300" title="오더 취소">취소</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('dashboard.business.order.status.transition', $order) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                                        <button type="submit" class="{{ $loop->first ? 'btn-primary' : 'btn-secondary' }}" title="{{ $statusOptions[$nextStatus] ?? $nextStatus }} 상태로 전환">
                                            {{ $statusOptions[$nextStatus] ?? $nextStatus }}
                                        </button>
                                    </form>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">이 상태에서 전환할 수 있는 단계가 없습니다.</p>
                    @endif
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
