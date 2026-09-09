<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportPost;
use App\Models\SupportTicket;
use App\Services\Support\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 고객지원 관리(B-4) — 관리자 전용 공지·FAQ 작성/수정/삭제와 1:1 문의 답변.
 */
class AdminSupportController extends Controller
{
    use AuthorizesAdmin;

    /**
     * 공지·FAQ 목록 — kind 필터.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function posts(Request $request, SupportService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        return response()->json([
            'data' => $service->posts($request->string('kind')->toString() ?: null),
        ]);
    }

    /**
     * 공지/FAQ 작성.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function store(Request $request, SupportService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(SupportPost::kindOptions()))],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $post = $service->savePost($request->user(), $data['kind'], $data['title'], $data['body']);

        return response()->json([
            'data' => ['id' => $post->id, 'kind' => $post->kind, 'title' => $post->title],
        ], 201);
    }

    /**
     * 공지/FAQ 수정.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function update(Request $request, SupportPost $post, SupportService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        $data = $request->validate([
            'kind' => ['sometimes', Rule::in(array_keys(SupportPost::kindOptions()))],
            'title' => ['sometimes', 'string', 'max:200'],
            'body' => ['sometimes', 'string', 'max:10000'],
        ]);

        $post = $service->savePost(
            $request->user(),
            $data['kind'] ?? $post->kind,
            $data['title'] ?? $post->title,
            $data['body'] ?? $post->body,
            $post,
        );

        return response()->json([
            'data' => ['id' => $post->id, 'kind' => $post->kind, 'title' => $post->title],
        ]);
    }

    /**
     * 공지/FAQ 삭제.
     */
    public function destroy(Request $request, SupportPost $post, SupportService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        $service->deletePost($post);

        return response()->json(['data' => ['id' => $post->id]]);
    }

    /**
     * 1:1 문의 목록 — 답변 대기 우선, 상태 필터.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function tickets(Request $request, SupportService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        return response()->json([
            'data' => $service->adminTickets($request->string('status')->toString() ?: null),
        ]);
    }

    /**
     * 문의 답변 — 완료 처리하고 사용자에게 결과 알림.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function answer(Request $request, SupportTicket $ticket, SupportService $service): JsonResponse
    {
        $this->assertAdmin($request->user());

        $data = $request->validate([
            'answer' => ['required', 'string', 'max:10000'],
        ]);

        $ticket = $service->answerTicket($request->user(), $ticket, $data['answer']);

        return response()->json([
            'data' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'status_label' => SupportTicket::statusOptions()[$ticket->status] ?? $ticket->status,
            ],
        ]);
    }
}
