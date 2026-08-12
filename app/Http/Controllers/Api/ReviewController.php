<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * 사용자 리뷰 목록 (reviewee 기준) + 평점 요약.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, summary: array<string, int|float>|null}
     */
    public function index(Request $request): JsonResponse
    {
        $userId = max(0, (int) $request->integer('user_id'));

        $reviews = Review::query()
            ->with(['reviewer:id,name', 'order:id,order_number'])
            ->when($userId > 0, fn ($q) => $q->where('reviewee_id', $userId))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $summary = null;

        if ($userId > 0) {
            $ratings = Review::query()->where('reviewee_id', $userId);

            $summary = [
                'rating' => round((float) ($ratings->avg('rating') ?? 0), 1),
                'count' => $ratings->count(),
                'distribution' => collect(range(5, 1))
                    ->map(fn (int $star) => [
                        'star' => $star,
                        'count' => Review::query()->where('reviewee_id', $userId)->where('rating', $star)->count(),
                    ])
                    ->all(),
            ];
        }

        return response()->json([
            'data' => $reviews->map(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'content' => $review->content,
                'created_at' => $review->created_at?->diffForHumans(),
                'reviewer' => [
                    'id' => $review->reviewer?->id,
                    'name' => $review->reviewer?->name,
                ],
                'order' => [
                    'id' => $review->order?->id,
                    'order_number' => $review->order?->order_number,
                ],
            ]),
            'summary' => $summary,
        ]);
    }

    /**
     * 완료/정산된 운행에서 상대방(등록자↔수행자)에게 리뷰를 남긴다.
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'max:500'],
        ]);

        $actor = $request->user();

        // 운행 완료 후에만 리뷰 가능
        if (! in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_SETTLED], true)) {
            throw ValidationException::withMessages([
                'content' => ['운행이 완료된 운행에서만 리뷰할 수 있습니다.'],
            ]);
        }

        // 운행 당사자(등록자 또는 수행자)가 상대방에게만 리뷰 가능
        $driverId = $order->user_id;
        $registrarId = $order->original_owner_id;

        $revieweeId = null;
        if ($registrarId === $actor->id && $driverId !== null && $driverId !== $actor->id) {
            $revieweeId = $driverId;
        } elseif ($driverId === $actor->id && $registrarId !== null && $registrarId !== $actor->id) {
            $revieweeId = $registrarId;
        }

        if ($revieweeId === null) {
            throw ValidationException::withMessages([
                'content' => ['리뷰할 수 없는 운행입니다.'],
            ]);
        }

        // 운행당 1회만 리뷰 가능
        if (Review::query()->where('order_id', $order->id)->where('reviewer_id', $actor->id)->exists()) {
            throw ValidationException::withMessages([
                'content' => ['이미 리뷰를 작성했습니다.'],
            ]);
        }

        $review = Review::create([
            'order_id' => $order->id,
            'reviewer_id' => $actor->id,
            'reviewee_id' => $revieweeId,
            'rating' => $data['rating'],
            'content' => $data['content'],
        ]);

        // 리뷰를 받은 사용자에게 알림
        $reviewee = User::query()->find($revieweeId);

        if ($reviewee !== null && $reviewee->id !== $actor->id) {
            $reviewee->notify(new OrderNotification(
                '새 리뷰 도착',
                "{$actor->name}님이 운행({$order->order_number})에 리뷰(★{$data['rating']})를 남겼습니다.",
                $order->id,
            ));
        }

        return response()->json([
            'data' => $this->serialize($review),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Review $review): array
    {
        return [
            'id' => $review->id,
            'order_id' => $review->order_id,
            'rating' => $review->rating,
            'content' => $review->content,
            'created_at' => $review->created_at?->diffForHumans(),
            'reviewer' => [
                'id' => $review->reviewer?->id,
                'name' => $review->reviewer?->name,
            ],
        ];
    }
}
