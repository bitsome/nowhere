<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\Support\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 고객지원(B-4) — 사용자 화면용 공지·FAQ 조회와 1:1 문의 작성·내 문의 조회.
 */
class SupportController extends Controller
{
    /**
     * 공지·FAQ 목록 — kind(notice|faq) 필터.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function posts(Request $request, SupportService $service): JsonResponse
    {
        $kind = $request->string('kind')->toString();

        return response()->json([
            'data' => $service->posts($kind !== '' ? $kind : null),
        ]);
    }

    /**
     * 1:1 문의 작성 — 관리자에게 접수 알림.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function storeTicket(Request $request, SupportService $service): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = $service->createTicket($request->user(), $data['title'], $data['body']);

        return response()->json([
            'data' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'status_label' => SupportTicket::statusOptions()[$ticket->status] ?? $ticket->status,
            ],
        ], 201);
    }

    /**
     * 내 문의 목록 — 본문·답변 포함 최신순.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function myTickets(Request $request, SupportService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->myTickets($request->user()),
        ]);
    }
}
