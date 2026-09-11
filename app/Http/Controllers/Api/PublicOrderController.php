<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Orders\OrderListRowBuilder;
use Illuminate\Http\JsonResponse;

/**
 * 공유된 운행의 공개 조회 — 로그인 없이 접근한다.
 *
 * 등록자가 카카오 오픈채팅·카페 등 외부에 링크로 뿌린 운행을,
 * 아직 가입하지 않은 기사도 내용을 확인하고 가입까지 이어질 수 있게 한다.
 * 토큰을 아는 사람만 접근할 수 있고, 고객 실명·연락처 등 민감 정보는 내려주지 않는다.
 */
class PublicOrderController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $order = Order::query()
            ->where('share_token', $token)
            ->with(['user:id,name,company_name', 'claimant:id,name'])
            ->first();

        if ($order === null) {
            return response()->json([
                'data' => null,
                'message' => '공유된 운행을 찾을 수 없습니다. 링크가 만료되었거나 삭제된 운행입니다.',
            ], 404);
        }

        return response()->json([
            'data' => $this->payload($order),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Order $order): array
    {
        // 아직 기사가 가져갈 수 있는 상태인지 — 마감된 운행도 '왜 못 가져가는지'를 알려주기 위해 함께 내려준다
        $available = $order->status === Order::STATUS_PUBLISHED
            && ! $order->is_hidden
            && ! $order->admin_hold;

        $row = app(OrderListRowBuilder::class)->build($order);

        // 공개 화면에 노출하면 안 되는 값 제거 — 고객 실명·기사 정보·내부 식별자
        unset(
            $row['customerName'],
            $row['orderNumber'],
            $row['userId'],
            $row['claimantName'],
            $row['claimantUserId'],
        );

        return [
            'available' => $available,
            'status' => $order->status,
            // 등록자 신뢰 정보 — 기사가 '누구의 운행인지' 확인하고 판단하도록 업체명만 노출한다
            'registrant_company' => $order->user?->company_name ?: ($order->reservation_company ?: ''),
            'row' => $row,
            'distance_km' => $order->distance_km,
            'estimated_duration_minutes' => $order->estimated_duration_minutes,
            'request_label' => $order->request_label,
        ];
    }
}
