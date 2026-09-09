<?php

namespace App\Services\Settlement;

use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Settlement;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * 정산·출금 — 정산 원장 생성(수수료 계산), 계좌 등록, 출금 신청과 관리자 지급 처리를 담당한다.
 *
 * 흐름: 운행 정산(settled) → 원장 확정(운행금액·수수료·실지급액) → 기사 출금 신청 → 관리자 지급 완료.
 */
class SettlementService
{
    /**
     * 플랫폼 수수료율 — 운행금액(실제 수익 우선)의 5%.
     */
    public const FEE_RATE = 0.05;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * 정산된 운행의 원장을 만든다 (중복 방지 — 운행당 원장 1개).
     * 정산 대상 금액은 실제 수익(actual_revenue)을 우선하고 없으면 계약 금액을 쓴다.
     */
    public function createFor(Order $order): ?Settlement
    {
        if (Settlement::query()->where('order_id', $order->id)->exists()) {
            return null;
        }

        $gross = (int) ($order->actual_revenue
            ?? $order->amount_value
            ?? $order->expected_revenue
            ?? 0);

        $fee = (int) round($gross * self::FEE_RATE);
        $net = max(0, $gross - $fee);

        return Settlement::query()->create([
            'order_id' => $order->id,
            'driver_id' => $order->user_id,
            'registrant_id' => $order->original_owner_id,
            'gross_amount' => $gross,
            'fee_amount' => $fee,
            'net_amount' => $net,
            'status' => Settlement::STATUS_PENDING,
        ]);
    }

    /**
     * 완료된 정상 운행을 자동 정산 처리한다 (백그라운드 스케줄러).
     * 정상 운행은 자동으로 흐르게 하고 문제 운행(보류 등)만 관리자가 개입하는 운영 원칙에 따른다.
     * 완료 후 유예 시간(config: settlement.auto_after_hours) 안에는 등록자가 문제를 제기하거나
     * 보류를 요청할 수 있으므로, 유예가 지난 운행만 정산 원장을 만들고 settled로 전환한다.
     * 실제 지급은 기사 출금 신청 → 관리자 지급 단계라 자동화 범위 밖이다.
     *
     * @return int 자동 정산된 운행 수
     */
    public function autoSettleCompleted(): int
    {
        $afterHours = max(0, (int) config('settlement.auto_after_hours', 24));
        $cutoff = now()->subHours($afterHours);

        $orders = Order::query()
            ->where('status', Order::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->where('admin_hold', false) // 보류(B-2) 운행은 시스템도 정산하지 않는다
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('settlements')
                    ->whereColumn('settlements.order_id', 'orders.id');
            })
            ->limit(50)
            ->get();

        $processed = 0;

        foreach ($orders as $order) {
            DB::transaction(function () use ($order) {
                $this->createFor($order);
                // 정산(settled) 전이 — 운행 타임라인에도 상태 변경이 기록된다
                $order->transitionTo(Order::STATUS_SETTLED);
            });

            $this->notifyAutoSettled($order);

            $processed++;
        }

        return $processed;
    }

    /**
     * 자동 정산 결과를 당사자(등록자·수행 기사)에게 알린다.
     * 같은 사용자가 등록자이자 수행자인 경우 한 번만 보낸다.
     */
    private function notifyAutoSettled(Order $order): void
    {
        $registrantId = $order->original_owner_id ?? $order->user_id;
        $performerId = $order->user_id;
        $notified = [];

        $registrant = $registrantId !== null ? User::query()->find($registrantId) : null;

        if ($registrant !== null) {
            $this->notificationService->notifyOnce(
                $registrant,
                '자동 정산 완료',
                $order->id,
                "{$order->rideSummary()} 운행이 정상 처리되어 자동 정산되었습니다.",
            );
            $notified[$registrant->id] = true;
        }

        if ($performerId !== null && ! isset($notified[$performerId])) {
            $performer = User::query()->find($performerId);

            if ($performer !== null) {
                $this->notificationService->notifyOnce(
                    $performer,
                    '정산 완료',
                    $order->id,
                    "{$order->rideSummary()} 운행 정산이 완료되어 출금 신청할 수 있습니다.",
                );
            }
        }
    }

    /**
     * 기사 정산 화면 요약 — 출금 가능(미지급 정산 합계), 이번 달 정산, 최근 정산 내역, 등록 계좌.
     *
     * @return array<string, mixed>
     */
    public function summaryFor(User $driver): array
    {
        $query = Settlement::query()->where('driver_id', $driver->id)->with('order:id,pickup_location,dropoff_location,service_date,service_time');

        $pending = $query->clone()
            ->where('status', Settlement::STATUS_PENDING)
            ->whereNull('hold_reason') // 보류 정산은 출금 가능에서 제외 (B-2)
            ->get();

        // 보류된 정산 — 사유와 함께 표시해 기사가 상황을 안다
        $held = $query->clone()
            ->where('status', Settlement::STATUS_PENDING)
            ->whereNotNull('hold_reason')
            ->get();

        $monthStart = now()->startOfMonth();
        $thisMonth = $query->clone()
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(gross_amount),0) as gross, COALESCE(SUM(fee_amount),0) as fee, COALESCE(SUM(net_amount),0) as net')
            ->first();

        return [
            'account' => $driver->bankAccount?->only(['bank_name', 'account_number', 'account_holder']),
            'pending_total' => (int) $pending->sum('net_amount'),
            'pending_count' => $pending->count(),
            'held_total' => (int) $held->sum('net_amount'),
            'held_count' => $held->count(),
            'this_month' => [
                'count' => (int) ($thisMonth->count ?? 0),
                'gross' => (int) ($thisMonth->gross ?? 0),
                'fee' => (int) ($thisMonth->fee ?? 0),
                'net' => (int) ($thisMonth->net ?? 0),
            ],
            'recent' => $query->clone()->latest('id')->limit(30)->get()
                ->map(fn (Settlement $settlement) => $this->settlementRow($settlement)),
        ];
    }

    /**
     * 계좌를 등록/갱신한다 (기사별 1개 — 최신 등록 계좌로 대체).
     *
     * @param  array{bank_name: string, account_number: string, account_holder: string}  $data
     * @return array<string, string>
     */
    public function saveAccount(User $user, array $data): array
    {
        $user->bankAccount()->updateOrCreate(['user_id' => $user->id], $data);

        return $data;
    }

    /**
     * 출금 신청 — 출금 가능(미지급) 정산 전부를 한 건으로 묶어 신청한다.
     * 신청 시점의 계좌 정보를 스냅샷으로 남겨 이후 계좌를 바꿔도 지급 대상은 유지된다.
     */
    public function requestPayout(User $driver): PayoutRequest
    {
        $account = $driver->bankAccount;

        abort_unless($account !== null, 422, '먼저 출금 계좌를 등록해 주세요.');

        $pending = Settlement::query()
            ->where('driver_id', $driver->id)
            ->where('status', Settlement::STATUS_PENDING)
            ->whereNull('hold_reason') // 보류 정산은 출금 신청에 포함하지 않는다 (B-2)
            ->lockForUpdate()
            ->get();

        abort_unless($pending->isNotEmpty(), 409, '출금할 정산 금액이 없습니다.');

        abort_unless(PayoutRequest::query()
            ->where('driver_id', $driver->id)
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->doesntExist(), 409, '처리 대기 중인 출금 신청이 있습니다.');

        $amount = (int) $pending->sum('net_amount');

        return DB::transaction(function () use ($driver, $account, $pending, $amount): PayoutRequest {
            $payout = PayoutRequest::query()->create([
                'driver_id' => $driver->id,
                'amount' => $amount,
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'account_holder' => $account->account_holder,
                'status' => PayoutRequest::STATUS_PENDING,
            ]);

            // 신청된 정산을 이 출금 건에 묶는다
            Settlement::query()
                ->whereIn('id', $pending->pluck('id'))
                ->update(['payout_id' => $payout->id]);

            return $payout;
        });
    }

    /**
     * 내 출금 신청 내역 (최신이 위).
     *
     * @return array<int, array<string, mixed>>
     */
    public function payoutsOf(User $driver): array
    {
        return PayoutRequest::query()
            ->where('driver_id', $driver->id)
            ->latest('id')
            ->get()
            ->map(fn (PayoutRequest $payout) => $this->payoutRow($payout))
            ->all();
    }

    /**
     * 관리자 — 처리 대기 중인 출금 신청 목록 (기사 이름 포함).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingPayouts(): array
    {
        return PayoutRequest::query()
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->with('driver:id,name')
            ->latest('id')
            ->get()
            ->map(fn (PayoutRequest $payout) => $this->payoutRow($payout))
            ->all();
    }

    /**
     * 관리자 — 출금 신청을 지급 완료 처리한다. 묶인 정산 원장도 지급 완료로 확정한다.
     */
    public function payPayout(User $admin, PayoutRequest $payout): void
    {
        abort_unless($payout->status === PayoutRequest::STATUS_PENDING, 409, '이미 처리된 출금 신청입니다.');

        DB::transaction(function () use ($admin, $payout) {
            $payout->forceFill([
                'status' => PayoutRequest::STATUS_PAID,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ])->save();

            Settlement::query()
                ->where('payout_id', $payout->id)
                ->update([
                    'status' => Settlement::STATUS_PAID,
                    'paid_at' => now(),
                ]);
        });

        $driver = $payout->driver;

        if ($driver !== null) {
            $driver->notify(new OrderNotification(
                '출금 지급 완료',
                number_format($payout->amount).'원이 출금 계좌로 지급되었습니다.',
                null,
            ));
        }
    }

    /**
     * 관리자 — 출금 신청을 거절한다. 묶인 정산은 다시 출금 가능 상태로 돌아간다.
     */
    public function rejectPayout(User $admin, PayoutRequest $payout, ?string $reason): void
    {
        abort_unless($payout->status === PayoutRequest::STATUS_PENDING, 409, '이미 처리된 출금 신청입니다.');

        DB::transaction(function () use ($admin, $payout, $reason) {
            $payout->forceFill([
                'status' => PayoutRequest::STATUS_REJECTED,
                'note' => $reason,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ])->save();

            Settlement::query()
                ->where('payout_id', $payout->id)
                ->update(['payout_id' => null]);
        });

        $driver = $payout->driver;

        if ($driver !== null) {
            $driver->notify(new OrderNotification(
                '출금 신청 거절',
                '출금 신청이 거절되었습니다.'.($reason !== null && $reason !== '' ? " 사유: {$reason}" : ''),
                null,
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementRow(Settlement $settlement): array
    {
        $order = $settlement->order;

        return [
            'id' => $settlement->id,
            'status' => $settlement->status,
            'route' => $order !== null
                ? trim(($order->pickup_location ?: '').' → '.($order->dropoff_location ?: ''))
                : '',
            'service_date' => $order?->service_date,
            'service_time' => $order?->service_time,
            'gross_amount' => (int) $settlement->gross_amount,
            'fee_amount' => (int) $settlement->fee_amount,
            'net_amount' => (int) $settlement->net_amount,
            'hold_reason' => $settlement->hold_reason,
            'paid_at' => $settlement->paid_at?->toIso8601String(),
            'created_at_iso' => $settlement->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payoutRow(PayoutRequest $payout): array
    {
        return [
            'id' => $payout->id,
            'status' => $payout->status,
            'amount' => (int) $payout->amount,
            'bank_name' => $payout->bank_name,
            'account_number' => $payout->account_number,
            'account_holder' => $payout->account_holder,
            'note' => $payout->note,
            'driver' => $payout->driver ? ['id' => $payout->driver->id, 'name' => $payout->driver->name] : null,
            'created_at_iso' => $payout->created_at?->toIso8601String(),
            'processed_at_iso' => $payout->processed_at?->toIso8601String(),
        ];
    }
}
