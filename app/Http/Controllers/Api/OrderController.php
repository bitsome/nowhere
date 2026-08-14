<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Review;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\OrderSummaryAiStructurer;
use App\Support\Orders\OrderWorkspaceListBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class OrderController extends Controller
{
    /**
     * 마켓(가져올 수 있는 운행) 또는 내가 받은 운행 목록.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function index(Request $request): JsonResponse
    {
        $scope = $request->string('scope', 'market')->toString();
        $tab = $request->string('tab', '진행중')->toString();
        $source = $request->string('source')->toString();
        $search = trim($request->string('search')->toString());
        $serviceType = $request->string('service_type')->toString();
        $date = $request->string('date')->toString();
        $region = trim($request->string('region')->toString());
        $vehicleType = $request->string('vehicle_type')->toString();
        $minAmount = $request->integer('min_amount', 0);
        $maxAmount = $request->integer('max_amount', 0);
        $minPassengers = $request->integer('min_passengers', 0);
        $sort = $request->string('sort', 'latest')->toString();

        $query = Order::query();

        if ($scope === 'mine') {
            $query->where('user_id', $request->user()->id);

            // 등록된 운행(직접 등록) / 받은 운행(마켓에서 가져옴)
            if ($source === 'registered') {
                $query->whereNull('claimed_at');
            } else {
                $query->whereNotNull('claimed_at');
            }

            if ($tab === '초안') {
                $query->where('status', Order::STATUS_DRAFT);
            } else {
                match ($tab) {
                    '완료' => $query->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED]),
                    '취소' => $query->where('status', Order::STATUS_CANCELLED),
                    default => $query->whereNotIn('status', [
                        Order::STATUS_DRAFT,
                        Order::STATUS_COMPLETED,
                        Order::STATUS_SETTLED,
                        Order::STATUS_CANCELLED,
                    ]),
                };
            }
        } else {
            $query->whereIn('status', [
                Order::STATUS_PUBLISHED,
                Order::STATUS_TRADING,
                Order::STATUS_ACCEPTANCE_PENDING,
            ])->where('user_id', '!=', $request->user()->id);

            // 마켓 공개 목록 — 서비스 날짜가 이미 지난 운행은 노출하지 않는다 (날짜 미정 운행은 유지, KST 기준)
            $query->where(function ($sub) {
                $sub->where('service_date', '>=', now('Asia/Seoul')->format('Y-m-d'))
                    ->orWhereNull('service_date')
                    ->orWhere('service_date', '');
            });
        }

        if (in_array($serviceType, ['pickup', 'sending', 'landing'], true)) {
            $query->where('service_type', $serviceType);
        }

        if ($date !== '') {
            $query->where('service_date', $date);
        }
        if ($region !== '') {
            $query->where(function ($sub) use ($region) {
                $sub->where('pickup_location', 'like', "%{$region}%")
                    ->orWhere('dropoff_location', 'like', "%{$region}%");
            });
        }

        if ($vehicleType !== '') {
            $query->where('vehicle_type', 'like', "%{$vehicleType}%");
        }

        if ($minAmount > 0) {
            $query->where('expected_revenue', '>=', $minAmount);
        }

        if ($maxAmount > 0) {
            $query->where('expected_revenue', '<=', $maxAmount);
        }

        if ($minPassengers > 0) {
            $query->where('passenger_count', '>=', $minPassengers);
        }

        // 마켓 상단 퀵 칩 필터 — new(최신 등록) / urgent(서비스 임박) / today(오늘) / amount(금액순) / priority(긴급)
        $quick = $request->string('quick')->toString();

        if ($quick === 'amount') {
            // 금액순은 정렬로 처리
            $sort = 'amount';
        }

        if (in_array($quick, ['new', 'urgent', 'today', 'priority'], true)) {
            $nowKst = now('Asia/Seoul');

            match ($quick) {
                // 최신 — 2시간 이내 등록 (목록 빌더 isNew와 동일 기준)
                'new' => $query->where('created_at', '>=', now()->subHours(2)),
                // 임박 — 오늘 서비스 + 2시간 이내 시작 (isUrgent와 동일 기준)
                'urgent' => $query
                    ->where('service_date', $nowKst->format('Y-m-d'))
                    ->where('service_time', '>=', $nowKst->format('H:i'))
                    ->where('service_time', '<=', $nowKst->copy()->addMinutes(120)->format('H:i')),
                'today' => $query->where('service_date', $nowKst->format('Y-m-d')),
                'priority' => $query->where('is_priority', true),
                default => null,
            };
        }

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('pickup_location', 'like', "%{$search}%")
                    ->orWhere('dropoff_location', 'like', "%{$search}%")
                    ->orWhere('reservation_company', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $orders = match ($sort) {
            'date' => $query
                ->orderByRaw("CASE WHEN service_date IS NULL OR service_date = '' THEN 1 ELSE 0 END")
                ->orderBy('service_date')
                ->orderBy('service_time')
                ->paginate($perPage),
            'date_desc' => $query
                ->orderByRaw("CASE WHEN service_date IS NULL OR service_date = '' THEN 1 ELSE 0 END")
                ->orderByDesc('service_date')
                ->orderByDesc('service_time')
                ->paginate($perPage),

            'amount' => $query->orderByDesc('expected_revenue')->paginate($perPage),
            'amount_asc' => $query->orderBy('expected_revenue')->paginate($perPage),
            default => $query->latest()->paginate($perPage),
        };

        $rows = app(OrderWorkspaceListBuilder::class)->build(collect($orders->items()), null, $sort);

        // 등록자 신뢰 정보는 마켓에서만 계산 (내 운행에는 불필요)
        if ($scope === 'market') {
            $rows = $this->withOwnerTrust($rows, $orders->items());
        }

        return response()->json([
            'data' => $rows,
            'meta' => [
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * 마켓 응답에 등록자 신뢰 정보(평점·리뷰 수·완료 수)를 붙인다. N+1 방지를 위해 bulk로 조회한다.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, Order>  $orderItems
     * @return array<int, array<string, mixed>>
     */
    private function withOwnerTrust(array $rows, array $orderItems): array
    {
        $orders = collect($orderItems);
        $ownerIds = $orders->pluck('user_id')->filter()->unique();

        $names = User::query()->whereIn('id', $ownerIds)->pluck('name', 'id');

        $reviewStats = Review::query()
            ->whereIn('reviewee_id', $ownerIds)
            ->selectRaw('reviewee_id, COUNT(*) as cnt, AVG(rating) as avg')
            ->groupBy('reviewee_id')
            ->get()
            ->keyBy('reviewee_id');

        $completedCounts = Order::query()
            ->whereIn('user_id', $ownerIds)
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $ownerIdByOrderId = $orders->pluck('user_id', 'id');

        foreach ($rows as &$row) {
            $ownerId = $ownerIdByOrderId[$row['id']] ?? null;

            if ($ownerId === null) {
                $row['owner'] = null;

                continue;
            }

            $rating = $reviewStats[$ownerId] ?? null;
            $completed = $completedCounts[$ownerId] ?? null;

            $row['owner'] = [
                'id' => $ownerId,
                'name' => $names[$ownerId] ?? '',
                'rating' => $rating ? round((float) $rating->avg, 1) : 0,
                'review_count' => (int) ($rating->cnt ?? 0),
                'completed_count' => (int) ($completed->cnt ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * 운행 상세 — 라인아이템, 셋트면 그룹 전체 일정 포함.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'lineItems', 'group.orders.lineItems', 'group.orders.user']);

        return response()->json([
            'data' => [
                'order' => $order->toArray(),
                'group' => $order->group?->toArray(),
                'statusOptions' => Order::statusOptions(),
                'nextTransitions' => array_values(Order::STATUS_FLOW[$order->status] ?? []),
            ],
        ]);
    }

    /**
     * 마켓의 공개 운행을 내 운행으로 가져온다(수락).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function claim(Request $request, Order $order): JsonResponse
    {
        $actor = $request->user();

        abort_unless(in_array($order->status, [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
            Order::STATUS_ACCEPTANCE_PENDING,
        ], true), 403);

        abort_unless($order->user_id !== $actor->id, 403);

        $previousOwnerId = $order->user_id;

        // 첫 가져오기라면 원 등록자를 기록 (상호 리뷰 대상 식별)
        if ($order->original_owner_id === null) {
            $order->forceFill(['original_owner_id' => $previousOwnerId]);
        }

        $order->forceFill([
            'user_id' => $actor->id,
            'status' => Order::STATUS_ACCEPTED,
            'claimed_at' => now(),
        ])->save();

        // 레벨링: 마켓 운행 가져오기 +20 XP
        $actor->addXp(20, 'order_claimed', '운행 가져오기 (수락)');

        // 기사 상태 자동 연동 — 가져온 순간부터 '운행 중'
        $actor->driver()->updateOrCreate(
            ['user_id' => $actor->id],
            ['status' => Driver::STATUS_ON_TRIP, 'status_updated_at' => now()],
        );

        // 가져온 드라이버에게 알림
        $actor->notify(new OrderNotification(
            '운행 가져오기 완료',
            "{$order->customer_name}님의 운행({$order->order_number})를 내 운행으로 가져왔습니다.",
            $order->id,
        ));

        // 원 소유자(등록한 운영자)에게 알림
        if ($previousOwnerId !== null && $previousOwnerId !== $actor->id) {
            $previousOwner = User::query()->find($previousOwnerId);

            if ($previousOwner !== null) {
                $previousOwner->notify(new OrderNotification(
                    '운행 가져오기됨',
                    "운행({$order->order_number})가 다른 드라이버에게 가져와졌습니다.",
                    $order->id,
                ));
            }
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * 라이프사이클 규칙에 따라 운행 상태를 전환한다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function transition(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $status = $data['status'];

        if (! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => ['전환할 수 없는 상태입니다.'],
            ]);
        }

        $order->transitionTo($status);

        // 취소 사유 기록
        if ($status === Order::STATUS_CANCELLED && filled($data['cancel_reason'] ?? null)) {
            $order->forceFill(['cancel_reason' => $data['cancel_reason']])->save();
        }

        // 상태 변경을 운행 소유자(드라이버/운영자)에게 알림
        $owner = User::query()->find($order->user_id);

        // 레벨링: 운행 완료 +50, 정산 완료 +30 XP (운행 소유자에게)
        if ($owner !== null) {
            if ($status === Order::STATUS_COMPLETED) {
                $owner->addXp(50, 'order_completed', '운행 완료');
            } elseif ($status === Order::STATUS_SETTLED) {
                $owner->addXp(30, 'order_settled', '정산 완료');
            }

            $statusLabel = Order::statusOptions()[$order->status] ?? $order->status;

            $owner->notify(new OrderNotification(
                '운행 상태 변경',
                "운행({$order->order_number})의 상태가 '{$statusLabel}'(으)로 변경되었습니다.",
                $order->id,
            ));
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * 완료된 운행을 선택해 일괄 정산 처리한다.
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function batchSettle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $user = $request->user();

        $orders = Order::query()
            ->whereIn('id', $data['ids'])
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->get();

        foreach ($orders as $order) {
            $order->transitionTo(Order::STATUS_SETTLED);
        }

        return response()->json([
            'data' => ['settled' => $orders->count()],
        ]);
    }

    /**
     * 운행을 등록한다 (단일 운행).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateOrderPayload($request);

        $order = DB::transaction(function () use ($data, $request): Order {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'reservation_company' => $data['reservation_company'] ?? '직접예약',
                'customer_name' => $data['customer_name'] ?? '미지정',
                'reservation_channel' => $data['reservation_channel'] ?? Order::CHANNEL_KAKAO,
                'group_type' => '단일',
                'vehicle_type' => $data['vehicle_type'] ?? null,
                'service_type' => $data['service_type'] ?? null,
                'service_date' => $data['service_date'] ?? null,
                'service_time' => $data['service_time'] ?? null,
                'service_datetime' => $data['service_datetime'] ?? null,
                'pickup_location' => $data['pickup_location'] ?? null,
                'dropoff_location' => $data['dropoff_location'] ?? null,
                'flight_number' => $data['flight_number'] ?? null,
                'passenger_count' => $data['passenger_count'] ?? 1,
                'luggage_count' => $data['luggage_count'] ?? null,
                'expected_revenue' => $data['expected_revenue'] ?? null,
                'amount_value' => $data['expected_revenue'] ?? null,
                'order_type' => Order::TYPE_GENERAL,
                'status' => Order::STATUS_DRAFT,
                'is_priority' => $data['is_priority'] ?? false,
                'user_id' => $request->user()->id,
            ]);

            foreach ($data['line_items'] ?? [] as $lineItem) {
                $order->lineItems()->create($lineItem);
            }

            return $order;
        });

        // 레벨링: 운행 등록 +10 XP
        $request->user()->addXp(10, 'order_created', '운행 등록');

        return response()->json([
            'data' => [
                'id' => $order->id,
                'orderNumber' => $order->order_number,
                'status' => $order->status,
            ],
        ], 201);
    }

    /**
     * 운행을 복제한다 — 동일 내용을 초안 상태로 새로 만든다.
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function duplicate(Request $request, Order $order): JsonResponse
    {
        $copy = DB::transaction(function () use ($request, $order): Order {
            $copy = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'reservation_company' => $order->reservation_company,
                'customer_name' => $order->customer_name,
                'reservation_channel' => $order->reservation_channel,
                'group_type' => '단일',
                'vehicle_type' => $order->vehicle_type,
                'service_type' => $order->service_type,
                'service_date' => $order->service_date,
                'service_time' => $order->service_time,
                'service_datetime' => $order->service_datetime,
                'pickup_location' => $order->pickup_location,
                'dropoff_location' => $order->dropoff_location,
                'flight_number' => $order->flight_number,
                'passenger_count' => $order->passenger_count,
                'luggage_count' => $order->luggage_count,
                'expected_revenue' => $order->expected_revenue,
                'amount_value' => $order->amount_value,
                'order_type' => Order::TYPE_GENERAL,
                'status' => Order::STATUS_DRAFT,
                'user_id' => $request->user()->id,
            ]);

            foreach ($order->lineItems as $lineItem) {
                $copy->lineItems()->create($lineItem->only([
                    'service_date',
                    'service_time',
                    'scheduled_time',
                    'service_type',
                    'pickup_location',
                    'dropoff_location',
                    'flight_number',
                    'amount_text',
                    'amount_value',
                    'service_weekday',
                ]));
            }

            return $copy;
        });

        return response()->json([
            'data' => ['id' => $copy->id],
        ], 201);
    }

    /**
     * 셋트 운행 등록 — 한 번에 여러 운행을 등록하고 하나의 그룹으로 묶는다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function batchStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_name' => ['required', 'string', 'max:100'],
            'orders' => ['required', 'array', 'min:2', 'max:30'],
            'orders.*.service_date' => ['nullable', 'string', 'max:20'],
            'orders.*.service_time' => ['nullable', 'string', 'max:10'],
            'orders.*.service_datetime' => ['nullable', 'string', 'max:20'],
            'orders.*.service_type' => ['nullable', 'string', 'max:50'],
            'orders.*.pickup_location' => ['nullable', 'string', 'max:200'],
            'orders.*.dropoff_location' => ['nullable', 'string', 'max:200'],
            'orders.*.flight_number' => ['nullable', 'string', 'max:20'],
            'orders.*.passenger_count' => ['nullable', 'integer', 'min:0'],
            'orders.*.luggage_count' => ['nullable', 'integer', 'min:0'],
            'orders.*.expected_revenue' => ['nullable', 'integer', 'min:0'],
            'orders.*.vehicle_type' => ['nullable', 'string', 'max:50'],
            'orders.*.customer_name' => ['nullable', 'string', 'max:100'],
            'orders.*.reservation_company' => ['nullable', 'string', 'max:100'],
            'orders.*.line_items' => ['array'],
        ]);

        $group = DB::transaction(function () use ($data, $request): OrderGroup {
            $group = OrderGroup::query()->create([
                'name' => $data['group_name'],
                'type' => '셋트',
            ]);

            foreach ($data['orders'] as $orderData) {
                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'group_id' => $group->id,
                    'reservation_company' => $orderData['reservation_company'] ?? '직접예약',
                    'customer_name' => $orderData['customer_name'] ?? '미지정',
                    'reservation_channel' => $orderData['reservation_channel'] ?? Order::CHANNEL_KAKAO,
                    'group_type' => '셋트',
                    'vehicle_type' => $orderData['vehicle_type'] ?? null,
                    'service_type' => $orderData['service_type'] ?? null,
                    'service_date' => $orderData['service_date'] ?? null,
                    'service_time' => $orderData['service_time'] ?? null,
                    'service_datetime' => $orderData['service_datetime'] ?? null,
                    'pickup_location' => $orderData['pickup_location'] ?? null,
                    'dropoff_location' => $orderData['dropoff_location'] ?? null,
                    'flight_number' => $orderData['flight_number'] ?? null,
                    'passenger_count' => $orderData['passenger_count'] ?? 1,
                    'luggage_count' => $orderData['luggage_count'] ?? null,
                    'expected_revenue' => $orderData['expected_revenue'] ?? null,
                    'amount_value' => $orderData['expected_revenue'] ?? null,
                    'order_type' => Order::TYPE_GENERAL,
                    'status' => Order::STATUS_DRAFT,
                    'user_id' => $request->user()->id,
                ]);

                foreach ($orderData['line_items'] ?? [] as $lineItem) {
                    $order->lineItems()->create($lineItem);
                }
            }

            return $group;
        });

        return response()->json([
            'data' => [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'order_count' => $group->orders()->count(),
            ],
        ], 201);
    }

    /**
     * 운행 정보를 수정한다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $data = $this->validateOrderPayload($request);

        $order->update([
            'reservation_company' => $data['reservation_company'] ?? $order->reservation_company,
            'customer_name' => $data['customer_name'] ?? $order->customer_name,
            'reservation_channel' => $data['reservation_channel'] ?? $order->reservation_channel,
            'vehicle_type' => $data['vehicle_type'] ?? $order->vehicle_type,
            'service_type' => $data['service_type'] ?? $order->service_type,
            'service_date' => $data['service_date'] ?? $order->service_date,
            'service_time' => $data['service_time'] ?? $order->service_time,
            'service_datetime' => $data['service_datetime'] ?? $order->service_datetime,
            'pickup_location' => $data['pickup_location'] ?? $order->pickup_location,
            'dropoff_location' => $data['dropoff_location'] ?? $order->dropoff_location,
            'flight_number' => $data['flight_number'] ?? $order->flight_number,
            'passenger_count' => $data['passenger_count'] ?? $order->passenger_count,
            'luggage_count' => $data['luggage_count'] ?? $order->luggage_count,
            'expected_revenue' => $data['expected_revenue'] ?? $order->expected_revenue,
            'amount_value' => $data['expected_revenue'] ?? $order->amount_value,
            'is_priority' => $data['is_priority'] ?? $order->is_priority,
        ]);

        if (array_key_exists('line_items', $data)) {
            $order->lineItems()->delete();

            foreach ($data['line_items'] as $lineItem) {
                $order->lineItems()->create($lineItem);
            }
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * 셋트 그룹에서 개별 운행을 분리한다 — 단일 운행으로 전환.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function detachFromGroup(Request $request, Order $order): JsonResponse
    {
        if ($order->group_id === null) {
            return response()->json([
                'data' => ['id' => $order->id, 'group_id' => null],
            ]);
        }

        $order->update([
            'group_id' => null,
            'group_type' => '단일',
        ]);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'group_id' => $order->group_id,
            ],
        ]);
    }

    /**
     * 운행 요약 텍스트를 AI로 구조화한다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function structure(Request $request, OrderSummaryAiStructurer $structurer): JsonResponse
    {
        $summary = $request->validate([
            'summary' => ['required', 'string', 'max:2000'],
        ])['summary'];

        try {
            return response()->json([
                'data' => [
                    'structured' => $structurer->structure($summary),
                ],
            ]);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (HttpExceptionInterface $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode(), $exception->getHeaders());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOrderPayload(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['nullable', 'string', 'max:100'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'service_type' => ['nullable', 'string', 'in:pickup,sending,landing'],
            'service_date' => ['nullable', 'string', 'max:20'],
            'service_time' => ['nullable', 'string', 'max:10'],
            'service_datetime' => ['nullable', 'string', 'max:20'],
            'pickup_location' => ['nullable', 'string', 'max:200'],
            'dropoff_location' => ['nullable', 'string', 'max:200'],
            'flight_number' => ['nullable', 'string', 'max:20'],
            'passenger_count' => ['nullable', 'integer', 'min:0'],
            'luggage_count' => ['nullable', 'integer', 'min:0'],
            'expected_revenue' => ['nullable', 'integer', 'min:0'],
            'reservation_company' => ['nullable', 'string', 'max:100'],
            'reservation_channel' => ['nullable', 'string', 'max:50'],
            'is_priority' => ['nullable', 'boolean'],
            'line_items' => ['array'],
            'line_items.*.scheduled_time' => ['nullable', 'string'],
            'line_items.*.service_type' => ['nullable', 'string'],
            'line_items.*.pickup_location' => ['nullable', 'string'],
            'line_items.*.dropoff_location' => ['nullable', 'string'],
            'line_items.*.flight_number' => ['nullable', 'string'],
            'line_items.*.passenger_count' => ['nullable', 'integer'],
            'line_items.*.luggage_count' => ['nullable', 'integer'],
        ]);
    }

    /**
     * 프론트 드롭다운용 옵션 모음.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'statusOptions' => Order::statusOptions(),
                'serviceOptions' => [
                    'pickup' => '픽업',
                    'sending' => '공항샌딩',
                    'landing' => '공항랜딩',
                ],
                'channelOptions' => Order::reservationChannelOptions(),
                'companyOptions' => Order::reservationCompanyOptions(),
            ],
        ]);
    }
}
