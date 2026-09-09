<?php

namespace App\Services\Admin;

use App\Models\Conversation;
use App\Models\Driver;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\OrderEvent;
use App\Models\Report;
use App\Models\Settlement;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\Chat\ChatService;
use App\Services\Order\OrderOfferService;
use Illuminate\Http\Request;

/**
 * 관리자 개입(B-2) — 문제 운행·사용자만 관리자가 손대는 운영 업무.
 * 운행 숨김/보류/강제취소, 사용자 제재(주의·운행 제한·정지), 채팅 운영(확인·중재),
 * 정산 보류, 일일 운영 요약. 변경은 모두 운행 타임라인/사용자 알림으로 남긴다.
 */
class AdminOperationService
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly OrderOfferService $offerService,
    ) {}

    // ── 사용자 제재 ────────────────────────────────────────────────

    /**
     * 사용자 제재 상태를 변경한다 (기사·등록자 공통). 관리자 본인·상위 직원은 대상이 아니다.
     */
    public function setUserModeration(User $admin, User $user, string $status, string $note): void
    {
        abort_unless(isset(User::moderationOptions()[$status]), 422, '제재 상태가 올바르지 않습니다.');
        abort_if((int) $user->id === (int) $admin->id, 422, '본인은 제재할 수 없습니다.');
        abort_unless($admin->canManageUser($user), 403, '더 높은 등급의 관리자만 제재할 수 있습니다.');
        abort_if($user->moderation_status === $status, 422, '이미 같은 상태입니다.');

        abort_unless(trim($note) !== '', 422, '제재 사유를 입력해 주세요.');

        $user->forceFill([
            'moderation_status' => $status,
            'moderation_note' => trim($note),
            'moderation_by' => $admin->id,
            'moderation_at' => now(),
        ])->save();

        // 정지·제한 시 기사 운행중 상태를 해제하고 오프라인으로 돌려놓는다
        if (! $user->canOperate()) {
            $user->driver()->where('status', Driver::STATUS_ON_TRIP)->update([
                'status' => Driver::STATUS_OFFLINE,
                'status_updated_at' => now(),
            ]);
        }

        $user->notify(new OrderNotification(
            '운영 제재',
            "계정 상태가 '".(User::moderationOptions()[$status])."'(으)로 변경되었습니다. 사유: ".trim($note),
        ));

        AuditService::record($admin, 'user.moderation', "{$user->name}님 계정을 '".(User::moderationOptions()[$status])."'(으)로 제재. 사유: ".trim($note), [
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    // ── 운행 개입 ──────────────────────────────────────────────────

    /**
     * 운행 목록 — 관리자 개입 대상 검색 (노선·번호·기사·등록자 키워드, 상태 필터).
     *
     * @return array<int, array<string, mixed>>
     */
    public function orderList(Request $request): array
    {
        $q = trim((string) $request->string('q'));
        $status = $request->string('status')->toString();

        $query = Order::query()
            ->with(['user:id,name,role', 'claimant:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('order_number', 'like', "%{$q}%")
                        ->orWhere('id', (int) $q)
                        ->orWhere('pickup_location', 'like', "%{$q}%")
                        ->orWhere('dropoff_location', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $query->map(fn (Order $order) => $this->orderRow($order))->all();
    }

    /**
     * 운행 숨김 토글 — 마켓·추천에서 제외하되 등록자(내 마켓)와 관리자는 그대로 볼 수 있다.
     */
    public function setOrderVisibility(User $admin, Order $order, bool $hidden, string $reason): void
    {
        if ($hidden) {
            abort_unless(trim($reason) !== '', 422, '숨김 사유를 입력해 주세요.');
            abort_if((bool) $order->is_hidden, 422, '이미 숨겨진 운행입니다.');
        } else {
            abort_unless((bool) $order->is_hidden, 422, '숨겨지지 않은 운행입니다.');
        }

        $order->forceFill(['is_hidden' => $hidden])->save();

        OrderEvent::create([
            'order_id' => $order->id,
            'user_id' => $admin->id,
            'event' => $hidden ? 'admin_hidden' : 'admin_visible',
            'note' => $reason,
        ]);

        AuditService::record(
            $admin,
            $hidden ? 'order.hide' : 'order.unhide',
            ($hidden ? '운행을 숨겼습니다' : '운행 숨김을 해제했습니다')." ({$order->order_number}) 사유: {$reason}",
            ['order_id' => $order->id],
        );
    }

    /**
     * 운행 보류 토글 — 상태 진행을 동결한다 (일반 사용자 상태 변경 차단, 관리자만 해제).
     */
    public function setOrderHold(User $admin, Order $order, bool $hold, string $reason): void
    {
        if ($hold) {
            abort_unless(trim($reason) !== '', 422, '보류 사유를 입력해 주세요.');
            abort_if((bool) $order->admin_hold, 422, '이미 보류된 운행입니다.');
        } else {
            abort_unless((bool) $order->admin_hold, 422, '보류되지 않은 운행입니다.');
        }

        $order->forceFill([
            'admin_hold' => $hold,
            'admin_hold_reason' => $hold ? $reason : null,
        ])->save();

        OrderEvent::create([
            'order_id' => $order->id,
            'user_id' => $admin->id,
            'event' => $hold ? 'admin_hold' : 'admin_release',
            'note' => $reason,
        ]);

        $registrant = User::query()->find($order->user_id);

        if ($registrant !== null) {
            $registrant->notify(new OrderNotification(
                $hold ? '운행 보류' : '운행 보류 해제',
                ($hold ? '관리자가 운행을 보류했습니다. 사유: '.$reason : '관리자가 운행 보류를 해제했습니다.'),
                $order->id,
            ));
        }

        AuditService::record(
            $admin,
            $hold ? 'order.hold' : 'order.release',
            ($hold ? '운행을 보류했습니다' : '운행 보류를 해제했습니다')." ({$order->order_number}) 사유: {$reason}",
            ['order_id' => $order->id],
        );
    }

    /**
     * 운행 강제 취소 — 진행 단계(승인 대기~운행중) 문제 운행을 관리자가 즉시 종료한다.
     * 완료·정산된 운행은 대상이 아니다(금액이 확정된 뒤라 정산 보류로 처리).
     */
    public function forceCancelOrder(User $admin, Order $order, string $reason): void
    {
        abort_unless(trim($reason) !== '', 422, '취소 사유를 입력해 주세요.');

        abort_unless(in_array($order->status, [
            Order::STATUS_PUBLISHED,
            Order::STATUS_TRADING,
            Order::STATUS_ACCEPTANCE_PENDING,
            Order::STATUS_ACCEPTED,
            Order::STATUS_DRIVING,
        ], true), 422, '완료·정산된 운행은 강제 취소할 수 없습니다. 정산 보류로 처리하세요.');

        $performerId = $this->performerIdOf($order);

        // 취소 사유를 먼저 반영 — 상태 전이 이벤트(타임라인)에 함께 남는다
        $order->forceFill(['cancel_reason' => $reason])->save();
        $order->transitionTo(Order::STATUS_CANCELLED);

        // 마켓에서 빠진 운행처럼 남은 요금 제안을 정리한다
        $this->offerService->cancelPendingFor($order);

        // 등록자(원 소유자)와 수행 기사에게 각각 취소 사실을 알린다
        $registrantId = $order->original_owner_id ?? $order->user_id;
        $notifiedIds = [];

        if ($registrantId !== null) {
            $registrant = User::query()->find($registrantId);

            if ($registrant !== null) {
                $registrant->notify(new OrderNotification(
                    '운행 강제 취소',
                    "관리자가 등록하신 운행을 취소했습니다. 사유: {$reason}",
                    $order->id,
                ));
                $notifiedIds[] = $registrant->id;
            }
        }

        // 수행 기사 상태 해제 (운행중이었다면 온라인 복귀) + 기사 알림
        if ($performerId !== null && ! in_array($performerId, $notifiedIds, true)) {
            $performer = User::query()->find($performerId);

            if ($performer !== null) {
                $performer->driver()->where('status', Driver::STATUS_ON_TRIP)->update([
                    'status' => Driver::STATUS_ONLINE,
                    'status_updated_at' => now(),
                ]);

                $performer->notify(new OrderNotification(
                    '운행 강제 취소',
                    "관리자가 진행하시던 운행을 취소했습니다. 사유: {$reason}",
                    $order->id,
                ));
            }
        }

        AuditService::record(
            $admin,
            'order.force-cancel',
            "운행을 강제 취소했습니다 ({$order->order_number}) 사유: {$reason}",
            ['order_id' => $order->id, 'performer_id' => $performerId],
        );
    }

    // ── 채팅 운영 ──────────────────────────────────────────────────

    /**
     * 관리자용 대화 목록 — 최근 메시지순 (문제 확인용). 참가자 이름·연결 운행 포함.
     *
     * @return array<int, array<string, mixed>>
     */
    public function conversationList(Request $request): array
    {
        $q = trim((string) $request->string('q'));

        $conversations = Conversation::query()
            ->with(['users:id,name,role', 'order:id,pickup_location,dropoff_location,service_date,service_time,status'])
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('order', function ($sub) use ($q) {
                    $sub->where('pickup_location', 'like', "%{$q}%")
                        ->orWhere('dropoff_location', 'like', "%{$q}%")
                        ->orWhere('order_number', 'like', "%{$q}%");
                })->orWhereHas('users', fn ($sub) => $sub->where('name', 'like', "%{$q}%"));
            })
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        return $conversations->map(function (Conversation $conversation): array {
            $order = $conversation->order;

            return [
                'id' => $conversation->id,
                'users' => $conversation->users->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role_label' => AdminOperationService::userRoleLabel($user),
                ])->values()->all(),
                'order' => $order ? [
                    'id' => $order->id,
                    'route' => trim(($order->pickup_location ?: '').' → '.($order->dropoff_location ?: '')),
                    'service_date' => $order->service_date,
                    'service_time' => $order->service_time,
                    'status' => $order->status,
                    'status_label' => Order::statusOptions()[$order->status] ?? $order->status,
                ] : null,
                'last_message_at' => $conversation->last_message_at?->diffForHumans(),
            ];
        })->all();
    }

    /**
     * 관리자용 대화 읽기 — 참가자 메시지를 문제 확인 용도로 그대로 반환한다.
     *
     * @return array<int, array<string, mixed>>
     */
    public function conversationMessages(Conversation $conversation): array
    {
        return $conversation->messages()
            ->with('user:id,name')
            ->get()
            ->map(fn (Message $message) => $this->chatService->serializeMessage($message))
            ->values()
            ->all();
    }

    /**
     * 중재 메시지 직렬화 — 컨트롤러 응답이 채팅 화면과 같은 형태를 쓰도록 위임한다.
     *
     * @return array<string, mixed>
     */
    public function chatSerialize(Message $message): array
    {
        return $this->chatService->serializeMessage($message);
    }

    /**
     * 관리자 중재 메시지 — 대화에 운영팀 메시지로 기록한다 (참가자에게 새 메시지로 전달).
     */
    public function moderate(User $admin, Conversation $conversation, string $body): Message
    {
        abort_unless(trim($body) !== '', 422, '중재 메시지를 입력해 주세요.');

        $message = $conversation->messages()->create([
            'user_id' => $admin->id,
            'body' => trim($body),
            'payload' => ['moderator' => true],
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message->load('user:id,name');
    }

    // ── 정산 보류 ──────────────────────────────────────────────────

    /**
     * 미지급 정산 원장 목록 (보류 포함) — 출금 처리와 별개로 확인한다.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingSettlements(): array
    {
        return Settlement::query()
            ->where('status', Settlement::STATUS_PENDING)
            ->with(['driver:id,name', 'order:id,pickup_location,dropoff_location,service_date,service_time'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (Settlement $settlement): array {
                $order = $settlement->order;

                return [
                    'id' => $settlement->id,
                    'driver' => $settlement->driver ? ['id' => $settlement->driver->id, 'name' => $settlement->driver->name] : null,
                    'route' => $order ? trim(($order->pickup_location ?: '').' → '.($order->dropoff_location ?: '')) : '',
                    'service_date' => $order?->service_date,
                    'net_amount' => (int) $settlement->net_amount,
                    'hold_reason' => $settlement->hold_reason,
                    'created_at_iso' => $settlement->created_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * 정산 보류 토글 — 보류된 정산은 출금 신청 대상에서 제외된다.
     */
    public function setSettlementHold(User $admin, Settlement $settlement, bool $hold, string $reason): void
    {
        if ($hold) {
            abort_unless(trim($reason) !== '', 422, '보류 사유를 입력해 주세요.');
            abort_if($settlement->hold_reason !== null, 422, '이미 보류된 정산입니다.');
            abort_if($settlement->payout_id !== null, 409, '출금 신청에 묶인 정산은 보류할 수 없습니다.');
        } else {
            abort_unless($settlement->hold_reason !== null, 422, '보류되지 않은 정산입니다.');
        }

        $settlement->forceFill(['hold_reason' => $hold ? trim($reason) : null])->save();

        $driver = $settlement->driver;

        if ($driver !== null) {
            $driver->notify(new OrderNotification(
                $hold ? '정산 보류' : '정산 보류 해제',
                $hold ? "정산이 보류되었습니다. 사유: {$reason}" : '정산 보류가 해제되었습니다.',
                $settlement->order_id,
            ));
        }

        AuditService::record(
            $admin,
            $hold ? 'settlement.hold' : 'settlement.release',
            ($hold ? '정산을 보류했습니다' : '정산 보류를 해제했습니다')." (#{$settlement->id}) 사유: {$reason}",
            ['settlement_id' => $settlement->id],
        );
    }

    // ── 일일 운영 요약 ─────────────────────────────────────────────

    /**
     * 운영 지표 — 정산·매칭·신고·운행 파이프라인 현황을 한 화면에 요약한다 (Q-6).
     * "정상 운행은 자동 처리, 문제 운행만 관리자 개입" 원칙에 따라 개입이 필요한 잔여를 먼저 보여준다.
     *
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        // 운행 파이프라인 — 진행 중인 활성 운행(문제 운행 제외 제로·숨김 제외) 상태 분포
        $activeStatuses = [
            Order::STATUS_ACCEPTANCE_PENDING,
            Order::STATUS_ACCEPTED,
            Order::STATUS_DRIVING,
        ];
        $pipeline = Order::query()
            ->whereIn('status', $activeStatuses)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // 매칭 — 최근 신청·승인 흐름 (30일)
        $since30d = now()->subDays(30)->startOfDay();
        $claims = OrderClaim::query()
            ->where('created_at', '>=', $since30d)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // 정산 — 지급 대기(출금 가능) 잔액 + 이번 달 지급·수수료
        $settlement = [
            'pending_amount' => (int) Settlement::query()
                ->where('status', Settlement::STATUS_PENDING)
                ->sum('net_amount'),
            'paid_month_amount' => (int) Settlement::query()
                ->where('status', Settlement::STATUS_PAID)
                ->where('paid_at', '>=', $month)
                ->sum('net_amount'),
            'fee_month_amount' => (int) Settlement::query()
                ->where('paid_at', '>=', $month)
                ->sum('fee_amount'),
        ];

        // 신고 — 미처리(접수+조사) > 처리 완료
        $reportPendingStatuses = [Report::STATUS_PENDING, Report::STATUS_REVIEWING, Report::STATUS_INVESTIGATING];
        $reports = [
            'pending' => Report::query()->whereIn('status', $reportPendingStatuses)->count(),
            'completed' => Report::query()->whereIn('status', [Report::STATUS_HANDLED, Report::STATUS_COMPLETED])->count(),
        ];

        // 사용자 — 최근 가입(기사·등록자)과 제재 계정, 인증 대기
        $users = [
            'drivers_today' => User::query()->where('role', User::ROLE_DRIVER)->where('created_at', '>=', $today)->count(),
            'customers_today' => User::query()->where('role', User::ROLE_CUSTOMER)->where('created_at', '>=', $today)->count(),
            'restricted' => User::query()
                ->whereIn('moderation_status', [User::MODERATION_RESTRICTED, User::MODERATION_SUSPENDED])
                ->count(),
        ];

        return [
            'pipeline' => [
                'acceptance_pending' => (int) ($pipeline[Order::STATUS_ACCEPTANCE_PENDING] ?? 0),
                'accepted' => (int) ($pipeline[Order::STATUS_ACCEPTED] ?? 0),
                'driving' => (int) ($pipeline[Order::STATUS_DRIVING] ?? 0),
            ],
            'matching_30d' => [
                'pending' => (int) ($claims['pending'] ?? 0),
                'approved' => (int) ($claims['approved'] ?? 0),
                'rejected' => (int) ($claims['rejected'] ?? 0),
            ],
            'settlement' => $settlement,
            'reports' => $reports,
            'users' => $users,
        ];
    }

    /**
     * 일일 운영 화면 데이터 — 🔴 즉시 처리 > 🟡 확인 필요 > 🟢 정상 순서로 관리자가 우선 처리할 일을 보여준다.
     *
     * @return array<string, array<string, int>>
     */
    public function dailySummary(): array
    {
        // 🔴 즉시 처리 — 신고 접수, 보류(운행·정산), 정지·제한 계정
        $red = [
            'reports_pending' => Report::query()->where('status', Report::STATUS_PENDING)->count(),
            'orders_hold' => Order::query()->where('admin_hold', true)->count(),
            'settlements_hold' => Settlement::query()->where('status', Settlement::STATUS_PENDING)
                ->whereNotNull('hold_reason')->count(),
            'users_suspended' => User::query()
                ->whereIn('moderation_status', [User::MODERATION_RESTRICTED, User::MODERATION_SUSPENDED])
                ->count(),
        ];

        // 🟡 확인 필요 — 승인 대기 운행, 확정 대기 요청 카드(시간/경로/요금/취소)
        $yellow = [
            'approval_waiting' => Order::query()->where('status', Order::STATUS_ACCEPTANCE_PENDING)->count(),
            'request_cards_pending' => Message::query()
                ->where('payload->status', 'pending')
                ->whereIn('type', ChatService::RESOLVABLE_TYPES)
                ->count(),
        ];

        // 🟢 정상 — 오늘 정상 완료·정산된 운행 수
        $today = now()->startOfDay();
        $green = [
            'today_completed' => Order::query()
                ->where('completed_at', '>=', $today)
                ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_SETTLED])
                ->count(),
        ];

        return compact('red', 'yellow', 'green');
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(Order $order): array
    {
        $driver = null;

        if ($order->claimant_user_id !== null) {
            $driver = $order->claimant ? [
                'id' => $order->claimant->id,
                'name' => $order->claimant->name,
            ] : null;
        }

        // 가져오기 요청 없이 운행이 진행된 과거 건 — user_id가 수행 기사인 경우
        if ($driver === null && $order->claimed_at !== null) {
            $driver = ['id' => $order->user_id, 'name' => null];
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'route' => trim(($order->pickup_location ?: '').' → '.($order->dropoff_location ?: '')),
            'service_date' => $order->service_date,
            'service_time' => $order->service_time,
            'amount' => (int) ($order->actual_revenue ?? $order->amount_value ?? $order->expected_revenue ?? 0),
            'status' => $order->status,
            'status_label' => Order::statusOptions()[$order->status] ?? $order->status,
            'is_hidden' => (bool) $order->is_hidden,
            'admin_hold' => (bool) $order->admin_hold,
            'admin_hold_reason' => $order->admin_hold_reason,
            'registrant' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
            ] : null,
            'driver' => $driver,
            'created_at_iso' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * 진행(수행) 기사 id — 가져오기 요청자(claimant) 우선, 요청 없이 진행된 건은 user_id.
     */
    private function performerIdOf(Order $order): ?int
    {
        if ($order->claimant_user_id !== null) {
            return $order->claimant_user_id;
        }

        if ($order->claimed_at !== null) {
            return $order->user_id;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function userRoleLabels(): array
    {
        return User::roleLabels();
    }

    private static function userRoleLabel(User $user): string
    {
        return self::userRoleLabels()[$user->role] ?? $user->role;
    }
}
