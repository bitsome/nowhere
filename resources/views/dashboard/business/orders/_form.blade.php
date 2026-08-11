@if ($errors->any())
    <x-alert variant="error" title="입력값을 확인하세요." class="mb-6" dismissible>
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

<form method="POST" action="{{ $formAction }}">
    @csrf
    @if (($formMethod ?? 'POST') !== 'POST')
        @method($formMethod)
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
            <x-form-group label="예약 상태" for="status" :error="$errors->first('status')">
                <select id="status" name="status" title="예약 상태" class="input-field" required>
                    @foreach ($statusOptions as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected(old('status', $order->status) === $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </x-form-group>
        </div>
    </div>

    @if ($formShowLineItems ?? false)
        @php
            $editorLineItems = old('line_items') ?? $order->lineItems;
            $selectedGroupType = old('group_type', $order->group_type) ?: '단일';
            $lineItemFieldKeys = [
                'scheduled_time', 'service_date', 'service_month', 'service_day',
                'service_weekday', 'service_type', 'location',
                'pickup_location', 'dropoff_location', 'flight_number',
                'passenger_count', 'luggage_count', 'amount_value', 'amount_text',
            ];
        @endphp
        <div class="mt-6" data-order-line-items-editor>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">예약 구분</p>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300" title="단일 예약">
                    <input type="radio" name="group_type" value="단일" @checked($selectedGroupType === '단일') class="h-4 w-4 border border-[#cfcfcf]" data-order-group-type>
                    <span>단일</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300" title="셋트 예약">
                    <input type="radio" name="group_type" value="셋트" @checked($selectedGroupType === '셋트') class="h-4 w-4 border border-[#cfcfcf]" data-order-group-type>
                    <span>셋트</span>
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400">단일은 한 건의 일정, 셋트는 여러 일정을 묶은 예약입니다.</p>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">일정 목록</p>
                        <span class="rounded-full border border-[#d7d7d7] bg-[#f5f5f5] px-2.5 py-1 text-xs font-medium text-gray-700 dark:border-[#2a2a2a] dark:bg-[#1a1a1a] dark:text-gray-300" data-order-line-items-kind>단일 일정</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">행의 [상세보기]를 클릭해 출발지, 도착지, 픽업 일시, 항공편, 인원, 금액을 입력하고 저장하세요.</p>
                </div>
                <button type="button" class="btn-secondary" data-line-items-add title="일정 추가">일정 추가</button>
            </div>

            <div class="mt-3 overflow-x-auto rounded-[10px] border border-[#dddddd] dark:border-[#2a2a2a]">
                <table class="min-w-full border-collapse text-left">
                    <thead class="bg-[#f1f1f1] dark:bg-[#1f1f1f]">
                        <tr>
                            <th class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">시간</th>
                            <th class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">날짜</th>
                            <th class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">운행 구분</th>
                            <th class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">경로</th>
                            <th class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">항공편</th>
                            <th class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">인원</th>
                            <th class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">짐</th>
                            <th class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">금액</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5] dark:divide-[#262626]" data-line-items-body>
                        @forelse ($editorLineItems as $lineItemKey => $lineItem)
                            @php
                                $lineItemRow = $lineItem instanceof \App\Models\OrderLineItem
                                    ? $lineItem->only($lineItemFieldKeys)
                                    : $lineItem;
                                $lineItemKey = $lineItem instanceof \App\Models\OrderLineItem ? $lineItem->id : $lineItemKey;
                                $lineItemSummaryDate = ($lineItemRow['service_month'] ?? '') && ($lineItemRow['service_day'] ?? '')
                                    ? trim(($lineItemRow['service_month'] ?? '').'월'.($lineItemRow['service_day'] ?? '').'일'.(($lineItemRow['service_weekday'] ?? '') ? ' ('.$lineItemRow['service_weekday'].')' : ''))
                                    : ($lineItemRow['service_date'] ?? '-');
                            @endphp
                            <tr class="bg-[#f7f7f7] align-top dark:bg-[#1a1a1a]" data-line-item-row>
                                <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="time">{{ $lineItemRow['scheduled_time'] ?? '-' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="date">{{ $lineItemSummaryDate }}</td>
                                <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="type">{{ $lineItemRow['service_type'] ?? '-' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="route">
                                    @if (($lineItemRow['pickup_location'] ?? '') || ($lineItemRow['dropoff_location'] ?? ''))
                                        {{ ($lineItemRow['pickup_location'] ?? '?') }} → {{ ($lineItemRow['dropoff_location'] ?? '?') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="flight">{{ $lineItemRow['flight_number'] ?? '-' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="passenger">{{ isset($lineItemRow['passenger_count']) && $lineItemRow['passenger_count'] !== '' ? $lineItemRow['passenger_count'].'명' : '-' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="luggage">{{ isset($lineItemRow['luggage_count']) && $lineItemRow['luggage_count'] !== '' ? $lineItemRow['luggage_count'].'개' : '-' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="amount">
                                    @if (!empty($lineItemRow['amount_text']))
                                        {{ $lineItemRow['amount_text'] }}
                                    @elseif (isset($lineItemRow['amount_value']) && $lineItemRow['amount_value'] !== '')
                                        {{ number_format((int) $lineItemRow['amount_value']) }}원
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="btn-secondary" data-line-item-edit title="상세보기">상세보기</button>
                                        <button type="button" class="btn-secondary" data-line-items-remove title="일정 삭제">삭제</button>
                                    </div>
                                </td>
                                @foreach ($lineItemFieldKeys as $lineItemFieldKey)
                                    <input type="hidden" name="line_items[{{ $lineItemKey }}][{{ $lineItemFieldKey }}]" value="{{ $lineItemRow[$lineItemFieldKey] ?? '' }}">
                                @endforeach
                            </tr>
                        @empty
                            <tr class="bg-[#f7f7f7] dark:bg-[#1a1a1a]" data-line-items-empty>
                                <td colspan="9" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                    등록된 일정이 없습니다.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <template data-line-items-row>
                <tr class="bg-[#f7f7f7] align-top dark:bg-[#1a1a1a]" data-line-item-row>
                    <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="time">-</td>
                    <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="date">-</td>
                    <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="type">-</td>
                    <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="route">-</td>
                    <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="flight">-</td>
                    <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="passenger">-</td>
                    <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="luggage">-</td>
                    <td class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200" data-line-item-summary="amount">-</td>
                    <td class="px-3 py-3">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-secondary" data-line-item-edit title="상세보기">상세보기</button>
                            <button type="button" class="btn-secondary" data-line-items-remove title="일정 삭제">삭제</button>
                        </div>
                    </td>
                    @foreach ($lineItemFieldKeys as $lineItemFieldKey)
                        <input type="hidden" name="line_items[__KEY__][{{ $lineItemFieldKey }}]">
                    @endforeach
                </tr>
            </template>

            <x-modal data-line-item-modal size="lg" title="일정 상세보기">
                <div class="form-framework grid gap-4 md:grid-cols-2">
                    <x-form-group label="출발지" for="line-item-pickup-location">
                        <input id="line-item-pickup-location" type="text" class="input-field" placeholder="예: 서울 강남구" data-line-item-field="pickup_location">
                    </x-form-group>
                    <x-form-group label="도착지" for="line-item-dropoff-location">
                        <input id="line-item-dropoff-location" type="text" class="input-field" placeholder="예: 인천공항" data-line-item-field="dropoff_location">
                    </x-form-group>
                    <x-form-group label="픽업 일시" for="line-item-scheduled-time">
                        <input id="line-item-scheduled-time" type="text" class="input-field" placeholder="예: 08:42" data-line-item-field="scheduled_time">
                    </x-form-group>
                    <x-form-group label="항공편" for="line-item-flight-number">
                        <input id="line-item-flight-number" type="text" class="input-field" placeholder="예: KE123" data-line-item-field="flight_number">
                    </x-form-group>
                    <x-form-group label="월" for="line-item-service-month">
                        <input id="line-item-service-month" type="number" min="1" max="12" class="input-field" placeholder="예: 3" data-line-item-field="service_month">
                    </x-form-group>
                    <x-form-group label="일" for="line-item-service-day">
                        <input id="line-item-service-day" type="number" min="1" max="31" class="input-field" placeholder="예: 3" data-line-item-field="service_day">
                    </x-form-group>
                    <x-form-group label="요일" for="line-item-service-weekday">
                        <input id="line-item-service-weekday" type="text" class="input-field" placeholder="예: 화요일" data-line-item-field="service_weekday">
                    </x-form-group>
                    <x-form-group label="운행 구분" for="line-item-service-type">
                        <input id="line-item-service-type" type="text" class="input-field" placeholder="예: 샌딩" data-line-item-field="service_type">
                    </x-form-group>
                    <x-form-group label="인원 수" for="line-item-passenger-count">
                        <input id="line-item-passenger-count" type="number" min="1" max="99" class="input-field" placeholder="예: 2" data-line-item-field="passenger_count">
                    </x-form-group>
                    <x-form-group label="짐" for="line-item-luggage-count">
                        <input id="line-item-luggage-count" type="number" min="0" max="99" class="input-field" placeholder="예: 1" data-line-item-field="luggage_count">
                    </x-form-group>
                    <x-form-group label="금액" for="line-item-amount-value">
                        <input id="line-item-amount-value" type="number" min="0" max="999999999" class="input-field" placeholder="예: 18000" data-line-item-field="amount_value">
                    </x-form-group>
                    <x-form-group label="금액(문구)" for="line-item-amount-text">
                        <input id="line-item-amount-text" type="text" class="input-field" placeholder="예: 18만" data-line-item-field="amount_text">
                    </x-form-group>
                    <x-form-group label="장소" for="line-item-location">
                        <input id="line-item-location" type="text" class="input-field" placeholder="예: 중구" data-line-item-field="location">
                    </x-form-group>
                    <x-form-group label="요약 문구" for="line-item-service-date">
                        <input id="line-item-service-date" type="text" class="input-field" placeholder="예: 3월3일" data-line-item-field="service_date">
                    </x-form-group>
                </div>

                <x-slot name="footer">
                    <button type="button" class="btn-secondary" data-modal-close>취소</button>
                    <button type="button" class="btn-primary" data-line-item-save>저장</button>
                </x-slot>
            </x-modal>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap gap-2">
        <button type="submit" class="btn-primary" title="{{ $formSubmitTitle }}" aria-label="{{ $formSubmitLabel }}">{{ $formSubmitLabel }}</button>
        <a href="{{ $formCancelUrl }}" class="btn-secondary" title="{{ $formCancelTitle }}">{{ $formCancelLabel }}</a>
    </div>
</form>
