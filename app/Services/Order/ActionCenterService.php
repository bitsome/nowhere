<?php

namespace App\Services\Order;

use App\Models\Message;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;

/**
 * 처리할 일(액션 센터) — 운행 관련 대기 액션을 한곳에 모아 반환한다.
 *
 * 가져오기 승인 · 요금 제안 · 채팅 요청 카드. 흩어져 있던 액션을 한 페이지에서 처리하게 한다.
 */
class ActionCenterService
{
    public function __construct(
        private readonly OrderOfferService $offerService,
    ) {}

    /**
     * @return array{claims: array<int, array<string, mixed>>, offers: array<int, array<string, mixed>>, chat_requests: array<int, array<string, mixed>>}
     */
    public function summaryFor(User $user): array
    {
        return [
            'claims' => $this->claimsFor($user),
            'offers' => $this->offerService->inboxFor($user),
            'chat_requests' => $this->chatRequestsFor($user),
        ];
    }

    /**
     * 내가 등록한 운행의 가져오기 요청(승인 대기) 목록.
     *
     * @return array<int, array<string, mixed>>
     */
    private function claimsFor(User $user): array
    {
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_ACCEPTANCE_PENDING)
            ->with('claimant')
            ->orderByDesc('updated_at')
            ->get();

        return $orders
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number ?: '#'.$order->id,
                'route' => trim(($order->pickup_location ?: '').' → '.($order->dropoff_location ?: '')),
                'service_date' => $order->service_date,
                'service_time' => $order->service_time,
                'requested_at' => $order->claimed_at?->toIso8601String(),
                'claimant' => $this->userBrief($order->claimant, $order->claimant_user_id),
            ])
            ->values()
            ->all();
    }

    /**
     * 나에게 온 채팅 요청 카드 — 시간·경로·요금·취소 요청 (승인 카드는 가져오기 승인에서 처리).
     *
     * @return array<int, array<string, mixed>>
     */
    private function chatRequestsFor(User $user): array
    {
        $types = ['time_change', 'route_change', 'payment_change', 'cancel'];

        $messages = Message::query()
            ->whereIn('type', $types)
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('conversation', fn ($query) => $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id)))
            ->with(['conversation.order', 'user'])
            ->latest()
            ->get();

        $rows = [];
        $seen = [];

        foreach ($messages as $message) {
            // 대화+종류별 가장 최근 요청만 남긴다 (중복 방지)
            $key = $message->conversation_id.'-'.$message->type;

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $rows[] = $this->chatRequestRow($message);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function chatRequestRow(Message $message): array
    {
        $order = $message->conversation?->order;
        $payload = $message->payload ?: [];

        $titles = [
            'time_change' => '시간 변경 요청',
            'route_change' => '경로 변경 요청',
            'payment_change' => '요금 협의 요청',
            'cancel' => '운행 취소 요청',
        ];

        $lines = match ($message->type) {
            'time_change' => array_values(array_filter([
                isset($payload['to_time']) ? '변경 시간: '.$payload['to_time'] : null,
                isset($payload['note']) ? '사유: '.$payload['note'] : null,
            ])),
            'route_change' => array_values(array_filter([
                '변경 대상: '.($payload['target'] === 'dropoff' ? '목적지' : '픽업 위치'),
                isset($payload['from']) ? '현재: '.$payload['from'] : null,
                isset($payload['to']) ? '변경: '.$payload['to'] : null,
                isset($payload['note']) ? '사유: '.$payload['note'] : null,
            ])),
            'payment_change' => array_values(array_filter([
                isset($payload['amount']) ? '변경 금액: '.number_format((int) $payload['amount']).'원' : null,
                isset($payload['note']) ? '사유: '.$payload['note'] : null,
            ])),
            'cancel' => array_values(array_filter([
                isset($payload['reason']) ? '사유: '.$payload['reason'] : null,
            ])),
            default => [],
        };

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'type' => $message->type,
            'title' => $titles[$message->type] ?? '요청',
            'body' => $message->body,
            'lines' => $lines,
            'sender_name' => $message->user?->name ?? '',
            'order_id' => $order?->id,
            'order_route' => $order !== null
                ? trim(($order->pickup_location ?: '').' → '.($order->dropoff_location ?: ''))
                : '',
            'created_at_iso' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userBrief(?User $user, ?int $userId): array
    {
        $rating = Review::query()
            ->where('reviewee_id', $userId)
            ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg')
            ->groupBy('reviewee_id')
            ->first();

        return [
            'id' => $userId,
            'name' => $user?->name ?? '',
            'rating' => $rating ? round((float) $rating->avg, 1) : 0,
            'review_count' => (int) ($rating->cnt ?? 0),
        ];
    }
}
