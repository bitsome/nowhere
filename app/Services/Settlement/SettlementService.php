<?php

namespace App\Services\Settlement;

use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Settlement;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\NotificationService;
use App\Support\Orders\ChineseTextNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * 정산·수금·출금 — 정산 원장 생성(수수료 계산), 등록자 대금 수금 확인, 기사 출금 신청과 관리자 지급 처리를 담당한다.
 *
 * 흐름: 운행 정산(settled) → 원장 확정(운행금액·수수료·실지급액) → 등록자 입금(수금 확인) → 기사 출금 신청 → 관리자 지급 완료.
 * 수수료율·최소 수수료·매입 계좌는 config(settlement)에서 읽고, 원장에는 정산 시점 요율을 남긴다.
 * 정상 운행은 자동 처리하되, 실제 현금 흐름(수금·지급)은 관리자가 확인하는 수동 단계로 둔다.
 */
class SettlementService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * 플랫폼 기본 수수료율 — config(settlement.fee_rate)의 비율. 운영 중 요율 조정은 이 설정만 바꾼다.
     */
    public function feeRate(): float
    {
        return max(0.0, (float) config('settlement.fee_rate', 0.05));
    }

    /**
     * 등록자에게 적용할 수수료율 — 등록자 개별 요율(users.fee_rate)이 있으면 그것을, 없으면 기본 요율을 쓴다.
     * 업체별 계약 요율을 관리자가 지정하는 지점이다.
     */
    public function feeRateFor(?User $registrant): float
    {
        $override = $registrant?->fee_rate;

        return $override !== null ? max(0.0, (float) $override) : $this->feeRate();
    }

    /**
     * 운행금액에 대한 플랫폼 수수료를 계산한다 — 요율 적용 후 최소 수수료를 보장하고,
     * 수수료가 운행금액을 넘지 않도록 보정한다(실지급액이 음수가 되지 않게).
     * 요율을 넘기지 않으면 기본 요율을 쓴다.
     */
    public function calculateFee(int $gross, ?float $rate = null): int
    {
        $fee = (int) round($gross * max(0.0, $rate ?? $this->feeRate()));
        $minFee = max(0, (int) config('settlement.min_fee', 0));

        return min($gross, max($fee, $minFee));
    }

    /**
     * 자기 수행 여부 — 등록자(원 등록자)와 수행자가 같은 사람이면 플랫폼을 통과하는 돈이 없다.
     * 자기 수행은 수금(등록자 입금)·수수료(플랫폼 매출)·지급(기사 출금)을 모두 0으로 마감한다.
     */
    private function isSelfDrive(Order $order): bool
    {
        return $order->original_owner_id !== null && $order->original_owner_id === $order->user_id;
    }

    /**
     * 정산된 운행의 원장을 만든다 (중복 방지 — 운행당 원장 1개).
     * 정산 대상 금액은 실제 수익(actual_revenue)을 우선하고 없으면 계약 금액을 쓴다.
     * 원장 생성 시 등록자에게 입금 안내, 기사에게 정산 완료를 알린다(입금 확인 후 출금 가능).
     */
    public function createFor(Order $order): ?Settlement
    {
        if (Settlement::query()->where('order_id', $order->id)->exists()) {
            return null;
        }

        // 자기 수행 — 등록자·수행자·지급 대상이 모두 같아 입금·지급이 자기 자신에게 왕복한다.
        // 그래서 수금·수수료·지급을 0으로 두고 원장을 바로 마감한다(수금 확인 목록·출금 재원에서 빠진다).
        if ($this->isSelfDrive($order)) {
            $settlement = Settlement::query()->create([
                'order_id' => $order->id,
                'driver_id' => $order->user_id,
                'registrant_id' => $order->original_owner_id,
                'gross_amount' => 0,
                'fee_amount' => 0,
                'fee_rate' => 0.0,
                'net_amount' => 0,
                'status' => Settlement::STATUS_PAID,
                'paid_at' => now(),
                'collection_status' => Settlement::COLLECTION_NOT_REQUIRED,
                'collection_note' => '자기 수행 — 수금·수수료·지급 없음',
            ]);

            $this->notifySettled($order, $settlement);

            return $settlement;
        }

        $gross = (int) ($order->actual_revenue
            ?? $order->amount_value
            ?? $order->expected_revenue
            ?? 0);

        // 등록자 개별 요율이 있으면 그 요율로 정산하고, 정산 시점 요율을 원장에 스냅샷으로 남긴다
        $rate = $this->feeRateFor($order->originalOwner);
        $fee = $this->calculateFee($gross, $rate);
        $net = max(0, $gross - $fee);

        $settlement = Settlement::query()->create([
            'order_id' => $order->id,
            'driver_id' => $order->user_id,
            'registrant_id' => $order->original_owner_id,
            'gross_amount' => $gross,
            'fee_amount' => $fee,
            'fee_rate' => $rate,
            'net_amount' => $net,
            'status' => Settlement::STATUS_PENDING,
            'collection_status' => Settlement::COLLECTION_PENDING,
        ]);

        $this->notifySettled($order, $settlement);

        return $settlement;
    }

    /**
     * 완료된 정상 운행을 자동 정산 처리한다 (백그라운드 스케줄러).
     * 정상 운행은 자동으로 흐르게 하고 문제 운행(보류 등)만 관리자가 개입하는 운영 원칙에 따른다.
     * 완료 후 유예 시간(config: settlement.auto_after_hours) 안에는 등록자가 문제를 제기하거나
     * 보류를 요청할 수 있으므로, 유예가 지난 운행만 정산 원장을 만들고 settled로 전환한다.
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

            $processed++;
        }

        return $processed;
    }

    /**
     * 정산 원장 생성 결과를 당사자에게 알린다.
     * 등록자에게는 운행 대금 입금 안내(입금 계좌 포함), 기사에게는 입금 확인 후 출금 가능함을 알린다.
     * 자기 수행이면 입금 안내 없이 마감 안내만 보낸다.
     * 같은 사용자가 등록자이자 수행 기사인 경우 한 번으로 묶는다.
     */
    private function notifySettled(Order $order, Settlement $settlement): void
    {
        $registrantId = $order->original_owner_id ?? $order->user_id;
        $driverId = $order->user_id;
        $deposit = $this->depositInstruction();

        // 자기 수행 — 수금할 것이 없으므로 입금 안내를 보내지 않는다
        if ($this->isSelfDrive($order)) {
            if ($driverId !== null) {
                $this->notifyOnceUser($driverId, '운행 정산 완료', $order->id,
                    "{$order->rideSummary()} 자기 수행 운행으로 정산되었습니다. 수금·수수료·지급 없이 마감됩니다.");
            }

            return;
        }

        if ($registrantId !== null && $registrantId === $driverId) {
            $this->notifyOnceUser($registrantId, '운행 정산 완료', $order->id,
                "{$order->rideSummary()} 운행이 정산되었습니다. 운행 대금 ".number_format($settlement->gross_amount)."원을 입금해 주세요.{$deposit}");

            return;
        }

        if ($registrantId !== null) {
            $this->notifyOnceUser($registrantId, '운행 대금 입금 안내', $order->id,
                "{$order->rideSummary()} 운행이 정산되었습니다. 운행 대금 ".number_format($settlement->gross_amount)."원을 입금해 주세요.{$deposit}");
        }

        if ($driverId !== null) {
            $this->notifyOnceUser($driverId, '정산 완료', $order->id,
                "{$order->rideSummary()} 운행 정산이 완료되었습니다. 등록자 입금 확인 후 출금 신청할 수 있습니다.");
        }
    }

    /**
     * 기사 정산 화면 요약 — 출금 가능(수금 완료된 미지급 정산 합계), 이번 달 정산, 최근 정산 내역, 등록 계좌.
     * 수금(등록자 입금)이 확인된 정산만 출금 재원이 된다.
     *
     * @return array<string, mixed>
     */
    public function summaryFor(User $driver): array
    {
        $query = Settlement::query()->where('driver_id', $driver->id)->with('order:id,pickup_location,dropoff_location,service_date,service_time');

        $pending = $query->clone()
            ->where('status', Settlement::STATUS_PENDING)
            ->where('collection_status', Settlement::COLLECTION_PAID) // 수금 완료분만 출금 가능
            ->whereNull('hold_reason') // 보류 정산은 출금 가능에서 제외 (B-2)
            ->get();

        // 정산은 됐지만 등록자 입금 전이라 아직 출금 불가한 금액 — 기사가 상황을 알도록 노출
        $awaitingCollection = $query->clone()
            ->where('status', Settlement::STATUS_PENDING)
            ->where('collection_status', Settlement::COLLECTION_PENDING)
            ->whereNull('hold_reason')
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
            // 수수료 정책 — 기사 화면에서 적용 요율을 투명하게 안내한다
            'fee_rate' => $this->feeRate(),
            'min_fee' => max(0, (int) config('settlement.min_fee', 0)),
            'pending_total' => (int) $pending->sum('net_amount'),
            'pending_count' => $pending->count(),
            'awaiting_collection_total' => (int) $awaitingCollection->sum('net_amount'),
            'awaiting_collection_count' => $awaitingCollection->count(),
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
     * 등록자 정산(청구) 화면 — 입금 대기(미수금) 운행 목록·합계와 플랫폼 매입 계좌 안내.
     *
     * @return array<string, mixed>
     */
    public function payablesFor(User $registrant): array
    {
        $query = Settlement::query()
            ->where('registrant_id', $registrant->id)
            ->with('order:id,pickup_location,dropoff_location,service_date,service_time');

        $unpaid = $query->clone()
            ->where('collection_status', Settlement::COLLECTION_PENDING)
            ->get();

        return [
            'platform_account' => config('settlement.platform_account', []),
            'unpaid_total' => (int) $unpaid->sum('gross_amount'),
            'unpaid_count' => $unpaid->count(),
            'recent' => $query->clone()->latest('id')->limit(50)->get()
                ->map(fn (Settlement $settlement) => $this->payableRow($settlement)),
        ];
    }

    /**
     * 관리자 — 입금 확인 대기(수금 전) 정산 원장 목록.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingCollections(): array
    {
        return Settlement::query()
            ->where('collection_status', Settlement::COLLECTION_PENDING)
            ->with(['registrant:id,name,company_name', 'order:id,pickup_location,dropoff_location,service_date,service_time'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Settlement $settlement) => $this->collectionRow($settlement))
            ->all();
    }

    /**
     * 관리자 — 등록자 입금을 확인해 수금을 확정한다. 수금 완료된 정산은 기사 출금 재원이 된다.
     */
    public function confirmCollection(User $admin, Settlement $settlement, ?string $note = null): void
    {
        abort_unless($settlement->collection_status === Settlement::COLLECTION_PENDING, 409, '이미 입금 확인된 정산입니다.');

        $settlement->forceFill([
            'collection_status' => Settlement::COLLECTION_PAID,
            'collected_at' => now(),
            'collected_by' => $admin->id,
            'collection_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ])->save();

        $order = $settlement->order;

        if ($order !== null) {
            $this->notifyOnceUser($settlement->registrant_id, '입금 확인 완료', $order->id,
                "{$order->rideSummary()} 운행 대금 입금이 확인되었습니다.");

            $this->notifyOnceUser($settlement->driver_id, '출금 가능', $order->id,
                "{$order->rideSummary()} 등록자 입금이 확인되어 출금 신청할 수 있습니다.");
        }
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
     * 출금 신청 — 출금 가능(수금 완료된 미지급) 정산 전부를 한 건으로 묶어 신청한다.
     * 신청 시점의 계좌 정보를 스냅샷으로 남겨 이후 계좌를 바꿔도 지급 대상은 유지된다.
     */
    public function requestPayout(User $driver): PayoutRequest
    {
        $account = $driver->bankAccount;

        abort_unless($account !== null, 422, '먼저 출금 계좌를 등록해 주세요.');

        $pending = Settlement::query()
            ->where('driver_id', $driver->id)
            ->where('status', Settlement::STATUS_PENDING)
            ->where('collection_status', Settlement::COLLECTION_PAID) // 등록자 입금 확인분만 출금 가능
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
     * 입금 안내 문구 — 매입 계좌 정보가 설정돼 있으면 계좌를, 없으면 공지 확인 안내를 붙인다.
     */
    private function depositInstruction(): string
    {
        $account = config('settlement.platform_account', []);
        $bank = $account['bank_name'] ?? '';
        $number = $account['account_number'] ?? '';
        $holder = $account['account_holder'] ?? '';

        if ($bank === '' || $number === '') {
            return ' 입금 계좌는 공지에서 확인해 주세요.';
        }

        return " 입금 계좌: {$bank} {$number}".($holder !== '' ? " ({$holder})" : '');
    }

    /**
     * 특정 사용자에게 중복 방지 알림을 보낸다.
     */
    private function notifyOnceUser(?int $userId, string $title, ?int $orderId, string $message): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return;
        }

        $this->notificationService->notifyOnce($user, $title, $orderId, $message);
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
            'collection_status' => $settlement->collection_status,
            'route' => $order !== null
                ? ChineseTextNormalizer::routeLabel($order->pickup_location, $order->dropoff_location)
                : '',
            'service_date' => $order?->service_date,
            'service_time' => $order?->service_time,
            'gross_amount' => (int) $settlement->gross_amount,
            'fee_amount' => (int) $settlement->fee_amount,
            'fee_rate' => $settlement->fee_rate !== null ? (float) $settlement->fee_rate : $this->feeRate(),
            'net_amount' => (int) $settlement->net_amount,
            'hold_reason' => $settlement->hold_reason,
            'collected_at' => $settlement->collected_at?->toIso8601String(),
            'paid_at' => $settlement->paid_at?->toIso8601String(),
            'created_at_iso' => $settlement->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payableRow(Settlement $settlement): array
    {
        $order = $settlement->order;

        return [
            'id' => $settlement->id,
            'collection_status' => $settlement->collection_status,
            'route' => $order !== null
                ? ChineseTextNormalizer::routeLabel($order->pickup_location, $order->dropoff_location)
                : '',
            'service_date' => $order?->service_date,
            'service_time' => $order?->service_time,
            'gross_amount' => (int) $settlement->gross_amount,
            'fee_amount' => (int) $settlement->fee_amount,
            'collected_at' => $settlement->collected_at?->toIso8601String(),
            'created_at_iso' => $settlement->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionRow(Settlement $settlement): array
    {
        $order = $settlement->order;
        $registrant = $settlement->registrant;

        return [
            'id' => $settlement->id,
            'registrant' => $registrant !== null
                ? ['id' => $registrant->id, 'name' => $registrant->name, 'company_name' => $registrant->company_name]
                : null,
            'route' => $order !== null
                ? ChineseTextNormalizer::routeLabel($order->pickup_location, $order->dropoff_location)
                : '',
            'service_date' => $order?->service_date,
            'service_time' => $order?->service_time,
            'gross_amount' => (int) $settlement->gross_amount,
            'fee_amount' => (int) $settlement->fee_amount,
            'net_amount' => (int) $settlement->net_amount,
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
