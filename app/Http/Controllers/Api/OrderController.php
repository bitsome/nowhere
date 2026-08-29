<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Services\Order\OrderClaimService;
use App\Services\Order\OrderCreator;
use App\Services\Order\OrderListService;
use App\Services\Order\OrderTransitionService;
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
        $order->load(['user', 'claimant.vehicles', 'lineItems', 'group.orders.lineItems', 'group.orders.user']);

        // 내가 이 운행에 남긴 리뷰 — 프론트에서 작성 여부를 알기 위해 함께 내려준다
        $myReview = Review::query()
            ->where('order_id', $order->id)
            ->where('reviewer_id', $request->user()?->id)
            ->first();

        return response()->json([
            'data' => [
                'order' => $order->toArray(),
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
     * 가져오기 요청을 등록자가 승인한다 — 요청한 드라이버에게 운행이 넘어간다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function approveClaim(Request $request, Order $order, OrderClaimService $claimService): JsonResponse
    {
        $claimService->approve($request->user(), $order);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * 가져오기 요청을 등록자가 거절한다 — 운행이 마켓으로 돌아간다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function rejectClaim(Request $request, Order $order, OrderClaimService $claimService): JsonResponse
    {
        $claimService->reject($request->user(), $order);

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
            'orders.*.reservation_company' => ['nullable', 'string', 'max:100'],
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
}
