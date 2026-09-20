<?php

namespace App\Services\Chat;

use App\Models\ChatImageUpload;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\Order\OrderTransitionService;
use App\Support\Orders\ChineseTextNormalizer;
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
     * 대화에서 확정(수락/거절)할 수 있는 요청 카드 종류.
     * 승인 요청(approval)은 가져오기 승인 흐름(claim)이 전담하므로 여기서 제외한다.
     *
     * @var array<int, string>
     */
    public const RESOLVABLE_TYPES = ['time_change', 'route_change', 'payment_change', 'cancel'];

    public function __construct(
        private readonly OrderTransitionService $transitionService,
    ) {}

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
                    'route' => ChineseTextNormalizer::routeLabel($conversation->order->pickup_location ?? '', $conversation->order->dropoff_location ?? ''),
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
     * 내가 참여한 모든 대화에서 상대가 보낸 안 읽은 메시지를 읽음 처리한다.
     * 대화방을 하나씩 열지 않아도 안 읽음 배지를 한 번에 정리할 수 있다.
     *
     * @return int 읽음 처리된 메시지 수
     */
    public function markAllRead(User $user): int
    {
        $conversationIds = $user->conversations()->pluck('conversations.id');

        if ($conversationIds->isEmpty()) {
            return 0;
        }

        return Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * 대화 증분 동기화 — after_id 이후의 새 메시지와 읽음 상태 변화만 반환한다.
     * 3초 폴링에서 매번 전체 메시지를 내려받지 않도록 가볍게 유지한다.
     *
     * @return array{messages: array<int, array<string, mixed>>, read_ids: array<int, int>}
     */
    public function sync(User $user, Conversation $conversation, int $afterId): array
    {
        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 403);

        $newMessages = $conversation->messages()
            ->with('user:id,name')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message) => $this->serializeMessage($message))
            ->values()
            ->all();

        // 대화를 열어두면 상대 메시지를 읽음 처리한다
        $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // 내가 보낸 메시지 중 이미 읽음 처리된 id — 보낸 쪽 '읽음' 표시 갱신용 (클라이언트가 diff)
        $readIds = $conversation->messages()
            ->where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'messages' => $newMessages,
            'read_ids' => $readIds,
        ];
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
     * 받은 요청 카드를 수락·거절로 확정한다.
     * 확정되면 요청 종류에 따라 운행(시간·경로·금액·취소)에 반영되고,
     * payload.status와 미리보기 문구가 갱신되며 요청을 보낸 상대에게 결과가 알림으로 전달된다.
     */
    public function resolveRequest(User $user, Conversation $conversation, Message $message, string $action, ?string $reason = null): Message
    {
        abort_unless($conversation->users()->where('users.id', $user->id)->exists(), 403);

        abort_unless($message->conversation_id === $conversation->id, 404, '요청을 찾을 수 없습니다.');

        abort_unless(in_array($message->type ?? '', self::RESOLVABLE_TYPES, true), 422, '확정할 수 없는 요청입니다.');

        // 요청을 보낸 쪽 본인은 스스로 확정할 수 없다 — 상대방(수신자)만 수락·거절한다
        abort_if($message->user_id === $user->id, 403, '보낸 요청은 상대방만 확정할 수 있습니다.');

        $payload = $message->payload ?? [];

        abort_unless(($payload['status'] ?? 'pending') === 'pending', 409, '이미 처리된 요청입니다.');

        $accepted = $action === 'accept';

        if ($accepted) {
            $order = $conversation->order;

            abort_unless($order !== null, 409, '연결된 운행이 없습니다.');

            $this->applyRequestToOrder($user, $order, $message->type, $payload);
        }

        $payload['status'] = $accepted ? 'accepted' : 'rejected';

        // 응답 사유(거절 등)는 요청 시 적은 payload['reason']와 분리해 보관한다
        if (filled($reason)) {
            $payload['response_reason'] = $reason;
        }

        $message->forceFill([
            'payload' => $payload,
            'body' => $this->resolveSummary($message->type, $accepted),
        ])->save();

        $conversation->forceFill(['last_message_at' => now()])->save();

        $this->notifyRequestResolved($user, $message, $conversation, $accepted, $reason);

        return $message;
    }

    /**
     * 수락된 요청을 연결된 운행에 반영한다.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyRequestToOrder(User $actor, Order $order, string $type, array $payload): void
    {
        // 진행이 끝났거나 취소된 운행, 이미 운행이 시작된(driving) 운행은 확정할 수 없다
        abort_unless(in_array($order->status, [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
            Order::STATUS_ACCEPTANCE_PENDING,
            Order::STATUS_ACCEPTED,
        ], true), 409, '현재 상태에서는 요청을 확정할 수 없습니다.');

        match ($type) {
            'time_change' => $this->applyTimeChange($order, $payload),
            'route_change' => $this->applyRouteChange($order, $payload),
            'payment_change' => $this->applyPaymentChange($order, $payload),
            'cancel' => $this->applyCancel($actor, $order, $payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyTimeChange(Order $order, array $payload): void
    {
        $toTime = trim((string) ($payload['to_time'] ?? ''));

        abort_unless(preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $toTime) === 1, 422, '변경 시간 형식이 올바르지 않습니다.');

        $order->forceFill(['service_time' => $toTime])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyRouteChange(Order $order, array $payload): void
    {
        $target = $payload['target'] ?? '';

        abort_unless(in_array($target, ['pickup', 'dropoff'], true), 422, '변경 대상이 올바르지 않습니다.');

        $to = trim((string) ($payload['to'] ?? ''));

        abort_unless($to !== '', 422, '변경 위치를 입력해야 합니다.');

        $order->forceFill([$target === 'pickup' ? 'pickup_location' : 'dropoff_location' => $to])->save();
    }

    /**
     * 협의 금액을 계약 금액(expected_revenue·amount_value)으로 반영한다.
     * 등록자가 6만에 등록해도 기사와 협의한 6만5천이 확정되면 계약 금액은 6만5천이다 (오퍼 수락과 동일 규칙).
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyPaymentChange(Order $order, array $payload): void
    {
        $amount = (int) ($payload['amount'] ?? 0);

        abort_unless($amount > 0, 422, '변경 금액을 올바르게 입력해야 합니다.');

        $order->forceFill([
            'expected_revenue' => $amount,
            'amount_value' => $amount,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyCancel(User $actor, Order $order, array $payload): void
    {
        // 취소 확정은 운행 관계자(원 등록자 또는 현재 수행자)만 할 수 있다
        abort_unless(
            $order->user_id === $actor->id || $order->original_owner_id === $actor->id,
            403,
            '운행 관계자만 취소를 확정할 수 있습니다.',
        );

        abort_unless($order->canTransitionTo(Order::STATUS_CANCELLED), 409, '현재 상태에서는 취소할 수 없습니다.');

        // 취소 사유는 요청 카드에 적은 사유를 우선하고, 없으면 채팅 합의로 기록한다
        $this->transitionService->transition(
            $actor,
            $order,
            Order::STATUS_CANCELLED,
            (string) ($payload['reason'] ?? '채팅 합의 취소'),
        );
    }

    /**
     * 확정 결과 문구 — "시간 변경 요청을 수락했습니다" (대화 목록 미리보기용).
     */
    private function resolveSummary(string $type, bool $accepted): string
    {
        return $this->requestLabel($type).'을 '.($accepted ? '수락했습니다' : '거절했습니다');
    }

    /**
     * 요청 카드 한글 라벨 — "시간 변경 요청".
     */
    private function requestLabel(string $type): string
    {
        return match ($type) {
            'time_change' => '시간 변경 요청',
            'route_change' => '경로 변경 요청',
            'payment_change' => '요금 협의 요청',
            'cancel' => '취소 요청',
            default => '요청',
        };
    }

    /**
     * 요청을 보낸 상대에게 확정 결과 알림을 보낸다.
     *
     * @param  array<string, mixed>  $payload
     */
    private function notifyRequestResolved(User $resolver, Message $message, Conversation $conversation, bool $accepted, ?string $reason): void
    {
        $sender = User::query()->find($message->user_id);

        if ($sender === null || $sender->id === $resolver->id) {
            return;
        }

        $label = $this->requestLabel($message->type);

        $orderLabel = $conversation->order !== null
            ? "{$conversation->order->rideSummary()} 운행의 "
            : '';

        $messageText = $accepted
            ? "{$orderLabel}{$label}을(를) 수락했습니다."
            : "{$orderLabel}{$label}을(를) 거절했습니다.".($reason !== null && $reason !== '' ? " (사유: {$reason})" : '');

        $sender->notify(new OrderNotification(
            $accepted ? '요청 수락됨' : '요청 거절됨',
            $messageText,
            $conversation->order?->id,
        ));
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
