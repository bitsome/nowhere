<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 운행 용어 사전 — 사전에 없던 중국어 표기를 모아 관리자가 한국어로 매핑한다.
 */
#[Fillable(['field', 'term', 'mapped_to', 'status', 'occurrences', 'first_seen_at', 'last_seen_at', 'mapped_by', 'mapped_at'])]
class OrderTerm extends Model
{
    public const FIELD_PICKUP = 'pickup_location';

    public const FIELD_DROPOFF = 'dropoff_location';

    public const FIELD_VEHICLE = 'vehicle_type';

    public const FIELD_MODEL = 'vehicle_model';

    public const FIELD_TAG = 'tag';

    public const STATUS_PENDING = 'pending';

    public const STATUS_MAPPED = 'mapped';

    public const STATUS_IGNORED = 'ignored';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'mapped_at' => 'datetime',
        ];
    }

    /**
     * 분야 코드 → 한글 라벨.
     *
     * @return array<string, string>
     */
    public static function fieldOptions(): array
    {
        return [
            self::FIELD_PICKUP => '출발지',
            self::FIELD_DROPOFF => '도착지',
            self::FIELD_VEHICLE => '차량',
            self::FIELD_MODEL => '차종',
            self::FIELD_TAG => '태그',
        ];
    }

    /**
     * 상태 코드 → 한글 라벨.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => '미매핑',
            self::STATUS_MAPPED => '매핑 완료',
            self::STATUS_IGNORED => '무시',
        ];
    }

    public function fieldLabel(): string
    {
        return self::fieldOptions()[$this->field] ?? $this->field;
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function mappedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array
    {
        return [
            'id' => $this->id,
            'field' => $this->field,
            'field_label' => $this->fieldLabel(),
            'term' => $this->term,
            'mapped_to' => $this->mapped_to,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'occurrences' => $this->occurrences,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'mapped_by_name' => $this->mappedByUser?->name,
            'mapped_at_iso' => $this->mapped_at?->toIso8601String(),
        ];
    }
}
