<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 증빙 심사 요청 — 인증 서류 사진을 올리면 관리자가 승인/거절한다 (B-3).
 * - 기사(Driver): 차량(vehicle)·면허(license) → users.is_vehicle_verified / is_license_verified
 * - 등록자(Customer): 사업자등록증(business)·대표 계좌(account) → users.is_business_verified / is_account_verified (Q-4)
 */
#[Fillable(['user_id', 'type', 'image_path', 'status', 'note', 'review_note', 'reviewed_by', 'reviewed_at'])]
class VerificationRequest extends Model
{
    public const TYPE_VEHICLE = 'vehicle';

    public const TYPE_LICENSE = 'license';

    public const TYPE_BUSINESS = 'business';

    public const TYPE_ACCOUNT = 'account';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * 심사 유형 라벨.
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_VEHICLE => '차량',
            self::TYPE_LICENSE => '면허',
            self::TYPE_BUSINESS => '사업자등록증',
            self::TYPE_ACCOUNT => '대표 계좌',
        ];
    }

    /**
     * 역할별 신청 가능한 심사 유형 — 기사는 차량/면허, 등록자(업체)는 사업자등록증/대표 계좌.
     *
     * @return array<int, string>
     */
    public static function typesForRole(string $role): array
    {
        return $role === User::ROLE_CUSTOMER
            ? [self::TYPE_BUSINESS, self::TYPE_ACCOUNT]
            : [self::TYPE_VEHICLE, self::TYPE_LICENSE];
    }

    /**
     * 유형별 승인 시 반영되는 users 컬럼.
     */
    public static function verifiedColumnFor(string $type): string
    {
        return match ($type) {
            self::TYPE_LICENSE => 'is_license_verified',
            self::TYPE_BUSINESS => 'is_business_verified',
            self::TYPE_ACCOUNT => 'is_account_verified',
            default => 'is_vehicle_verified',
        };
    }

    /**
     * 심사 상태 라벨.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => '심사 대기',
            self::STATUS_APPROVED => '승인',
            self::STATUS_REJECTED => '거절',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
