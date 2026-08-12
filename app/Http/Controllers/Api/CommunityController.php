<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityController extends Controller
{
    /**
     * 커뮤니티 피드 — 최신 글 목록 (좋아요/댓글 수, 내 좋아요 여부 포함).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $tab = $request->string('tab')->toString();
        $sort = $request->string('sort', 'latest')->toString();

        $query = CommunityPost::query()->feed($request->user()->id);

        if ($tab === 'my') {
            $query->where('user_id', $request->user()->id);
        }

        if ($sort === 'popular') {
            $query->reorder()->withCount('likes')->orderByDesc('likes_count');
        }

        $posts = $query->paginate($perPage);

        return response()->json([
            'data' => $posts->map(fn (CommunityPost $post) => $this->serialize($post)),
            'meta' => [
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                    'last_page' => $posts->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * 글 작성 — 내용 + 선택 사진 + 영상/숏츠 URL.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'video_url' => ['nullable', 'string', 'max:500'],
        ]);

        $imagePath = null;
        $image = $data['image'] ?? null;

        if ($image instanceof UploadedFile) {
            $imagePath = $image->store('community', 'public');
        }

        $post = CommunityPost::create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
            'image_path' => $imagePath,
            'video_url' => trim((string) ($data['video_url'] ?? '')),
        ]);

        // 레벨링: 커뮤니티 글 작성 +5 XP
        $request->user()->addXp(5, 'community_post', '커뮤니티 글 작성');

        return response()->json([
            'data' => $this->serialize($post
                ->load('user:id,name,email,profile_photo_path,is_vip,is_vehicle_verified,is_license_verified,xp')
                ->load('comments.user:id,name')
                ->loadCount('likes')
                ->loadCount('comments')),
        ], 201);
    }

    /**
     * 글 단건 — 모든 댓글 포함 (피드의 '댓글 모두 보기'에서 사용).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function show(Request $request, CommunityPost $post): JsonResponse
    {
        $post->load([
            'user:id,name,email,profile_photo_path,is_vip,is_vehicle_verified,is_license_verified,xp',
            'comments.user:id,name,email,profile_photo_path',
        ])
            ->loadCount('likes')
            ->loadCount('comments')
            ->loadExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $request->user()->id)]);

        return response()->json([
            'data' => $this->serialize($post),
        ]);
    }

    /**
     * 글 삭제 — 본인 글만.
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function destroy(Request $request, CommunityPost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        if ($post->image_path !== null) {
            Storage::disk('public')->delete($post->image_path);
        }

        $post->delete();

        return response()->json(['data' => ['id' => $post->id]]);
    }

    /**
     * 좋아요 토글.
     *
     * @return JsonResponse{data: array<string, bool|int>}
     */
    public function toggleLike(Request $request, CommunityPost $post): JsonResponse
    {
        $user = $request->user();

        if ($post->likes()->where('user_id', $user->id)->exists()) {
            $post->likes()->detach($user->id);
            $liked = false;
        } else {
            $post->likes()->attach($user->id);
            $liked = true;

            // 레벨링: 글 작성자가 좋아요를 받으면 +1 XP
            $author = $post->user()->first();

            if ($author !== null && $author->id !== $user->id) {
                $author->addXp(1, 'community_liked', '커뮤니티 글 좋아요 받기');
            }
        }

        return response()->json([
            'data' => [
                'id' => $post->id,
                'liked' => $liked,
                'likes_count' => $post->likes()->count(),
            ],
        ]);
    }

    /**
     * 댓글 작성.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function comment(Request $request, CommunityPost $post): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $comment = CommunityComment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        // 레벨링: 댓글 작성 +2 XP
        $request->user()->addXp(2, 'community_comment', '커뮤니티 댓글 작성');

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
                'user' => $request->user()->only(['id', 'name']),
                'comments_count' => $post->comments()->count(),
            ],
        ], 201);
    }

    /**
     * 유저 페이지 — 프로필(배지 포함), 올린 글, 등록한 운행.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function showUser(Request $request, User $user): JsonResponse
    {
        $posts = CommunityPost::query()
            ->feed($request->user()->id)
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

        return response()->json([
            'data' => [
                'user' => $this->serializeUser($user),
                'posts' => $posts->map(fn (CommunityPost $post) => $this->serialize($post)),
                'orders' => $orders->map(function (Order $order): array {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'status' => $order->status,
                        'statusLabel' => Order::statusOptions()[$order->status] ?? $order->status,
                        'route' => trim(($order->pickup_location ?? '').' → '.($order->dropoff_location ?? '')),
                        'service_date' => $order->service_date,
                        'service_time' => $order->service_time,
                        'amount' => (int) ($order->expected_revenue ?? $order->amount_value ?? 0),
                    ];
                }),
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
            ],
        ]);
    }

    /**
     * 업로드된 이미지 스트리밍 — 터널(SPA)에서도 보이도록 API 경로로 제공한다.
     */
    public function image(string $filename): BinaryFileResponse|StreamedResponse
    {
        $path = 'community/'.$filename;

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')
            ->response($path, null, [
                'Cache-Control' => 'public, max-age=86400, immutable',
                'Content-Type' => Storage::disk('public')->mimeType($path) ?? 'application/octet-stream',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CommunityPost $post): array
    {
        $post->loadMissing('user:id,name,email,profile_photo_path,is_vip,is_vehicle_verified,is_license_verified,xp');

        return [
            'id' => $post->id,
            'content' => $post->content,
            'image_url' => $post->image_path !== null
                ? '/api/community/images/'.basename($post->image_path)
                : null,
            'video_url' => $post->video_url,
            'created_at' => $post->created_at?->toISOString(),
            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'profile_photo_path' => $post->user->profile_photo_path,
                'is_vip' => (bool) $post->user->is_vip,
                'is_vehicle_verified' => (bool) $post->user->is_vehicle_verified,
                'is_license_verified' => (bool) $post->user->is_license_verified,
                'level' => $post->user->levelInfo(),
            ],
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'is_liked' => (bool) ($post->is_liked ?? false),
            'is_mine' => $post->user_id === auth()->id(),
            'comments' => $post->comments->map(fn (CommunityComment $comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at?->toISOString(),
                'user' => $comment->user?->only(['id', 'name']),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo_path' => $user->profile_photo_path,
            'role' => $user->role,
            'roleLabel' => [
                'Super Admin' => '최고 관리자',
                'Admin' => '관리자',
                'Operator' => '운영자',
                'Driver' => '드라이버',
            ][$user->role] ?? $user->role,
            'is_vehicle_verified' => (bool) $user->is_vehicle_verified,
            'is_license_verified' => (bool) $user->is_license_verified,
            'is_vip' => (bool) $user->is_vip,
            'vehicle_info' => $user->vehicle_info,
            'joined_at' => $user->created_at?->toDateString(),
            'posts_count' => $user->communityPosts()->count(),
            'level' => $user->levelInfo(),
            'xp' => (int) $user->xp,
        ];
    }
}
