<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Services\Order\OrderOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 요금 제안(오퍼) API — 기사가 운임을 제안하고, 등록자가 수락/거절한다.
 * HTTP 요청/응답만 담당하고 비즈니스 규칙은 OrderOfferService에 위임한다.
 */
class OrderOfferController extends Controller
{
    /**
     * 운행의 제안 목록 (등록자는 전체, 기사는 본인 제안만).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request, Order $order, OrderOfferService $offerService): JsonResponse
    {
        return response()->json([
            'data' => $offerService->listFor($request->user(), $order),
        ]);
    }

    /**
     * 등록자의 제안 받은 편지함 — 대기 제안이 있는 내 공개 운행 목록 (비교·수락용).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function inbox(Request $request, OrderOfferService $offerService): JsonResponse
    {
        return response()->json([
            'data' => $offerService->inboxFor($request->user()),
        ]);
    }

    /**
     * 기사가 공개 운행에 운임을 제안한다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function store(Request $request, Order $order, OrderOfferService $offerService): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $offer = $offerService->propose(
            $request->user(),
            $order,
            (int) $data['amount'],
            isset($data['message']) ? trim($data['message']) ?: null : null,
        );

        return response()->json([
            'data' => [
                'id' => $offer->id,
                'order_id' => $offer->order_id,
                'amount' => $offer->amount,
                'status' => $offer->status,
            ],
        ], 201);
    }

    /**
     * 등록자가 제안을 수락한다 — 제안한 기사에게 운행이 넘어간다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function accept(Request $request, Order $order, OrderOffer $offer, OrderOfferService $offerService): JsonResponse
    {
        $offerService->accept($request->user(), $order, $offer);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'accepted_driver_id' => $offer->driver_id,
                'accepted_amount' => $offer->amount,
            ],
        ]);
    }

    /**
     * 등록자가 제안을 거절한다 — 운행은 마켓에 남는다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function reject(Request $request, Order $order, OrderOffer $offer, OrderOfferService $offerService): JsonResponse
    {
        $offerService->reject($request->user(), $order, $offer);

        return response()->json([
            'data' => ['ok' => true],
        ]);
    }

    /**
     * 제안한 기사가 본인 대기 제안을 철회한다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function destroy(Request $request, Order $order, OrderOffer $offer, OrderOfferService $offerService): JsonResponse
    {
        $offerService->withdraw($request->user(), $order, $offer);

        return response()->json([
            'data' => ['ok' => true],
        ]);
    }
}
