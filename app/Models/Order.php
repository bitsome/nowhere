<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable([
    'order_number',
    'original_summary',
    'structured_payload',
    'group_id',
    'request_label',
    'service_date',
    'service_time',
    'service_datetime',
    'group_type',
    'vehicle_type',
    'service_type',
    'reservation_company',
    'customer_name',
    'reservation_channel',
    'passenger_count',
    'luggage_count',
    'amount_text',
    'amount_value',
    'extra_options',
    'pickup_location',
    'dropoff_location',
    'flight_number',
    'scheduled_at',
    'order_type',
    'estimated_duration_minutes',
    'distance_km',
    'expected_revenue',
    'status',
    'cancel_reason',
    'is_priority',
    'auto_registered',
    'claimed_at',
    'claimant_user_id',
    'user_id',
    'original_owner_id',
    'started_at',
    'completed_at',
    'actual_revenue',
])]
class Order extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_TRADING = 'trading';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DRIVING = 'driving';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_ACCEPTANCE_PENDING = 'acceptance_pending';

    /**
     * 운행 라이프사이클 전이 규칙.
     *
     * Create → Edit → Single/Set → Publish → Trade → Accepted → Driving → Completed → Settlement
     * Trade는 Order의 상태 변화 중 하나이며, Set은 그룹 기능만 담당한다.
     *
     * @var array<string, array<int, string>>
     */
    public const STATUS_FLOW = [
        self::STATUS_DRAFT => [self::STATUS_PUBLISHED, self::STATUS_CANCELLED],
        // 공개 ↔ 초안 왕복 — 등록자가 공개 후 다시 비공개(초안)로 되돌릴 수 있다.
        // 거래중(trading)은 과거 데이터 대응용으로 흐름에서 제외했다 (claim → 승인 흐름 사용).
        self::STATUS_PUBLISHED => [self::STATUS_DRAFT, self::STATUS_CANCELLED],
        // 가져오기 요청(수락 대기) — 승인·거절·철회·취소. 실제 처리 규칙은 OrderClaimService/OrderTransitionService가 담당한다.
        self::STATUS_ACCEPTANCE_PENDING => [self::STATUS_ACCEPTED, self::STATUS_PUBLISHED, self::STATUS_CANCELLED],
        self::STATUS_TRADING => [self::STATUS_ACCEPTED, self::STATUS_CANCELLED],
        self::STATUS_ACCEPTED => [self::STATUS_DRIVING, self::STATUS_CANCELLED],
        self::STATUS_DRIVING => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [self::STATUS_SETTLED],
        self::STATUS_SETTLED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const CHANNEL_ONLINE_PARTNER = 'online_partner';

    public const CHANNEL_PHONE = 'phone';

    public const CHANNEL_KAKAO = 'kakao';

    public const CHANNEL_WALK_IN = 'walk_in';

    public const TYPE_GENERAL = 'general';

    public const TYPE_AIRPORT = 'airport';

    public const TYPE_BUSINESS = 'business';

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => '초안',
            self::STATUS_PUBLISHED => '공개',
            self::STATUS_TRADING => '거래중',
            self::STATUS_ACCEPTED => '수락',
            self::STATUS_DRIVING => '운행중',
            self::STATUS_COMPLETED => '완료',
            self::STATUS_SETTLED => '정산',
            self::STATUS_CANCELLED => '취소',
            self::STATUS_ACCEPTANCE_PENDING => '수락 대기',
        ];
    }

    /**
     * 현재 상태에서 이동할 수 있는 다음 상태인지 확인한다.
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::STATUS_FLOW[$this->status] ?? [], true);
    }

    /**
     * 라이프사이클 규칙에 따라 상태를 전환한다.
     *
     * @throws InvalidArgumentException
     */
    public function transitionTo(string $status): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new InvalidArgumentException('운행 상태를 전환할 수 없는 단계입니다.');
        }

        $this->update(['status' => $status]);
    }

    /**
     * @return array<int, string>
     */
    public static function reservationCompanyOptions(): array
    {
        return [
            'KLOOK',
            'KKDAY',
            'Trip.com',
            '직접예약',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function reservationChannelOptions(): array
    {
        return [
            self::CHANNEL_ONLINE_PARTNER => '온라인 제휴',
            self::CHANNEL_PHONE => '전화 접수',
            self::CHANNEL_KAKAO => '카카오 상담',
            self::CHANNEL_WALK_IN => '직접 방문',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function orderTypeOptions(): array
    {
        return [
            self::TYPE_GENERAL => '일반 운행',
            self::TYPE_AIRPORT => '공항 운행',
            self::TYPE_BUSINESS => '비즈니스 운행',
        ];
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd').'-';
        $latestOrderNumber = self::query()
            ->where('order_number', 'like', $prefix.'%')
            ->max('order_number');

        $nextSequence = $latestOrderNumber === null
            ? 1
            : ((int) substr($latestOrderNumber, -4)) + 1;

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 운행 알림 메시지용 운행 요약 — "출발지 → 도착지 (M/D(요일) HH:MM)".
     * 운행 번호 대신 출발·도착지와 날짜·시간을 보여준다.
     */
    public function rideSummary(): string
    {
        $route = trim(($this->pickup_location ?: '').' → '.($this->dropoff_location ?: ''));

        $when = '';

        if ($this->service_date) {
            $date = Carbon::parse($this->service_date, 'Asia/Seoul');
            $weekdays = ['일', '월', '화', '수', '목', '금', '토'];
            $when = $date->format('n/j').'('.$weekdays[$date->dayOfWeek].')';
        }

        if ($this->service_time) {
            $when = trim($when.' '.$this->service_time);
        }

        return $when !== '' ? $route.' ('.$when.')' : $route;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OrderGroup::class, 'group_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(OrderLineItem::class);
    }

    #[Scope]
    protected function search(Builder $query, string $search): void
    {
        $query->where(function (Builder $orderQuery) use ($search) {
            $orderQuery
                ->where('order_number', 'like', "%{$search}%")
                ->orWhere('reservation_company', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('pickup_location', 'like', "%{$search}%")
                ->orWhere('dropoff_location', 'like', "%{$search}%")
                ->orWhere('flight_number', 'like', "%{$search}%");
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:1',
            'expected_revenue' => 'integer',
            'passenger_count' => 'integer',
            'luggage_count' => 'integer',
            'amount_value' => 'integer',
            'extra_options' => 'array',
            'structured_payload' => 'array',
            'scheduled_at' => 'datetime',
            'claimed_at' => 'datetime',
            'estimated_duration_minutes' => 'integer',
            'is_priority' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'actual_revenue' => 'integer',
        ];
    }

    /**
     * 마켓 공개(publish) 전 필수 입력 요건을 검사한다.
     * 공개된 운행은 드라이버가 바로 가져갈 수 있어야 하므로 핵심 정보는 반드시 채워야 한다.
     *
     * @return string|null 누락 항목 안내 메시지 (요건 충족 시 null)
     */
    public function publishRequirementError(): ?string
    {
        $missing = [];

        if (blank($this->pickup_location)) {
            $missing[] = '출발지';
        }

        if (blank($this->dropoff_location)) {
            $missing[] = '도착지';
        }

        if (blank($this->vehicle_type)) {
            $missing[] = '차량';
        }

        if (blank($this->service_type)) {
            $missing[] = '구분';
        }

        // 서비스 일시 — 통합 일시 또는 (날짜+시간) 중 하나는 있어야 한다
        $hasDatetime = filled($this->service_datetime)
            || (filled($this->service_date) && filled($this->service_time));

        if (! $hasDatetime) {
            $missing[] = '서비스 일시';
        }

        if (! filled($this->expected_revenue) || (int) $this->expected_revenue <= 0) {
            $missing[] = '금액';
        }

        return $missing === [] ? null : '마켓 공개 전에 다음 항목을 입력해 주세요: '.implode(', ', $missing).'.';
    }
}
