<?php

namespace App\Services\Community;

use App\Models\CommunityPost;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Support\Orders\ChineseTextNormalizer;

/**
 * 유저 페이지 — 프로필, 올린 글, 등록한 운행, 리뷰 통계, 수행 실적을 집계한다.
 */
class UserProfileService
{
    /**
     * @return array<string, mixed>
     */
    public function profile(User $viewer, User $user, CommunityPostService $posts): array
    {
        $viewedPosts = CommunityPost::query()
            ->feed($viewer->id)
            ->where('user_id', $user->id)
            ->limit(30)
            ->get();

        // 등록한 운행 (초안/취소 제외, 최근 10건)
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', [
                Order::STATUS_DRAFT,
                Order::STATUS_CANCELLED,
            ])
            ->latest('service_date')
            ->limit(10)
            ->get();

        // 받은 리뷰 + 평점 통계
        $reviews = Review::query()
            ->with('reviewer:id,name')
            ->where('reviewee_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        $allRatings = Review::query()
            ->where('reviewee_id', $user->id)
            ->pluck('rating');

        $avg = $allRatings->isEmpty() ? 0 : round($allRatings->avg(), 1);
        $breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($allRatings as $rating) {
            $breakdown[(int) $rating]++;
        }

        // 수행 실적: 완료/정산 운행 수와 총 매출
        $performed = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])
            ->get(['status', 'expected_revenue', 'amount_value']);

        return [
            'user' => $posts->serializeUser($user),
            'posts' => $viewedPosts->map(fn (CommunityPost $post) => $posts->serialize($post)),
            'orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'statusLabel' => Order::statusOptions()[$order->status] ?? $order->status,
                'route' => ChineseTextNormalizer::routeLabel($order->pickup_location ?? '', $order->dropoff_location ?? ''),
                'service_date' => $order->service_date,
                'service_time' => $order->service_time,
                'amount' => (int) ($order->expected_revenue ?? $order->amount_value ?? 0),
            ]),
            'reviewSummary' => [
                'avg' => $avg,
                'count' => $allRatings->count(),
                'breakdown' => $breakdown,
            ],
            'reviews' => $reviews->map(fn (Review $review) => [
                'id' => $review->id,
                'order_id' => $review->order_id,
                'rating' => $review->rating,
                'content' => $review->content,
                'created_at' => $review->created_at?->diffForHumans(),
                'reviewer' => [
                    'id' => $review->reviewer?->id,
                    'name' => $review->reviewer?->name,
                ],
            ]),
            'stats' => [
                'completed_orders' => $performed->count(),
                'total_revenue' => (int) $performed->sum(fn (Order $o) => $o->expected_revenue ?? $o->amount_value ?? 0),
            ],
        ];
    }
}
