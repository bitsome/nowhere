<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\OrderEvent;
use App\Models\Review;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\OrderNotification;
use App\Services\Order\OrderClaimService;
use App\Services\Order\OrderCreator;
use App\Services\Order\OrderListService;
use App\Services\Order\OrderTransitionService;
use App\Services\OrderFavoriteService;
use App\Services\OrderSummaryAiStructurer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * 운행 API — HTTP 요청/응답만 담당하고 비즈니스 로직은 Order 서비스에 위임한다.
 */
class OrderController extends Controller
{
    /**
     * 마켓(가져올 수 있는 운행) 또는 내가 받은 운행 목록.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function index(Request $request, OrderListService $listService): JsonResponse
    {
        $result = $listService->index($request);

        return response()->json([
            'data' => $result['rows'],
            'meta' => [
                'pagination' => $result['pagination'],
            ],
        ]);
    }

    /**
     * 찜한 운행 목록 — 마켓에서 아직 가져올 수 있는 운행만 (마켓 공용 파이프라인 재사용).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function favorites(Request $request, OrderListService $listService): JsonResponse
    {
        // '찜' 퀵 필터가 정리(마켓에서 빠진 찜 제거)·스코프(마켓)를 모두 담당한다
        $request->merge(['scope' => 'market', 'quick' => 'favorites']);

        return $this->index($request, $listService);
    }

    /**
     * 찜(즐겨찾기) 상태를 뒤집는다 — 이미 찜했으면 해제, 아니면 추가.
     *
     * @return JsonResponse{data: array{favorited: bool}}
     */
    public function favorite(Request $request, Order $order, OrderFavoriteService $favoriteService): JsonResponse
    {
        return response()->json([
            'data' => $favoriteService->toggle($request->user(), $order),
        ]);
    }

    /**
     * 왕복 노선 추천 — 내가 맡은 운행의 하차지 근처에서 시작하는 마켓 운행.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function returnRoutes(Request $request, OrderListService $listService): JsonResponse
    {
        return response()->json([
            'data' => $listService->returnRoutes($request),
        ]);
    }

    /**
     * 홈 '추천일정' — 일정이 없어도 매칭 설정·운행 이력 기준으로 마켓 운행을 추천한다.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function recommendations(Request $request, OrderListService $listService): JsonResponse
    {
        return response()->json([
            'data' => $listService->recommendations($request),
        ]);
    }

    /**
     * 운행 상세 — 라인아이템, 셋트면 그룹 전체 일정 포함.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load(['user', 'claimant.vehicles', 'originalOwner', 'lineItems', 'group.orders.lineItems', 'group.orders.user']);

        // 내가 이 운행에 남긴 리뷰 — 프론트에서 작성 여부를 알기 위해 함께 내려준다
        $myReview = Review::query()
            ->where('order_id', $order->id)
            ->where('reviewer_id', $request->user()?->id)
            ->first();

        return response()->json([
            'data' => [
                'order' => $order->toArray(),
                // 내가 찜한 운행인지 — 상세 화면 하트 초기 상태
                'favorited' => $request->user() !== null
                    ? app(OrderFavoriteService::class)->isFavorited($request->user(), $order)
                    : false,
                // 운행 타임라인 — 상태·단계 변경 이력을 시간순(최신이 위)으로 내려준다
                'timeline' => $order->orderEvents()
                    ->with('user:id,name')
                    ->get()
                    ->map(fn (OrderEvent $event) => [
                        'id' => $event->id,
                        'event' => $event->event,
                        'from_status' => $event->from_status,
                        'to_status' => $event->to_status,
                        'note' => $event->note,
                        'user_name' => $event->user?->name ?? '',
                        'created_at_iso' => $event->created_at?->toISOString(),
                    ]),
                'claims' => $order->pendingClaims()
                    ->with('driver')
                    ->get()
                    ->map(fn (OrderClaim $claim) => [
                        'claim_id' => $claim->id,
                        'driver_id' => $claim->driver_id,
                        'driver_name' => $claim->driver?->name ?? '',
                        // 기사 평점·리뷰 수 — 신청 카드에서 한눈에 확인하도록 함께 내려준다
                        'rating' => $this->driverRating($claim->driver_id),
                        'review_count' => (int) Review::query()
                            ->where('reviewee_id', $claim->driver_id)
                            ->count(),
                        'vehicle' => Vehicle::brief(Vehicle::activeVehicleFor($claim->driver_id)),
                        'requested_at' => $claim->created_at?->toIso8601String(),
                    ]),
                'my_review' => $myReview === null ? null : [
                    'id' => $myReview->id,
                    'rating' => $myReview->rating,
                ],
                'group' => $order->group?->toArray(),
                'statusOptions' => Order::statusOptions(),
                'nextTransitions' => array_values(Order::STATUS_FLOW[$order->status] ?? []),
            ],
        ]);
    }

    /**
     * 마켓의 공개 운행을 가져오기 요청한다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function claim(Request $request, Order $order, OrderClaimService $claimService): JsonResponse
    {
        $claimService->claim($request->user(), $order);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * 보낸 가져오기 요청들의 현재 상태 요약 — 홈 일괄요청중 카드에서
     * 남은 시간·승인/거절 개수를 세는 데 사용한다.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function claimSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'max:20'],
            'order_ids.*' => ['integer'],
        ]);

        $rows = Order::query()
            ->whereIn('id', array_unique(array_filter($data['order_ids'])))
            ->get(['id', 'status', 'claimed_at', 'claim_batch_id'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'status' => $order->status,
                'claimedAt' => $order->claimed_at?->toISOString(),
                'claimBatchId' => $order->claim_batch_id,
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    /**
     * 왕복 체인의 여러 운행을 등록자들에게 일괄로 가져오기 요청한다.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function batchClaim(Request $request, OrderClaimService $claimService): JsonResponse
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array', 'max:10'],
            'order_ids.*' => ['integer'],
        ]);

        // 일괄 요청 전체가 실패 목록으로 흘러가지 않도록 기사 여부는 요청 시점에 가른다
        abort_unless($request->user()->role === User::ROLE_DRIVER, 403, '기사만 운행을 가져올 수 있습니다.');

        $results = $claimService->claimBatch($request->user(), $data['order_ids']);

        $succeeded = count(array_filter($results, fn (array $result) => $result['ok']));

        return response()->json([
            'data' => $results,
            'summary' => [
                'requested' => count($results),
                'succeeded' => $succeeded,
                'failed' => count($results) - $succeeded,
            ],
        ]);
    }

    /**
     * 가져오기 요청을 요청자(드라이버)가 철회한다 — 남은 대기 신청이 없으면 운행이 마켓으로 돌아간다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function withdrawClaim(Request $request, Order $order, OrderClaimService $claimService): JsonResponse
    {
        abort_unless($order->hasPendingClaimBy($request->user()->id), 403, '요청한 드라이버만 철회할 수 있습니다.');

        $claimService->withdraw($order, $request->user());

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * 만료된 가져오기 요청을 자동 철회한다 — 홈 일괄요청중 카드가
     * 30분이 지나면 호출해 운행을 마켓으로 돌려 다시 요청할 수 있게 한다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function withdrawExpiredClaim(Request $request, Order $order, OrderClaimService $claimService): JsonResponse
    {
        abort_unless($order->hasPendingClaimBy($request->user()->id), 403, '요청한 드라이버만 철회할 수 있습니다.');

        $claimService->withdrawExpired($order, $request->user());

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * 가져오기 요청을 등록자가 승인한다 — 해당 신청자의 드라이버에게 운행이 넘어간다.
     * 같은 운행의 다른 신청자들은 자동 거절된다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function approveClaim(Request $request, Order $order, OrderClaim $claim, OrderClaimService $claimService): JsonResponse
    {
        $claimService->approve($request->user(), $order, $claim);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * 가져오기 요청을 등록자가 거절한다 — 남은 대기 신청이 없으면 운행이 마켓으로 돌아간다.
     * 거절 사유(선택)는 기사에게 전달된다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function rejectClaim(Request $request, Order $order, OrderClaim $claim, OrderClaimService $claimService): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $claimService->reject(
            $request->user(),
            $order,
            $claim,
            isset($data['reason']) ? trim($data['reason']) ?: null : null,
        );

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * 운행 정보가 부족할 때 등록자에게 더 자세한 입력을 요청한다.
     * 요청 사유(선택)·메모(선택)를 함께 받아 등록자에게 DB/웹 푸시 알림으로 전달된다.
     *
     * @return JsonResponse{data: array<string, bool>}
     */
    public function requestDetails(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $registrantId = $order->original_owner_id ?? $order->user_id;

        abort_unless($registrantId !== null, 404, '등록자를 찾을 수 없습니다.');

        // 등록자 본인이 스스로에게 요청하는 것은 막는다
        abort_if($registrantId === $request->user()->id, 422, '본인 운행에는 상세 정보를 요청할 수 없습니다.');

        $registrant = User::query()->find($registrantId);

        if ($registrant !== null) {
            $requestText = implode(' / ', array_filter([
                trim((string) ($data['reason'] ?? '')),
                trim((string) ($data['message'] ?? '')),
            ]));

            $message = "{$order->rideSummary()} 운행의 정보가 부족합니다. 더 자세한 내용을 입력해 주세요.";

            if ($requestText !== '') {
                $message .= " 요청 내용: {$requestText}";
            }

            $registrant->notify(new OrderNotification(
                '상세 정보 요청',
                $message,
                $order->id,
            ));
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * 운행중 세부 단계를 다음 단계로 진행한다 — 카드 단계 스테퍼(운행시작→픽업 도착→승객 도착→출발→이동중→도착지 도착)용.
     * 마지막 '도착지 도착'을 기록하면 운행이 자동 완료 처리된다 (실제 수익은 선택 입력).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function advanceRideStep(Request $request, Order $order, OrderTransitionService $transitionService): JsonResponse
    {
        $data = $request->validate([
            'actual_revenue' => ['nullable', 'integer', 'min:0'],
        ]);

        $rideStep = $transitionService->advanceRideStep($request->user(), $order, $data['actual_revenue'] ?? null);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'ride_step' => $rideStep,
                'ride_step_times' => $order->ride_step_times ?? [],
            ],
        ]);
    }

    /**
     * 라이프사이클 규칙에 따라 운행 상태를 전환한다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function transition(Request $request, Order $order, OrderTransitionService $transitionService): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'cancel_reason' => ['nullable', 'string', 'max:500'],
            'actual_revenue' => ['nullable', 'integer', 'min:0'],
        ]);

        $transitionService->transition(
            $request->user(),
            $order,
            $data['status'],
            $data['cancel_reason'] ?? null,
            $data['actual_revenue'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'ride_step' => $order->ride_step,
                'ride_step_times' => $order->ride_step_times ?? [],
            ],
        ]);
    }

    /**
     * 완료된 운행을 선택해 일괄 정산 처리한다.
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function batchSettle(Request $request, OrderTransitionService $transitionService): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $settled = $transitionService->batchSettle($request->user(), $data['ids']);

        return response()->json([
            'data' => ['settled' => $settled],
        ]);
    }

    /**
     * 운행을 등록한다 (단일 운행).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function store(Request $request, OrderCreator $creator): JsonResponse
    {
        $data = $this->validateOrderPayload($request);

        $order = $creator->create($data, $request->user()->id);

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
    public function duplicate(Request $request, Order $order, OrderCreator $creator): JsonResponse
    {
        $copy = $creator->duplicate($order, $request->user()->id);

        return response()->json([
            'data' => ['id' => $copy->id],
        ], 201);
    }

    /**
     * 셋트 운행 등록 — 한 번에 여러 운행을 등록하고 하나의 그룹으로 묶는다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function batchStore(Request $request, OrderCreator $creator): JsonResponse
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
            'orders.*.customer_phone' => ['nullable', 'string', 'max:40'],
            'orders.*.reservation_company' => ['nullable', 'string', 'max:100'],
            'orders.*.tags' => ['nullable', 'array', 'max:20'],
            'orders.*.tags.*' => ['string', 'max:30'],
            'orders.*.line_items' => ['array'],
        ]);

        $group = $creator->createBatch($data, $request->user()->id);

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
    public function update(Request $request, Order $order, OrderCreator $creator): JsonResponse
    {
        $data = $this->validateOrderPayload($request);

        $creator->update($order, $data);

        // 찜한 운행의 조건(일정·노선·차량·금액 등)이 실제로 바뀌었으면 찜한 기사에게 알린다.
        // 마켓에 없는 운행(진행중·완료 등)의 세부 수정은 알림 대상이 아니다.
        $relevantFields = [
            'service_date', 'service_time', 'service_datetime',
            'pickup_location', 'dropoff_location', 'flight_number',
            'vehicle_type', 'service_type',
            'passenger_count', 'luggage_count',
            'expected_revenue', 'amount_value', 'tags', 'is_priority',
        ];

        if (array_intersect($relevantFields, array_keys($order->getChanges() ?: [])) !== []
            && app(OrderFavoriteService::class)->isMarketable($order)) {
            app(OrderFavoriteService::class)->notifyChanged($order);
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
    public function detachFromGroup(Order $order): JsonResponse
    {
        if ($order->group_id !== null) {
            $order->update([
                'group_id' => null,
                'group_type' => '단일',
            ]);
        }

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

    /**
     * 기사 평점 — 리뷰 평균을 소수 첫째 자리로 반올림해 반환한다 (리뷰 없으면 0).
     */
    private function driverRating(int $userId): float
    {
        $rating = Review::query()
            ->where('reviewee_id', $userId)
            ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg')
            ->groupBy('reviewee_id')
            ->first();

        return $rating ? round((float) $rating->avg, 1) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOrderPayload(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
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
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:30'],
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
}
