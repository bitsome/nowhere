<?php

namespace App\Services\Chat;

use App\Models\ChatImageUpload;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
     * 대화에 메시지를 보낸다. 여러 장 이미지를 하나의 말풍선(image_paths)으로 묶어 전송한다.
     * - image(새 업로드): 내용 지문(해시)으로 중복 저장 없이 보관한다.
     * - image_path / image_paths(보관함 재사용): 내가 이전에 보낸 이미지만 허용하고 그대로 재사용한다.
     *
     * @param  array<int, string>  $imagePaths
     */
    public function send(User $user, Conversation $conversation, ?string $body, ?UploadedFile $image = null, ?string $imagePath = null, array $imagePaths = []): Message
    {
        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 403);

        $paths = [];

        if ($image instanceof UploadedFile) {
            $paths[] = $this->storeImage($user, $image);
        }

        if ($imagePath !== null && trim($imagePath) !== '') {
            $this->assertOwnsImage($user, $imagePath);
            $paths[] = $imagePath;
        }

        foreach ($imagePaths as $candidate) {
            $this->assertOwnsImage($user, $candidate);
            $paths[] = $candidate;
        }

        $paths = array_values(array_unique($paths));

        if (trim((string) ($body ?? '')) === '' && count($paths) === 0) {
            throw ValidationException::withMessages([
                'body' => ['메시지 또는 이미지를 입력해주세요.'],
            ]);
        }

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $body ?? '',
            'image_paths' => count($paths) ? $paths : null,
            'image_path' => $paths[0] ?? null,
        ]);

        // 전송이 끝난 지문은 메시지가 참조하므로 업로드 소유권 기록은 정리한다 (중복 누적 방지)
        if (count($paths)) {
            ChatImageUpload::where('user_id', $user->id)
                ->whereIn('image_path', $paths)
                ->delete();
        }

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    /**
     * 첨부 이미지를 저장하고 저장 경로를 반환한다.
     * 파일 내용 해시(지문)를 파일명으로 사용해 같은 이미지를 다시 올려도 중복 저장하지 않는다.
     * 아직 메시지로 저장되지 않은 상태이므로, 곧바로 묶음 전송할 수 있도록 소유권을 기록한다.
     */
    public function storeImage(User $user, UploadedFile $image): string
    {
        $hash = hash_file('sha256', (string) $image->getRealPath());
        $extension = $image->extension() ?: 'jpg';
        $path = 'chat/'.$hash.'.'.$extension;

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->putFileAs('chat', $image, $hash.'.'.$extension);
        }

        ChatImageUpload::firstOrCreate([
            'user_id' => $user->id,
            'image_path' => $path,
        ]);

        return $path;
    }

    /**
     * 보관함 재사용 경로가 내가 이전에 보낸 이미지인지 검증한다 (단일·다중 참조 모두 허용).
     * 전송 전에 업로드한 새 사진(chat_image_uploads 소유권)도 허용한다.
     */
    private function assertOwnsImage(User $user, string $imagePath): void
    {
        $referenced = Message::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($imagePath) {
                $query->where('image_path', $imagePath)
                    ->orWhereJsonContains('image_paths', $imagePath);
            })
            ->exists();
        $uploaded = ChatImageUpload::where('user_id', $user->id)
            ->where('image_path', $imagePath)
            ->exists();

        abort_unless($referenced || $uploaded, 422, '이미지를 찾을 수 없습니다.');
        abort_unless(Storage::disk('public')->exists($imagePath), 422, '이미지를 찾을 수 없습니다.');
    }

    /**
     * 보관함 이미지를 삭제한다.
     * 내가 보낸 해당 이미지(지문)의 참조를 내 모든 메시지에서 제거하고, 더 이상 어느 메시지에서도
     * 쓰이지 않게 되면 저장 파일도 함께 정리한다.
     */
    public function deleteImage(User $user, Message $message, ?string $imagePath = null): void
    {
        abort_unless($message->user_id === $user->id, 403);

        // 다중 이미지 말풍선에서는 어떤 지문을 지울지 명시한다. 없으면 첫 이미지를 대상으로 한다.
        $paths = $message->image_paths ?? ($message->image_path ? [$message->image_path] : []);
        $target = $imagePath !== null && trim($imagePath) !== ''
            ? $imagePath
            : ($paths[0] ?? null);

        if ($target === null) {
            return;
        }

        // 같은 지문(해시) 이미지를 참조하는 내 모든 메시지에서 참조를 제거한다 (단일·다중 공통)
        Message::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($target) {
                $query->where('image_path', $target)
                    ->orWhereJsonContains('image_paths', $target);
            })
            ->get()
            ->each(function (Message $ownMessage) use ($target): void {
                $ownPaths = array_values(array_filter(
                    $ownMessage->image_paths ?? ($ownMessage->image_path ? [$ownMessage->image_path] : []),
                    fn (string $path) => $path !== $target,
                ));

                $ownMessage->update([
                    'image_path' => $ownPaths[0] ?? null,
                    'image_paths' => count($ownPaths) ? $ownPaths : null,
                ]);
            });

        // 전송 전 업로드 소유권(아직 메시지가 아닌 지문) 기록도 함께 정리한다
        ChatImageUpload::where('user_id', $user->id)
            ->where('image_path', $target)
            ->delete();

        // 다른 사용자의 메시지에서도 더 이상 참조되지 않을 때만 파일을 정리한다
        if (! Message::query()
            ->where(function ($query) use ($target) {
                $query->where('image_path', $target)
                    ->orWhereJsonContains('image_paths', $target);
            })
            ->exists()) {
            Storage::disk('public')->delete($target);
        }
    }

    /**
     * 내가 보낸 메시지를 삭제한다.
     * 이미지 메시지면 포함된 각 지문의 파일을 정리한다 — 다른 메시지(다른 사용자 포함)가
     * 아직 참조하거나 전송 전 업로드 기록이 남아 있으면 파일은 유지한다.
     */
    public function deleteMessage(User $user, Message $message): void
    {
        abort_unless($message->user_id === $user->id, 403);

        $paths = $message->image_paths ?? ($message->image_path ? [$message->image_path] : []);

        $message->delete();

        foreach ($paths as $path) {
            ChatImageUpload::where('user_id', $user->id)
                ->where('image_path', $path)
                ->delete();

            $stillReferenced = Message::query()
                ->where(function ($query) use ($path) {
                    $query->where('image_path', $path)
                        ->orWhereJsonContains('image_paths', $path);
                })
                ->exists();

            if (! $stillReferenced) {
                Storage::disk('public')->delete($path);
            }
        }
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
        $paths = $message->image_paths ?? ($message->image_path ? [$message->image_path] : []);

        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'body' => $message->body,
            'type' => $message->type ?? 'text',
            'payload' => $message->payload ?: (object) [],
            'image_url' => count($paths) ? '/api/chat/images/'.basename($paths[0]) : null,
            // 한 개 말풍선에 여러 장 — 순서 유지
            'images' => array_values(array_map(
                fn (string $path) => '/api/chat/images/'.basename($path),
                $paths,
            )),
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
