<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 채팅 — 대화 목록/시작/메시지 조회(읽음 처리)/전송(이미지)과 직렬화를 담당한다.
 */
class ChatService
{
    /**
     * 내 대화 목록 (최신 메시지순).
     *
     * @return array<int, array<string, mixed>>
     */
    public function conversations(User $user): array
    {
        $conversations = $user->conversations()
            // reorder()로 관계 기본 정렬(created_at ASC)을 버리고 최신 메시지(id DESC) 1건을 가져온다
            // (기존: 관계 정렬 위에 orderByDesc가 쌓여 첫 메시지가 선택되던 버그)
            ->with(['users', 'order', 'messages' => fn ($q) => $q->reorder()->orderByDesc('id')->limit(1)])
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

        return $conversations->map(function (Conversation $conversation) use ($user, $unreadCounts): array {
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
                    'type' => $lastMessage->type ?? 'text',
                    'payload' => $lastMessage->payload ?: (object) [],
                    'image_url' => $lastMessage->image_path
                        ? url('/api/chat/images/'.basename($lastMessage->image_path))
                        : null,
                    'user_id' => $lastMessage->user_id,
                    'created_at' => $lastMessage->created_at?->diffForHumans(),
                ] : null,
                'unread_count' => (int) ($unreadCounts[$conversation->id] ?? 0),
                'last_message_at' => $conversation->last_message_at?->diffForHumans(),
            ];
        })->all();
    }

    /**
     * 새 대화를 시작한다.
     */
    public function start(User $me, int $userId, ?int $orderId = null): Conversation
    {
        abort_if($userId === $me->id, 422);

        return DB::transaction(function () use ($me, $userId, $orderId): Conversation {
            $conversation = Conversation::create([
                'order_id' => $orderId,
            ]);

            $conversation->users()->attach([$me->id, $userId]);

            return $conversation;
        });
    }

    /**
     * 대화의 메시지 목록을 반환하고 상대 메시지를 읽음 처리한다.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messages(User $user, Conversation $conversation): array
    {
        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 403);

        $messages = $conversation->messages()
            ->with('user:id,name')
            ->get()
            ->map(fn (Message $message) => $this->serializeMessage($message))
            ->values()
            ->all();

        // 대화를 열면 상대 메시지를 읽음 처리한다
        $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $messages;
    }

    /**
     * 대화에 메시지를 보낸다.
     */
    public function send(User $user, Conversation $conversation, ?string $body, ?UploadedFile $image): Message
    {
        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 403);

        $imagePath = $image instanceof UploadedFile
            ? $image->store('chat', 'public')
            : null;

        if (trim((string) ($body ?? '')) === '' && $imagePath === null) {
            throw ValidationException::withMessages([
                'body' => ['메시지 또는 이미지를 입력해주세요.'],
            ]);
        }

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $body ?? '',
            'image_path' => $imagePath,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    /**
     * 구조화된 운행 요청을 대화에 전송한다 (승인·시간·경로·요금·취소).
     * 요청 종류에 맞는 요약 문구를 body로 남겨 대화 목록 미리보기에도 보이게 한다.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendRequest(User $user, Conversation $conversation, string $type, array $payload): Message
    {
        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 403);

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'type' => $type,
            'payload' => $payload,
            'body' => $this->requestSummary($type, $payload),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    /**
     * 요청 종류별 채팅 카드 요약 문구.
     *
     * @param  array<string, mixed>  $payload
     */
    public function requestSummary(string $type, array $payload): string
    {
        return match ($type) {
            'approval' => '운행 시작 승인을 요청했습니다',
            'time_change' => '픽업 시간 변경 요청: '.($payload['to_time'] ?? ''),
            'route_change' => '경로 변경 요청 ('.($payload['target'] === 'dropoff' ? '목적지' : '픽업 위치').')',
            'payment_change' => '요금 협의 요청: '.number_format((int) ($payload['amount'] ?? 0)).'원',
            'cancel' => '운행 취소 요청',
            default => '요청',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'body' => $message->body,
            'type' => $message->type ?? 'text',
            'payload' => $message->payload ?: (object) [],
            'image_url' => $message->image_path
                ? url('/api/chat/images/'.basename($message->image_path))
                : null,
            'created_at' => $message->created_at?->diffForHumans(),
            'created_at_iso' => $message->created_at?->toISOString(),
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
