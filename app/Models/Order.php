<?php

namespace App\Models;

use App\Services\OrderFavoriteService;
use App\Support\Orders\ChineseTextNormalizer;
use App\Support\Orders\ServiceTimeNormalizer;
use Carbon\Carbon;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Throwable;

#[Fillable([
    'order_number',
    'share_token',
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
    'customer_phone',
    'reservation_channel',
    'passenger_count',
    'luggage_count',
    'amount_text',
    'amount_value',
    'extra_options',
    'tags',
    'pickup_location',
    'dropoff_location',
    'flight_number',
    'scheduled_at',
    'order_type',
    'estimated_duration_minutes',
    'distance_km',
    'expected_revenue',
    'status',
    'source',
    'cancel_reason',
    'is_priority',
    'auto_registered',
    'claimed_at',
    'claimant_user_id',
    'claim_lock_driver_id',
    'claim_lock_until',
    'claim_batch_id',
    'approved_at',
    'ride_step',
    'ride_step_times',
    'user_id',
    'original_owner_id',
    'started_at',
    'completed_at',
    'actual_revenue',
    'is_hidden',
    'admin_hold',
    'admin_hold_reason',
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

    // 등록 경로 — 위챗 파이프라인으로 들어온 운행과 앱에서 직접 등록한 운행을 구분한다
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_PIPELINE = 'pipeline';

    // 운행중 세부 단계 — 카드 단계 스테퍼(운행시작→픽업 도착→승객 도착→출발→이동중→도착지 도착)에 사용.
    // 최종 '완료'는 운행 완료(status=completed) 처리로 이어진다.
    public const RIDE_STEP_START = 'ride_start';

    public const RIDE_STEP_PICKUP_ARRIVED = 'pickup_arrived';

    public const RIDE_STEP_PASSENGER_ARRIVED = 'passenger_arrived';

    public const RIDE_STEP_DEPARTED = 'departed';

    public const RIDE_STEP_MOVING = 'moving';

    public const RIDE_STEP_ARRIVED = 'arrived';

    /**
     * 운행 라이프사이클 전이 규칙 — docs/ORDER_FLOW.md 가 단일 소스다.
     *
     * draft → published → acceptance_pending → accepted → driving → completed → settled
     * 거래중(trading)은 과거 데이터·데모 대응용이며 신규 전이는 만들지 않는다.
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
     * 운행 상태·운행중 단계가 바뀌는 순간 이벤트를 자동 기록한다.
     * 모든 전이 경로(claim 승인·거절, 상태 전이, 운행중 단계 진행, 취소 등)에서
     * Eloquent updated 훅으로 한 번에 남겨 빠짐없는 타임라인을 보장한다.
     */
    protected static function booted(): void
    {
        static::updated(function (Order $order) {
            // 정보 수정(시간·금액 등)은 기록하지 않고, 상태·단계 변경만 남긴다
            if (! $order->isDirty('status') && ! $order->isDirty('ride_step')) {
                return;
            }

            $events = [];

            if ($order->isDirty('status')) {
                $events[] = [
                    'event' => OrderEvent::EVENT_STATUS,
                    'from_status' => $order->getOriginal('status'),
                    'to_status' => $order->status,
                ];
            }

            if ($order->isDirty('ride_step')) {
                $events[] = [
                    'event' => OrderEvent::EVENT_RIDE_STEP,
                    'from_status' => $order->getOriginal('ride_step'),
                    'to_status' => $order->ride_step,
                ];
            }

            foreach ($events as $data) {
                // 취소 사유가 있는 상태 전이는 사유를 함께 남겨 분쟁·정산 대응 근거로 삼는다
                $note = $order->status === self::STATUS_CANCELLED && filled($order->cancel_reason)
                    ? $order->cancel_reason
                    : null;

                $order->orderEvents()->create([
                    'user_id' => auth()->id(),
                    'note' => $note,
                    ...$data,
                ]);
            }

            // 찜한 기사 안내 — 마켓에서 빠지는 상태(취소·다른 기사 배차)가 되면
            // 찜한 기사에게 알리고 찜 기록을 정리한다 (배차는 새 수행자가 아닌 사람만)
            if ($order->status === self::STATUS_CANCELLED) {
                app(OrderFavoriteService::class)->notifyUnavailable(
                    $order,
                    '찜한 운행 취소',
                    "찜해 둔 {$order->rideSummary()} 운행이 취소되어 마켓에서 내려갔습니다.",
                );
            } elseif ($order->status === self::STATUS_ACCEPTED) {
                app(OrderFavoriteService::class)->notifyUnavailable(
                    $order,
                    '찜한 운행 배차 완료',
                    "찜해 둔 {$order->rideSummary()} 운행을 다른 기사님이 가져갔습니다.",
                    $order->user_id,
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => '초안',
            self::STATUS_PUBLISHED => '공개',
            self::STATUS_TRADING => '거래중',
            self::STATUS_ACCEPTED => '예약',
            self::STATUS_DRIVING => '운행중',
            self::STATUS_COMPLETED => '완료',
            self::STATUS_SETTLED => '정산',
            self::STATUS_CANCELLED => '취소',
            self::STATUS_ACCEPTANCE_PENDING => '수락 대기',
        ];
    }

    /**
     * 운행중 세부 단계 순서 — 운행시작 → 픽업장소 도착 → 승객 도착 → 출발 → 도착지로 이동중 → 도착지 도착.
     * '완료'는 운행 완료(status=completed) 처리이므로 이 목록에 없다.
     *
     * @return array<int, string>
     */
    public static function rideStepSequence(): array
    {
        return [
            self::RIDE_STEP_START,
            self::RIDE_STEP_PICKUP_ARRIVED,
            self::RIDE_STEP_PASSENGER_ARRIVED,
            self::RIDE_STEP_DEPARTED,
            self::RIDE_STEP_MOVING,
            self::RIDE_STEP_ARRIVED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function rideStepOptions(): array
    {
        return [
            self::RIDE_STEP_START => '운행시작',
            self::RIDE_STEP_PICKUP_ARRIVED => '픽업장소 도착',
            self::RIDE_STEP_PASSENGER_ARRIVED => '승객 도착',
            self::RIDE_STEP_DEPARTED => '출발',
            self::RIDE_STEP_MOVING => '도착지로 이동중',
            self::RIDE_STEP_ARRIVED => '도착지 도착',
        ];
    }

    /**
     * 운행중 다음 단계를 반환한다 — 현재 단계의 바로 다음 단계.
     * 아직 시작 전이면 첫 단계(운행시작), 마지막 단계(도착지 도착) 다음이면 null (완료는 완료 전이가 담당).
     */
    public function nextRideStep(): ?string
    {
        $steps = self::rideStepSequence();

        if ($this->ride_step === null) {
            return $steps[0];
        }

        $index = array_search($this->ride_step, $steps, true);

        if ($index === false || $index >= count($steps) - 1) {
            return null;
        }

        return $steps[$index + 1];
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

        $attributes = ['status' => $status];

        // 배차 승인(수락) 시점 기록 — 진행중 목록의 '승인받은 시간'에 사용
        if ($status === self::STATUS_ACCEPTED && $this->approved_at === null) {
            $attributes['approved_at'] = now();
        }

        $this->update($attributes);
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
     * 외부 공유 링크용 토큰을 만든다 — 운행 id를 노출하지 않기 위한 추측 불가능한 임의 문자열.
     */
    public static function generateShareToken(): string
    {
        do {
            $token = bin2hex(random_bytes(8));
        } while (self::query()->where('share_token', $token)->exists());

        return $token;
    }

    /**
     * 공유 토큰을 반환한다. 아직 없으면 이 운행에만 발급해 저장한다.
     * 등록자가 '공유'를 누르는 시점에 지연 발급하므로 기존 운행도 그대로 쓸 수 있다.
     */
    public function ensureShareToken(): string
    {
        if (filled($this->share_token)) {
            return $this->share_token;
        }

        $this->forceFill(['share_token' => self::generateShareToken()])->save();

        return $this->share_token;
    }

    /**
     * 운행 알림 메시지용 운행 요약 — "출발지 → 도착지 (M/D(요일) HH:MM)".
     * 운행 번호 대신 출발·도착지와 날짜·시간을 보여준다.
     */
    public function rideSummary(): string
    {
        // 표시용 노선 — 유입 원문의 중국어가 알림 문구에 그대로 노출되지 않도록 변환한다
        $route = ChineseTextNormalizer::displayLocation($this->pickup_location)
            .' → '.ChineseTextNormalizer::displayLocation($this->dropoff_location);

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

    /**
     * @return HasMany<OrderEvent, $this>
     */
    public function orderEvents(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_user_id');
    }

    /**
     * 이 운행에 대한 모든 가져오기 신청 (여러 드라이버 동시 신청 가능).
     */
    public function claimRequests(): HasMany
    {
        return $this->hasMany(OrderClaim::class, 'order_id');
    }

    /**
     * 아직 처리 대기인 가져오기 신청 목록.
     */
    public function pendingClaims(): HasMany
    {
        return $this->claimRequests()->pending();
    }

    /**
     * 특정 드라이버가 이 운행에 승인 대기 중인 신청을 남겼는지.
     */
    public function hasPendingClaimBy(int $userId): bool
    {
        return $this->claimRequests()
            ->pending()
            ->where('driver_id', $userId)
            ->exists();
    }

    public function originalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_owner_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OrderGroup::class, 'group_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(OrderLineItem::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(OrderOffer::class);
    }

    /**
     * 이 운행을 찜한 기사들의 기록 — 찜 상태 변화(취소·배차·숨김) 알림에 사용한다.
     *
     * @return HasMany<OrderFavorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(OrderFavorite::class);
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
            'ride_step_times' => 'array',
            'scheduled_at' => 'datetime',
            'claimed_at' => 'datetime',
            'claim_lock_until' => 'datetime',
            'approved_at' => 'datetime',
            'estimated_duration_minutes' => 'integer',
            'is_priority' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'actual_revenue' => 'integer',
            'is_hidden' => 'boolean',
            'admin_hold' => 'boolean',
        ];
    }

    /**
     * 태그(배열) 저장 — 한글을 유니코드 이스케이프(\uXXXX) 없이 그대로 저장한다.
     * 태그 LIKE 검색(마켓 태그 검색)이 SQLite·MySQL 어디서든 한글로 매칭되도록 하기 위함.
     */
    protected function tags(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : (is_string($value) ? json_decode($value, true) : $value),
            set: fn ($value) => is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                : $value,
        );
    }

    /**
     * 운행 시각 저장 — 한 자리 시각(`7:30`)도 두 자리(`07:30`)로 맞춰 저장한다.
     *
     * 문자열 컬럼이라 마켓 노출 컷·자동 취소가 문자열로 비교하므로, 저장 시점에 자리 수를
     * 맞춰 두지 않으면 지난 운행이 목록에 남고 자동 취소에서도 빠져나간다.
     */
    protected function serviceTime(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => ServiceTimeNormalizer::normalize($value),
        );
    }

    /**
     * 공개할 수 있는 일시의 상한 (일).
     *
     * 유입 원문의 소수 금액(`12.5🌾` 등)을 날짜로 잘못 읽어 몇 달 뒤 일시로 저장된 건이 있었다.
     * 정상 유입은 오늘부터 며칠 안에 몰려 있으므로, 이 상한을 넘는 일시는 원문을 다시 봐야 한다.
     */
    public const PUBLISH_MAX_DAYS_AHEAD = 30;

    /**
     * 마켓 공개(publish) 전 필수 입력 요건을 검사한다.
     *
     * 필수는 노선(출발지·도착지)과 일시다. 유입 원문에 차종·요금·구분이 없는 경우가 많고
     * 억지로 기본값을 채우면 오정보가 되므로, 미지정은 각각 '차량 무관'·'요금 협의'·'구분 미지정'으로
     * 표기하고 공개를 허용한다. 요금 미지정 운행은 기사가 요금을 제안(offer)하는 정상 흐름으로 이어진다.
     *
     * 다만 기사가 판단할 수 없는 값은 공개하지 않는다 — 지명 사전에서 읽지 못한 표기와
     * 이미 지났거나 너무 먼 일시가 그렇다.
     *
     * @return string|null 누락·확인 항목 안내 메시지 (요건 충족 시 null)
     */
    public function publishRequirementError(): ?string
    {
        $messages = [];
        $missing = [];

        if (blank($this->pickup_location)) {
            $missing[] = '출발지';
        }

        if (blank($this->dropoff_location)) {
            $missing[] = '도착지';
        }

        // 서비스 일시 — 통합 일시 또는 (날짜+시간) 중 하나는 있어야 한다
        $hasDatetime = filled($this->service_datetime)
            || (filled($this->service_date) && filled($this->service_time));

        if (! $hasDatetime) {
            $missing[] = '서비스 일시';
        }

        if ($missing !== []) {
            $messages[] = '마켓 공개 전에 다음 항목을 입력해 주세요: '.implode(', ', $missing).'.';
        }

        // 지명 사전에서 한국어로 바뀌지 않은 값은 어느 지역인지 알 수 없어 기사가 판단할 수 없다
        $unreadable = [];

        if (filled($this->pickup_location) && ChineseTextNormalizer::displayLocation($this->pickup_location) === '미정') {
            $unreadable[] = '출발지';
        }

        if (filled($this->dropoff_location) && ChineseTextNormalizer::displayLocation($this->dropoff_location) === '미정') {
            $unreadable[] = '도착지';
        }

        if ($unreadable !== []) {
            $messages[] = '읽지 못한 지명이 있습니다. 다음 항목의 표기를 확인해 주세요: '.implode(', ', $unreadable).'.';
        }

        $datetimeError = $this->publishServiceDateError();

        if ($datetimeError !== null) {
            $messages[] = $datetimeError;
        }

        return $messages === [] ? null : implode(' ', $messages);
    }

    /**
     * 공개할 수 있는 날짜인지 본다 (KST 기준) — 지난 날짜와 상한을 넘긴 날짜는 공개하지 않는다.
     *
     * 시각까지 보지 않는 이유: 저장 시각은 KST 벽시계인데 앱은 UTC로 도는데, 같은 날 이른 시각 운행을
     * 시각으로 비교하면 하루 중 실행 시점에 따라 판정이 뒤집힌다. 지난 시각 운행은 마켓 조회에서
     * 걸러지고 자동 취소(`orders:close-published`)가 정리하므로 공개 요건은 날짜만 본다.
     */
    private function publishServiceDateError(): ?string
    {
        $date = $this->serviceDate();

        // 날짜가 없거나 형식을 읽지 못하면 위의 필수 항목 안내로 충분하다
        if ($date === null) {
            return null;
        }

        if ($date->lt(Carbon::now('Asia/Seoul')->startOfDay())) {
            return '이미 지난 날짜로는 공개할 수 없습니다. 일시를 확인해 주세요.';
        }

        if ($date->gt(Carbon::now('Asia/Seoul')->startOfDay()->addDays(self::PUBLISH_MAX_DAYS_AHEAD))) {
            return '날짜가 '.self::PUBLISH_MAX_DAYS_AHEAD.'일보다 뒤입니다. 일시를 확인해 주세요.';
        }

        return null;
    }

    /**
     * 운행 날짜 (KST, 시각 00:00) — 없거나 형식을 읽지 못하면 null.
     */
    private function serviceDate(): ?Carbon
    {
        try {
            if (filled($this->service_datetime)) {
                return Carbon::parse($this->service_datetime, 'Asia/Seoul')->startOfDay();
            }

            if (filled($this->service_date)) {
                return Carbon::parse($this->service_date, 'Asia/Seoul')->startOfDay();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
