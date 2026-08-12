<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\StoreStructuredOrderRequest;
use App\Http\Requests\StructureOrderSummaryRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderSummaryAiStructurer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class OrderManagementController extends Controller
{
    public function create(): View
    {
        $actor = $this->resolveActor();

        return view('dashboard.business.orders.create', [
            'businessModule' => DashboardWorkspaceController::findNowhereBusinessModule('order'),
            'businessModules' => DashboardWorkspaceController::nowhereBusinessModules(),
            'module' => DashboardWorkspaceController::findBusinessModule('nowhere'),
            'modules' => DashboardWorkspaceController::businessModules(),
            'order' => new Order([
                'order_type' => Order::TYPE_GENERAL,
                'status' => Order::STATUS_DRAFT,
            ]),
            'statusOptions' => Order::statusOptions(),
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $actor = $this->resolveActor();

        $validated = $request->validated();
        $lineItems = $this->normalizeLineItems($validated['line_items'] ?? []);

        $order = Order::create([
            ...$this->routeFieldsFromLineItems($lineItems),
            'order_number' => Order::generateOrderNumber(),
            'reservation_company' => '직접예약',
            'customer_name' => '미지정',
            'reservation_channel' => Order::CHANNEL_KAKAO,
            'order_type' => Order::TYPE_GENERAL,
            'status' => $validated['status'] ?? Order::STATUS_DRAFT,
            'group_type' => $this->resolveGroupType($validated['group_type'] ?? null),
            'user_id' => $actor->id,
        ]);

        foreach ($lineItems as $lineItem) {
            $order->lineItems()->create($lineItem);
        }

        return redirect()
            ->route('dashboard.business.order.show', $order)
            ->with('status', '예약이 등록되었습니다.');
    }

    public function structureSummary(
        StructureOrderSummaryRequest $request,
        OrderSummaryAiStructurer $structurer,
    ): JsonResponse {
        $actor = $this->resolveActor();

        try {
            return response()->json([
                'message' => '운행 요약을 구조화했습니다.',
                'structured' => $structurer->structure((string) $request->string('summary')),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (HttpExceptionInterface $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode(), $exception->getHeaders());
        }
    }

    public function storeStructured(StoreStructuredOrderRequest $request): RedirectResponse
    {
        $actor = $this->resolveActor();

        $rawStructured = $request->validated('structured');
        $structured = is_string($rawStructured)
            ? (json_decode($rawStructured, true) ?: [])
            : ($rawStructured ?? []);

        // 원본 입력 내용과 AI 반환 JSON은 본데이터 보관을 위해 그대로 유지한다.
        $structuredPayload = $structured;

        // AI 응답 래퍼({"message":...,"structured":{...}})가 전달되면 내부 structured를 추출한다.
        $structured = $structured['structured'] ?? $structured;

        $order = DB::transaction(function () use ($structured, $structuredPayload, $request, $actor): Order {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'original_summary' => $request->validated('original_summary') ?: null,
                'structured_payload' => $structuredPayload,
                'reservation_company' => '직접예약',
                'customer_name' => '미지정',
                'reservation_channel' => Order::CHANNEL_KAKAO,
                'request_label' => $structured['request_label'] ?? null,
                'service_date' => $structured['service_date'] ?? null,
                'service_time' => $structured['service_time'] ?? ($structured['scheduled_time'] ?? null),
                'group_type' => $this->resolveGroupType(
                    $request->filled('group_type') ? (string) $request->string('group_type') : ($structured['group_type'] ?? null)
                ),
                'vehicle_type' => $structured['vehicle_type'] ?? null,
                'service_type' => $structured['service_type'] ?? null,
                'passenger_count' => $this->resolvePassengerCount($structured),
                'luggage_count' => $structured['luggage_count'] ?? null,
                'amount_text' => $structured['amount_text'] ?? null,
                'amount_value' => $structured['amount_value'] ?? null,
                'extra_options' => $structured['extra_options'] ?? [],
                'pickup_location' => $structured['pickup_location'] ?? null,
                'dropoff_location' => $structured['dropoff_location'] ?? null,
                'flight_number' => $structured['flight_number'] ?? null,
                'scheduled_at' => $this->resolveScheduledAt($structured),
                'order_type' => $this->resolveOrderTypeCode($structured['order_type'] ?? null),
                'status' => Order::STATUS_DRAFT,
                'user_id' => $actor->id,
            ]);

            foreach ($structured['line_items'] ?? [] as $lineItem) {
                $order->lineItems()->create([
                    'scheduled_time' => $lineItem['scheduled_time'] ?? null,
                    'service_date' => $lineItem['service_date'] ?? ($structured['service_date'] ?? null),
                    'service_month' => $lineItem['service_month'] ?? null,
                    'service_day' => $lineItem['service_day'] ?? null,
                    'service_weekday' => $lineItem['service_weekday'] ?? null,
                    'service_type' => $lineItem['service_type'] ?? null,
                    'location' => $lineItem['location'] ?? null,
                    'pickup_location' => $lineItem['pickup_location'] ?? null,
                    'dropoff_location' => $lineItem['dropoff_location'] ?? null,
                    'flight_number' => $lineItem['flight_number'] ?? null,
                    'passenger_count' => $lineItem['passenger_count'] ?? null,
                    'luggage_count' => $lineItem['luggage_count'] ?? null,
                    'amount_value' => $lineItem['amount_value'] ?? null,
                    'amount_text' => $lineItem['amount_text'] ?? null,
                ]);
            }

            return $order;
        });

        return redirect()
            ->route('dashboard.business.order.show', $order)
            ->with('status', 'AI 구조화 결과로 예약이 등록되었습니다.');
    }

    public function show(Order $order): View
    {
        $actor = $this->resolveActor();

        $order->load(['user', 'lineItems', 'group.orders.lineItems']);

        return view('dashboard.business.orders.show', [
            'businessModule' => DashboardWorkspaceController::findNowhereBusinessModule('order'),
            'businessModules' => DashboardWorkspaceController::nowhereBusinessModules(),
            'module' => DashboardWorkspaceController::findBusinessModule('nowhere'),
            'modules' => DashboardWorkspaceController::businessModules(),
            'order' => $order,
            'statusOptions' => Order::statusOptions(),
            'canClaimOrder' => $actor->hasPermission('order.create')
                && in_array($order->status, [
                    Order::STATUS_PUBLISHED,
                    Order::STATUS_TRADING,
                    Order::STATUS_ACCEPTANCE_PENDING,
                ], true)
                && $order->user_id !== $actor->id,
        ]);
    }

    public function edit(Order $order): View
    {
        $actor = $this->resolveActor();

        $order->load(['lineItems']);

        return view('dashboard.business.orders.edit', [
            'businessModule' => DashboardWorkspaceController::findNowhereBusinessModule('order'),
            'businessModules' => DashboardWorkspaceController::nowhereBusinessModules(),
            'module' => DashboardWorkspaceController::findBusinessModule('nowhere'),
            'modules' => DashboardWorkspaceController::businessModules(),
            'order' => $order,
            'statusOptions' => Order::statusOptions(),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $actor = $this->resolveActor();

        $validated = $request->validated();
        $lineItems = $this->normalizeLineItems($validated['line_items'] ?? []);

        DB::transaction(function () use ($order, $validated, $lineItems): void {
            $order->update([
                ...$this->routeFieldsFromLineItems($lineItems),
                'status' => $validated['status'] ?? $order->status,
                'group_type' => $this->resolveGroupType($validated['group_type'] ?? null),
            ]);

            $keptLineItemIds = [];

            foreach ($lineItems as $key => $lineItem) {
                if (is_numeric($key)) {
                    $lineItemModel = $order->lineItems()->findOrFail((int) $key);
                    $lineItemModel->update($lineItem);
                    $keptLineItemIds[] = $lineItemModel->id;
                } else {
                    $lineItemModel = $order->lineItems()->create($lineItem);
                    $keptLineItemIds[] = $lineItemModel->id;
                }
            }

            // 제출되지 않은 기존 일정은 삭제한다. (모든 행이 제거되면 전체 삭제)
            $order->lineItems()
                ->whereNotIn('id', $keptLineItemIds)
                ->delete();
        });

        return redirect()
            ->route('dashboard.business.order.show', $order)
            ->with('status', '운행이 수정되었습니다.');
    }

    /**
     * 라이프사이클 규칙에 따라 운행 상태를 전환한다.
     */
    public function transition(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $actor = $this->resolveActor();

        $status = $request->validated('status');

        if (! $order->canTransitionTo($status)) {
            return back()->with('error', '운행 상태를 전환할 수 없는 단계입니다.');
        }

        $order->transitionTo($status);

        return back()->with('status', sprintf('운행 상태가 "%s"(으)로 변경되었습니다.', Order::statusOptions()[$status] ?? $status));
    }

    /**
     * 마켓의 공개 운행을 내 운행으로 가져온다(수락).
     */
    public function claim(Order $order): RedirectResponse
    {
        $actor = $this->resolveActor();

        abort_unless(in_array($order->status, [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
            Order::STATUS_ACCEPTANCE_PENDING,
        ], true), 403);

        abort_unless($order->user_id !== $actor->id, 403);

        $order->forceFill([
            'user_id' => $actor->id,
            'status' => Order::STATUS_ACCEPTED,
            'claimed_at' => now(),
        ])->save();

        return redirect()
            ->route('dashboard.business.order.show', $order)
            ->with('status', '운행을 내 운행으로 가져왔습니다.');
    }

    /**
     * 일정 배열 전체를 정규화한다.
     *
     * @param  array<int|string, array<string, mixed>>  $lineItems
     * @return array<int|string, array<string, mixed>>
     */
    private function normalizeLineItems(array $lineItems): array
    {
        $normalized = [];

        foreach ($lineItems as $key => $lineItem) {
            $lineItem = $this->normalizeLineItem($lineItem);

            if ($this->isEmptyLineItem($lineItem)) {
                continue;
            }

            $lineItem['service_date'] = $this->resolveLineItemServiceDate($lineItem);
            $normalized[$key] = $lineItem;
        }

        return $normalized;
    }

    /**
     * 일정 입력에서 일정 테이블 컬럼만 남긴다.
     *
     * @param  array<string, mixed>  $lineItem
     * @return array<string, mixed>
     */
    private function normalizeLineItem(array $lineItem): array
    {
        return array_intersect_key($lineItem, array_flip([
            'scheduled_time',
            'service_date',
            'service_month',
            'service_day',
            'service_weekday',
            'service_type',
            'location',
            'pickup_location',
            'dropoff_location',
            'flight_number',
            'passenger_count',
            'luggage_count',
            'amount_value',
            'amount_text',
        ]));
    }

    /**
     * 첫 번째 유효한 일정에서 운행 루트 라우트 필드(출발지/도착지/항공편/픽업시각/인원/금액)를 파생한다.
     *
     * @param  array<int|string, array<string, mixed>>  $lineItems
     * @return array<string, mixed>
     */
    private function routeFieldsFromLineItems(array $lineItems): array
    {
        $first = $lineItems[array_key_first($lineItems)] ?? [];

        if ($first === []) {
            return [];
        }

        $serviceDate = $first['service_date'] ?? null;
        $scheduledTime = $first['scheduled_time'] ?? null;
        $scheduledAt = null;

        if ($serviceDate !== null && $serviceDate !== '') {
            $base = $this->parseServiceDate($serviceDate) ?? $serviceDate;

            if (is_string($scheduledTime) && preg_match('/^([01]?\d|2[0-3])[:.]?([0-5]\d)?$/', trim($scheduledTime), $matches) === 1) {
                $minutes = $matches[2] ?? '00';
                $base .= ' '.sprintf('%02d:%s', (int) $matches[1], $minutes);
            }

            try {
                $scheduledAt = Carbon::parse($base);
            } catch (Throwable) {
                $scheduledAt = null;
            }
        }

        $passengerCount = $first['passenger_count'] ?? null;

        // 인원이 미입력된 경우 일정 전체의 인원 합계로 대체하고, 그래도 없으면 기본값 1을 사용한다.
        if ($passengerCount === null || $passengerCount === '') {
            $passengerCount = collect($lineItems)
                ->sum(fn (array $lineItem): int => (int) ($lineItem['passenger_count'] ?? 0));
        }

        return [
            'pickup_location' => $first['pickup_location'] ?? null,
            'dropoff_location' => $first['dropoff_location'] ?? null,
            'flight_number' => $first['flight_number'] ?? null,
            'scheduled_at' => $scheduledAt,
            'passenger_count' => (int) $passengerCount > 0 ? (int) $passengerCount : 1,
            'expected_revenue' => $first['amount_value'] ?? null,
        ];
    }

    /**
     * "M월D일" 또는 "Y-m-d" 형태의 service_date를 Carbon이 파싱 가능한 날짜 문자열로 변환한다.
     */
    private function parseServiceDate(string $serviceDate): ?string
    {
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $serviceDate) === 1) {
            return $serviceDate;
        }

        if (preg_match('/(\d{1,2})월(\d{1,2})일/u', $serviceDate, $matches) !== 1) {
            return null;
        }

        return now()
            ->copy()
            ->setMonth((int) $matches[1])
            ->setDay((int) $matches[2])
            ->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $lineItem
     */
    private function isEmptyLineItem(array $lineItem): bool
    {
        foreach ($lineItem as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * 월/일 입력이 있으면 "M월D일" 형태의 service_date를 자동으로 만든다.
     *
     * @param  array<string, mixed>  $lineItem
     */
    private function resolveLineItemServiceDate(array $lineItem): ?string
    {
        $month = $lineItem['service_month'] ?? null;
        $day = $lineItem['service_day'] ?? null;

        if ($month === null || $day === null || $month === '' || $day === '') {
            return $lineItem['service_date'] ?? null;
        }

        return sprintf('%d월%d일', (int) $month, (int) $day);
    }

    /**
     * 단일 예약은 null, 셋트 예약만 "셋트"로 저장한다. (상세 화면의 묶음 테이블 분기 기준)
     */
    private function resolveGroupType(?string $groupType): ?string
    {
        return trim((string) $groupType) === '셋트' ? '셋트' : null;
    }

    /**
     * 최상위 인원이 없으면 일정별 인원 합계로 대체하고, 그래도 없으면 기본값 1을 사용한다.
     *
     * @param  array<string, mixed>  $structured
     */
    private function resolvePassengerCount(array $structured): int
    {
        $explicitCount = $structured['passenger_count'] ?? null;

        if (is_numeric($explicitCount) && (int) $explicitCount > 0) {
            return (int) $explicitCount;
        }

        $lineItems = $structured['line_items'] ?? [];

        if (is_array($lineItems) && $lineItems !== []) {
            $sum = collect($lineItems)->sum(fn (array $lineItem): int => (int) ($lineItem['passenger_count'] ?? 0));

            if ($sum > 0) {
                return $sum;
            }
        }

        return 1;
    }

    /**
     * 구조화된 service_date + 시각을 하나의 Carbon 스케줄로 조합한다.
     *
     * @param  array<string, mixed>  $structured
     */
    private function resolveScheduledAt(array $structured): ?Carbon
    {
        $serviceDate = $structured['service_date'] ?? null;
        $scheduledTime = $structured['scheduled_time'] ?? ($structured['service_time'] ?? null);

        if (! is_string($scheduledTime) || trim($scheduledTime) === '') {
            return null;
        }

        $datePart = null;

        if ($serviceDate !== null && $serviceDate !== '') {
            $datePart = $this->parseServiceDate((string) $serviceDate) ?? (string) $serviceDate;
        }

        try {
            return $datePart !== null
                ? Carbon::parse($datePart.' '.trim($scheduledTime))
                : Carbon::parse(trim($scheduledTime));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 구조화된 운행 유형 라벨을 저장용 코드로 변환한다.
     */
    private function resolveOrderTypeCode(?string $orderType): string
    {
        return match ($orderType) {
            '공항 운행' => Order::TYPE_AIRPORT,
            '비즈니스 운행' => Order::TYPE_BUSINESS,
            default => Order::TYPE_GENERAL,
        };
    }

    private function resolveActor(): User
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
