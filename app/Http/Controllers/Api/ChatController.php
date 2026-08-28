<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
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
     * 대화에 메시지를 보낸다 — 여러 장 이미지는 image_paths로 한 개 말풍선에 묶는다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function send(Request $request, Conversation $conversation, ChatService $chatService): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
            'images' => ['nullable', 'array', 'max:9'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image_paths' => ['nullable', 'array', 'max:9'],
            'image_paths.*' => ['string', 'max:255'],
        ]);

        $message = $chatService->send(
            $request->user(),
            $conversation,
            $data['body'] ?? null,
            $data['image'] ?? null,
            $data['image_path'] ?? null,
            $data['image_paths'] ?? [],
        );

        return response()->json([
            'data' => $chatService->serializeMessage($message),
        ], 201);
    }

    /**
     * 내가 채팅으로 보낸 이미지 보관함 — 사용자별로 각자 자신의 이미지만 조회한다.
     * 한 개 말풍선의 여러 장은 각각 하나의 항목으로 펼친 뒤 중복(지문)을 제거한다.
     * 이미지가 많을 때를 대비해 24장씩 페이지네이션한다.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function archive(Request $request): JsonResponse
    {
        $messages = Message::query()
            ->where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query->whereNotNull('image_path')
                    ->orWhereNotNull('image_paths');
            })
            ->latest()
            ->get();

        $items = [];
        $seen = [];

        foreach ($messages as $message) {
            $paths = $message->image_paths ?? ($message->image_path ? [$message->image_path] : []);

            foreach ($paths as $path) {
                if (isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                $items[] = [
                    'id' => $message->id,
                    'image_path' => $path,
                    // 상대 경로 — 접속 origin(터널/도메인/IP)에 맞게 브라우저가 절대화한다
                    'url' => '/api/chat/images/'.basename($path),
                    'created_at' => $message->created_at?->toDateTimeString(),
                ];
            }
        }

        $page = max(1, $request->integer('page', 1));
        $perPage = 24;
        $total = count($items);
        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => array_values($slice),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'total' => $total,
            ],
        ]);
    }

    /**
     * 채팅 첨부 이미지를 먼저 업로드한다 (전송 전 단계).
     * 내용 지문(해시)으로 중복 저장을 막고, 응답의 image_path를 묶음 전송에 재사용한다.
     *
     * @return JsonResponse{data: array<string, string>}
     */
    public function uploadImage(Request $request, ChatService $chatService): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ]);

        $imagePath = $chatService->storeImage($request->user(), $data['image']);

        return response()->json([
            'data' => [
                'image_path' => $imagePath,
                'url' => '/api/chat/images/'.basename($imagePath),
            ],
        ], 201);
    }

    /**
     * 보관함 이미지를 삭제한다 — 내가 업로드한 이미지만 삭제할 수 있고 파일도 함께 정리된다.
     *
     * @return JsonResponse{data: bool}
     */
    public function destroyArchiveImage(Request $request, Message $message, ChatService $chatService): JsonResponse
    {
        $chatService->deleteImage($request->user(), $message, $request->query('image_path'));

        return response()->json(['data' => true]);
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
