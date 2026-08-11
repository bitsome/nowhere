<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    /**
     * 내 대화 목록 (최신 메시지순).
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['users', 'order', 'messages' => fn ($q) => $q->orderByDesc('id')->limit(1)])
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        $unreadCounts = Message::query()
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->selectRaw('conversation_id, count(*) as total')
            ->groupBy('conversation_id')
            ->pluck('total', 'conversation_id');

        return response()->json([
            'data' => $conversations->map(function (Conversation $conversation) use ($user, $unreadCounts) {
                $lastMessage = $conversation->messages->first();

                return [
                    'id' => $conversation->id,
                    'order_id' => $conversation->order_id,
                    'counterpart' => $this->counterpart($conversation, $user),
                    'order' => $conversation->order ? [
                        'id' => $conversation->order->id,
                        'order_number' => $conversation->order->order_number,
                        'route' => trim(($conversation->order->pickup_location ?? '').' → '.($conversation->order->dropoff_location ?? '')),
                        'service_date' => $conversation->order->service_date,
                        'service_time' => $conversation->order->service_time,
                        'amount' => (int) ($conversation->order->expected_revenue ?? $conversation->order->amount_value ?? 0),
                        'status' => $conversation->order->status,
                        'statusLabel' => Order::statusOptions()[$conversation->order->status] ?? $conversation->order->status,
                    ] : null,
                    'last_message' => $lastMessage ? [
                        'body' => $lastMessage->body,
                        'image_url' => $lastMessage->image_path
                            ? url('/api/chat/images/'.basename($lastMessage->image_path))
                            : null,
                        'user_id' => $lastMessage->user_id,
                        'created_at' => $lastMessage->created_at?->diffForHumans(),
                    ] : null,
                    'unread_count' => (int) ($unreadCounts[$conversation->id] ?? 0),
                    'last_message_at' => $conversation->last_message_at?->diffForHumans(),
                ];
            }),
        ]);
    }

    /**
     * 새 대화를 시작한다.
     *
     * @return JsonResponse{data: array<string, int>}
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $me = $request->user();

        abort_if((int) $data['user_id'] === $me->id, 422);

        $conversation = DB::transaction(function () use ($data, $me): Conversation {
            $conversation = Conversation::create([
                'order_id' => $data['order_id'] ?? null,
            ]);

            $conversation->users()->attach([$me->id, (int) $data['user_id']]);

            return $conversation;
        });

        return response()->json([
            'data' => ['id' => $conversation->id],
        ], 201);
    }

    /**
     * 대화의 메시지 목록을 반환하고 상대 메시지를 읽음 처리한다.
     *
     * @return JsonResponse{data: array<int, array<string, mixed>>}
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 403);

        $messages = $conversation->messages()
            ->with('user:id,name')
            ->get()
            ->map(fn (Message $message) => $this->serializeMessage($message));

        // 대화를 열면 상대 메시지를 읽음 처리한다
        $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => $messages]);
    }

    /**
     * 대화에 메시지를 보낸다.
     *
     * @return JsonResponse{data: array<string, mixed>}
     */
    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $image = $data['image'] ?? null;

        $imagePath = $image instanceof UploadedFile
            ? $image->store('chat', 'public')
            : null;

        if (trim((string) ($data['body'] ?? '')) === '' && $imagePath === null) {
            throw ValidationException::withMessages([
                'body' => ['메시지 또는 이미지를 입력해주세요.'],
            ]);
        }

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $data['body'] ?? '',
            'image_path' => $imagePath,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return response()->json([
            'data' => $this->serializeMessage($message),
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

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'body' => $message->body,
            'image_url' => $message->image_path
                ? url('/api/chat/images/'.basename($message->image_path))
                : null,
            'created_at' => $message->created_at?->diffForHumans(),
            'read' => $message->read_at !== null,
        ];
    }

    /**
     * 상대방 사용자 요약 (대화 목록용).
     *
     * @return array<string, mixed>|null
     */
    private function counterpart(Conversation $conversation, User $me): ?array
    {
        $counterpart = $conversation->users
            ->first(fn (User $user) => $user->id !== $me->id);

        return $counterpart
            ? ['id' => $counterpart->id, 'name' => $counterpart->name]
            : null;
    }
}
