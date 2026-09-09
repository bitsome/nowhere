<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\OrderNotification;
use App\Support\Leveling\LevelTable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'name',
    'company_name',
    'email',
    'phone',
    'password',
    'profile_photo_path',
    'role',
    'permissions',
    'status',
    'last_login_at',
    'login_count',
    'is_vehicle_verified',
    'is_license_verified',
    'is_business_verified',
    'is_account_verified',
    'is_vip',
    'vehicle_info',
    'xp',
    'channels',
    'moderation_status',
    'moderation_note',
    'moderation_by',
    'moderation_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;

    public const ROLE_SUPER_ADMIN = 'Super Admin';

    public const ROLE_ADMIN = 'Admin';

    public const ROLE_OPERATOR = 'Operator';

    public const ROLE_DRIVER = 'Driver';

    public const ROLE_CUSTOMER = 'Customer'; // 운행 등록자(마켓 이용)

    // 역할값의 단일 소스 — DB role 컬럼·라벨·순위는 전부 이 상수를 기준으로 사용한다.
    // (마이그레이션 기본값·원시 SQL 등 리터럴 잔존은 Q-1/Q-4에서 정리 대상)

    /**
     * 관리자 기능에 접근할 수 있는 역할(Admin/Super Admin) — 단일 소스.
     *
     * @var array<int, string>
     */
    public const ADMIN_ROLES = [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN];

    // 시스템 루트 사용자 id — role과 무관하게 전체 접근(권한 상속 특례) 허용.
    public const ROOT_USER_ID = 1;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    // 관리 제재 상태(B-2) — 정상/주의/운행 제한/정지. 기사·등록자 공통 적용
    public const MODERATION_ACTIVE = 'active';

    public const MODERATION_WATCH = 'watch';

    public const MODERATION_RESTRICTED = 'restricted';

    public const MODERATION_SUSPENDED = 'suspended';

    /**
     * @return array<string, string>
     */
    public static function moderationOptions(): array
    {
        return [
            self::MODERATION_ACTIVE => '정상',
            self::MODERATION_WATCH => '주의',
            self::MODERATION_RESTRICTED => '운행 제한',
            self::MODERATION_SUSPENDED => '정지',
        ];
    }

    /**
     * 운행을 가져오기·수행할 수 있는 상태인지 — 제한/정지는 운행 활동이 막힌다.
     */
    public function canOperate(): bool
    {
        return in_array($this->moderation_status ?? self::MODERATION_ACTIVE, [
            self::MODERATION_ACTIVE,
            self::MODERATION_WATCH,
        ], true);
    }

    /**
     * @return array<string, int>
     */
    public static function roleRanks(): array
    {
        return [
            self::ROLE_DRIVER => 10,
            self::ROLE_CUSTOMER => 10,
            self::ROLE_OPERATOR => 20,
            self::ROLE_ADMIN => 30,
            self::ROLE_SUPER_ADMIN => 40,
        ];
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithMedia, Notifiable;

    /**
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * 출금 계좌 — 기사별 1개.
     *
     * @return HasOne<UserBankAccount, $this>
     */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(UserBankAccount::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * 최근 증빙 심사 요청 — 차량·면허별 현재(최신) 상태 조회용.
     *
     * @return HasMany<VerificationRequest, $this>
     */
    public function latestVerificationRequests(): HasMany
    {
        return $this->verificationRequests()->orderByDesc('id');
    }

    public function matchPreferences(): HasMany
    {
        return $this->hasMany(MatchPreference::class);
    }

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function orderTemplates(): HasMany
    {
        return $this->hasMany(OrderTemplate::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function levelEvents(): HasMany
    {
        return $this->hasMany(UserLevelEvent::class);
    }

    /**
     * XP를 부여하고 이벤트 로그를 남긴다. 레벨이 올랐으면 알림으로 알려준다.
     */
    public function addXp(int $xp, string $type, string $label): void
    {
        if ($xp <= 0) {
            return;
        }

        $before = LevelTable::resolve((int) $this->xp)['level'];

        $this->increment('xp', $xp);
        $this->refresh();

        $this->levelEvents()->create([
            'type' => $type,
            'label' => $label,
            'xp' => $xp,
        ]);

        $after = LevelTable::resolve((int) $this->xp)['level'];

        if ($after > $before) {
            $info = $this->levelInfo();
            $this->notify(new OrderNotification(
                '레벨 업!',
                "축하합니다! {$info['title']} (Lv.{$info['level']})에 도달했습니다.",
                null,
            ));
        }
    }

    /**
     * 현재 레벨 정보 (XP → 레벨/타이틀/진행률).
     *
     * @return array{level: int, title: string, min_xp: int, next_xp: int|null, progress: float}
     */
    public function levelInfo(): array
    {
        return LevelTable::resolve((int) $this->xp);
    }

    /**
     * @return array<int, string>
     */
    public static function roleOptions(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_OPERATOR,
            self::ROLE_DRIVER,
            self::ROLE_CUSTOMER,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function permissionOptions(): array
    {
        return [
            'order.create',
            'order.status.update',
            'dispatch.assign',
        ];
    }

    /**
     * 역할 한글 라벨 — UI·알림·관리 화면 공통 표기 (단일 소스).
     *
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_SUPER_ADMIN => '최고 관리자',
            self::ROLE_ADMIN => '관리자',
            self::ROLE_OPERATOR => '운영자',
            self::ROLE_DRIVER => '기사',
            self::ROLE_CUSTOMER => '등록자',
        ];
    }

    public static function roleLabel(string $role): string
    {
        return self::roleLabels()[$role] ?? $role;
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => '활성',
            self::STATUS_INACTIVE => '비활성',
            self::STATUS_SUSPENDED => '정지',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function defaultPermissionsForRole(string $role): array
    {
        return match ($role) {
            self::ROLE_SUPER_ADMIN => self::permissionOptions(),
            self::ROLE_ADMIN => self::permissionOptions(),
            self::ROLE_OPERATOR => [
                'order.create',
                'order.status.update',
                'dispatch.assign',
            ],
            // 기사 — 운행 수행. 등록·관리는 못 하지만 가져오기(claim)·제안은 role 규칙으로 허용
            self::ROLE_DRIVER => [],
            // 등록자(사업자) — 운행 등록·관리는 가능하지만 가져오기(claim)·기사 운행은 불가
            self::ROLE_CUSTOMER => [
                'order.create',
            ],
            default => [],
        };
    }

    /**
     * 시스템 루트 사용자 — role과 무관하게 전체 접근(Super Admin 특례 상위).
     */
    public function isRootUser(): bool
    {
        return (int) $this->id === self::ROOT_USER_ID;
    }

    public function roleRank(): int
    {
        if ($this->isRootUser()) {
            return self::roleRanks()[self::ROLE_SUPER_ADMIN];
        }

        return self::roleRanks()[$this->role ?? self::ROLE_OPERATOR] ?? 0;
    }

    public function canAssignRole(string $role): bool
    {
        if ($role === self::ROLE_SUPER_ADMIN) {
            return false;
        }

        if (! $this->isRootUser() && $role === self::ROLE_ADMIN) {
            return false;
        }

        return $this->roleRank() > (self::roleRanks()[$role] ?? 0);
    }

    public function canManageUser(self $targetUser): bool
    {
        if ((int) $this->id === (int) $targetUser->id) {
            return false;
        }

        if ($this->isRootUser()) {
            return true;
        }

        return $this->roleRank() > $targetUser->roleRank();
    }

    /**
     * @return array<int, string>
     */
    public function assignableRoles(): array
    {
        return array_values(array_filter(self::roleOptions(), function ($role) {
            return $this->canAssignRole($role);
        }));
    }

    /**
     * @return array<int, string>
     */
    public function assignablePermissions(): array
    {
        if ($this->isRootUser()) {
            return self::permissionOptions();
        }

        return array_values(array_intersect(
            self::permissionOptions(),
            $this->resolvedPermissions(),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function resolvedPermissions(): array
    {
        if (is_array($this->permissions) && count($this->permissions) > 0) {
            return $this->permissions;
        }

        return self::defaultPermissionsForRole($this->role ?? self::ROLE_OPERATOR);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isRootUser()) {
            return true;
        }

        return in_array($permission, $this->resolvedPermissions(), true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file-manager');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 320, 240)
            ->performOnCollections('file-manager')
            ->nonQueued();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_vehicle_verified' => 'boolean',
            'is_license_verified' => 'boolean',
            'is_business_verified' => 'boolean',
            'is_account_verified' => 'boolean',
        ];
    }
}
