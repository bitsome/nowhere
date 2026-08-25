<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 채팅 API — HTTP 요청/응답만 담당하고 비즈니스 로직은 Chat 서비스에 위임한다.
 */
class ChatController extends Controller
{
    /**
     * 내 대화 목록 (최신 메시지순).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request, ChatService $chatService): JsonResponse
    {
        return response()->json([
            'data' => $chatService->conversations($request->user()),
        ]);
    }

    /**
     * 새 대화를 시작한다.
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function store(Request $request, ChatService $chatService): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $conversation = $chatService->start($request->user(), (int) $data['user_id'], $data['order_id'] ?? null);

        return response()->json([
            'data' => ['id' => $conversation->id],
        ], 201);
    }

    /**
     * 대화의 메시지 목록을 반환하고 상대 메시지를 읽음 처리한다.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function show(Request $request, Conversation $conversation, ChatService $chatService): JsonResponse
    {
        return response()->json([
            'data' => $chatService->messages($request->user(), $conversation),
        ]);
    }

    /**
     * 대화에 메시지를 보낸다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function send(Request $request, Conversation $conversation, ChatService $chatService): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $message = $chatService->send(
            $request->user(),
            $conversation,
            $data['body'] ?? null,
            $data['image'] ?? null,
        );

        return response()->json([
            'data' => $chatService->serializeMessage($message),
        ], 201);
    }

    /**
     * 구조화된 운행 요청을 대화에 보낸다 (승인·시간 변경·경로 변경·요금 협의·취소).
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function request(Request $request, Conversation $conversation, ChatService $chatService): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['approval', 'time_change', 'route_change', 'payment_change', 'cancel'])],
            'payload' => ['required', 'array'],
        ]);

        $message = $chatService->sendRequest($request->user(), $conversation, $data['type'], $data['payload']);

        return response()->json([
            'data' => $chatService->serializeMessage($message),
        ], 201);
    }

    /**
     * 채팅 이미지를 공개 서빙한다 (<img> 태그는 Authorization 헤더를 못 보내므로 인증 밖).
     */
    public function image(string $filename): BinaryFileResponse|StreamedResponse
    {
        $path = 'chat/'.$filename;

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')
            ->response($path, null, [
                'Cache-Control' => 'public, max-age=86400, immutable',
                'Content-Type' => Storage::disk('public')->mimeType($path) ?? 'application/octet-stream',
            ]);
    }
}
