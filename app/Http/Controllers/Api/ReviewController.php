<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * 완료/정산된 오더에서 상대방(등록자↔수행자)에게 리뷰를 남긴다.
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
                'content' => ['운행이 완료된 오더에서만 리뷰할 수 있습니다.'],
            ]);
        }

        // 오더 당사자(등록자 또는 수행자)가 상대방에게만 리뷰 가능
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
                'content' => ['리뷰할 수 없는 오더입니다.'],
            ]);
        }

        // 오더당 1회만 리뷰 가능
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
