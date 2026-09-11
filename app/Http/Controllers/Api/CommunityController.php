<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Community\CommunityPostService;
use App\Services\Community\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 커뮤니티 API — HTTP 요청/응답만 담당하고 비즈니스 로직은 Community 서비스에 위임한다.
 */
class CommunityController extends Controller
{
    /**
     * 커뮤니티 피드 — 최신 글 목록 (좋아요/댓글 수, 내 좋아요 여부 포함).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function index(Request $request, CommunityPostService $posts): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $tab = $request->string('tab')->toString();
        $sort = $request->string('sort', 'latest')->toString();
        $category = $request->string('category')->toString();
        $search = $request->string('search')->trim()->toString();

        $query = CommunityPost::query()->feed($request->user()->id);

        if ($tab === 'my') {
            $query->where('user_id', $request->user()->id);
        }

        if ($search !== '') {
            $query->where('content', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%');
        }

        $author = $request->string('author')->trim()->toString();

        if ($author !== '') {
            $keyword = str_replace(['%', '_'], ['\%', '\_'], $author);

            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%"));
        }

        $period = $request->string('period')->toString();

        if (in_array($period, ['today', 'week', 'month'], true)) {
            $query->where('created_at', '>=', match ($period) {
                'today' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
            });
        }

        // 카테고리 칩 건수 — 선택한 카테고리와 무관하게 현재 범위(검색/기간/내 글)의 카테고리별 글 수
        // select() 로 컬럼을 먼저 비워야 한다. feed() 가 withCount/withExists 로 community_posts.* 와
        // 집계 서브쿼리를 이미 채워두는데, 그대로 두면 count(*) 와 함께 나가 MySQL ONLY_FULL_GROUP_BY 에서
        // 1055 오류가 난다 (SQLite 로컬 테스트로는 잡히지 않는다).
        $categoryCounts = (clone $query)->reorder()
            ->select('category')
            ->selectRaw('count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($total) => (int) $total);

        if ($category !== '' && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($sort === 'popular') {
            $query->reorder()->withCount('likes')->orderByDesc('likes_count');
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->map(fn (CommunityPost $post) => $posts->serialize($post)),
            'meta' => [
                'category_counts' => $categoryCounts,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * 글 작성 — 내용 + 선택 사진 + 영상/숏츠 URL.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function store(Request $request, CommunityPostService $posts): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:20'],
            'image' => ['nullable', 'image', 'max:5120'],
            'video_url' => ['nullable', 'string', 'max:500'],
        ]);

        $post = $posts->create(
            $request->user(),
            $data['content'],
            $data['image'] ?? null,
            (string) ($data['video_url'] ?? ''),
            (string) ($data['category'] ?? 'free'),
        );

        return response()->json([
            'data' => $posts->serialize($post
                ->load('user:id,name,email,profile_photo_path,is_vip,is_vehicle_verified,is_license_verified,xp')
                ->load('comments.user:id,name')
                ->loadCount('likes')
                ->loadCount('comments')),
        ], 201);
    }

    /**
     * 글 수정 — 본인 글만 (내용/카테고리/영상, 선택적으로 사진 교체).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function update(Request $request, CommunityPost $post, CommunityPostService $posts): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:20'],
            'image' => ['nullable', 'image', 'max:5120'],
            'video_url' => ['nullable', 'string', 'max:500'],
        ]);

        $post = $posts->update(
            $request->user(),
            $post,
            $data['content'],
            $data['image'] ?? null,
            (string) ($data['video_url'] ?? ''),
            (string) ($data['category'] ?? 'free'),
        );

        return response()->json([
            'data' => $posts->serialize($post
                ->load('user:id,name,email,profile_photo_path,is_vip,is_vehicle_verified,is_license_verified,xp')
                ->load('comments.user:id,name')
                ->loadCount('likes')
                ->loadCount('comments')),
        ]);
    }

    /**
     * 글 단건 — 모든 댓글 포함 (피드의 '댓글 모두 보기'에서 사용).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function show(Request $request, CommunityPost $post, CommunityPostService $posts): JsonResponse
    {
        $post->load([
            'user:id,name,email,profile_photo_path,is_vip,is_vehicle_verified,is_license_verified,xp',
            'comments.user:id,name,email,profile_photo_path',
        ])
            ->loadCount('likes')
            ->loadCount('comments')
            ->loadExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $request->user()->id)]);

        return response()->json([
            'data' => $posts->serialize($post),
        ]);
    }

    /**
     * 글 삭제 — 본인 글만.
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function destroy(Request $request, CommunityPost $post, CommunityPostService $posts): JsonResponse
    {
        $posts->delete($request->user(), $post);

        return response()->json(['data' => ['id' => $post->id]]);
    }

    /**
     * 좋아요 토글.
     *
     * @return JsonResponse{data: array<string, bool|int>}
     */
    public function toggleLike(Request $request, CommunityPost $post, CommunityPostService $posts): JsonResponse
    {
        $result = $posts->toggleLike($request->user(), $post);

        return response()->json([
            'data' => [
                'id' => $post->id,
                ...$result,
            ],
        ]);
    }

    /**
     * 댓글 작성.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function comment(Request $request, CommunityPost $post, CommunityPostService $posts): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $comment = $posts->addComment($request->user(), $post, $data['content']);

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
                'user' => $request->user()->only(['id', 'name']),
                'is_mine' => true,
                'comments_count' => $post->comments()->count(),
            ],
        ], 201);
    }

    /**
     * 댓글 삭제 — 본인 댓글만.
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function destroyComment(
        Request $request,
        CommunityPost $post,
        CommunityComment $comment,
        CommunityPostService $posts,
    ): JsonResponse {
        abort_unless($comment->post_id === $post->id, 404);

        $posts->deleteComment($request->user(), $comment);

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'comments_count' => $post->comments()->count(),
            ],
        ]);
    }

    /**
     * 유저 페이지 — 프로필(배지 포함), 올린 글, 등록한 운행.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function showUser(
        Request $request,
        User $user,
        UserProfileService $profileService,
        CommunityPostService $posts,
    ): JsonResponse {
        return response()->json([
            'data' => $profileService->profile($request->user(), $user, $posts),
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
}
