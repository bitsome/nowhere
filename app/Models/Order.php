<?php

namespace App\Models;

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
    'claimed_at',
    'user_id',
    'original_owner_id',
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
     * 오더 라이프사이클 전이 규칙.
     *
     * Create → Edit → Single/Set → Publish → Trade → Accepted → Driving → Completed → Settlement
     * Trade는 Order의 상태 변화 중 하나이며, Set은 그룹 기능만 담당한다.
     *
     * @var array<string, array<int, string>>
     */
    public const STATUS_FLOW = [
        self::STATUS_DRAFT => [self::STATUS_PUBLISHED, self::STATUS_CANCELLED],
        self::STATUS_PUBLISHED => [self::STATUS_TRADING, self::STATUS_CANCELLED],
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
            throw new InvalidArgumentException('오더 상태를 전환할 수 없는 단계입니다.');
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
            self::TYPE_GENERAL => '일반 오더',
            self::TYPE_AIRPORT => '공항 오더',
            self::TYPE_BUSINESS => '비즈니스 오더',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        ];
    }
}
