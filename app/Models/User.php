<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Leveling\LevelTable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'name',
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
    'is_vip',
    'vehicle_info',
    'xp',
    'channels',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;

    public const ROLE_SUPER_ADMIN = 'Super Admin';

    public const ROLE_ADMIN = 'Admin';

    public const ROLE_OPERATOR = 'Operator';

    public const ROLE_DRIVER = 'Driver';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * @return array<string, int>
     */
    public static function roleRanks(): array
    {
        return [
            self::ROLE_DRIVER => 10,
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

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function levelEvents(): HasMany
    {
        return $this->hasMany(UserLevelEvent::class);
    }

    /**
     * XP를 부여하고 이벤트 로그를 남긴다.
     */
    public function addXp(int $xp, string $type, string $label): void
    {
        if ($xp <= 0) {
            return;
        }

        $this->increment('xp', $xp);

        $this->levelEvents()->create([
            'type' => $type,
            'label' => $label,
            'xp' => $xp,
        ]);
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
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function permissionOptions(): array
    {
        return [
            'board.view',
            'board.create',
            'board.update',
            'board.delete',
            'board.comment',
            'order.create',
            'order.status.update',
            'dispatch.assign',
        ];
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
            self::ROLE_ADMIN => [
                'board.view',
                'board.create',
                'board.update',
                'board.delete',
                'board.comment',
                'order.create',
                'order.status.update',
                'dispatch.assign',
            ],
            self::ROLE_OPERATOR => [
                'board.view',
                'board.comment',
                'order.create',
                'order.status.update',
                'dispatch.assign',
            ],
            self::ROLE_DRIVER => [
                'board.view',
            ],
            default => [
                'board.view',
            ],
        };
    }

    public function roleRank(): int
    {
        if ((int) $this->id === 1) {
            return self::roleRanks()[self::ROLE_SUPER_ADMIN];
        }

        return self::roleRanks()[$this->role ?? self::ROLE_OPERATOR] ?? 0;
    }

    public function canAssignRole(string $role): bool
    {
        if ($role === self::ROLE_SUPER_ADMIN) {
            return false;
        }

        if ((int) $this->id !== 1 && $role === self::ROLE_ADMIN) {
            return false;
        }

        return $this->roleRank() > (self::roleRanks()[$role] ?? 0);
    }

    public function canManageUser(self $targetUser): bool
    {
        if ((int) $this->id === (int) $targetUser->id) {
            return false;
        }

        if ((int) $this->id === 1) {
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
        if ((int) $this->id === 1) {
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
        if ((int) $this->id === 1) {
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
        ];
    }
}
