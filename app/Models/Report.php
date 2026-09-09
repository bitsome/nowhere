<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 신고/분쟁 — 운행·사용자·채팅 대상 신고 접수와 관리자 처리 기록.
 * 처리 흐름: pending(접수) → reviewing(확인) → investigating(조사) → handled(처리) → completed(완료)
 */
#[Fillable(['reporter_id', 'target_type', 'target_id', 'subject_user_id', 'category', 'reason', 'status', 'note', 'processed_by', 'processed_at'])]
class Report extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public const TARGET_ORDER = 'order';

    public const TARGET_USER = 'user';

    public const TARGET_CHAT = 'chat';

    // 처리 흐름 — 앞 단계로 되돌리지 않고, 이후 단계로만 진행한다
    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_HANDLED = 'handled';

    public const STATUS_COMPLETED = 'completed';

    /**
     * @return array<string, string>
     */
    public static function targetOptions(): array
    {
        return [
            self::TARGET_ORDER => '운행',
            self::TARGET_USER => '사용자',
            self::TARGET_CHAT => '채팅',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return [
            'fee' => '요금',
            'cancel' => '취소',
            'no_show' => '노쇼',
            'address' => '주소 오류',
            'time' => '시간 오류',
            'chat' => '채팅 문제',
            'service' => '서비스 문제',
            'other' => '기타',
        ];
    }

    /**
     * 대상별로 고를 수 있는 신고 유형 — 운행에는 운행 관련, 채팅에는 채팅·기타 위주.
     *
     * @return array<string, string>
     */
    public static function categoriesFor(string $targetType): array
    {
        $all = self::categoryOptions();

        if ($targetType === self::TARGET_CHAT) {
            return array_intersect_key($all, array_flip(['chat', 'service', 'other']));
        }

        // 운행·사용자 대상 — 전 유형 허용(취소/노쇼 등 운행 문제, 서비스/기타 등 사용자 문제)
        return $all;
    }

    /**
     * 처리 흐름 단계 순서 — 현재 상태에서 '이후 단계'로만 진행할 수 있다.
     *
     * @return array<int, string>
     */
    public static function statusFlow(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_REVIEWING,
            self::STATUS_INVESTIGATING,
            self::STATUS_HANDLED,
            self::STATUS_COMPLETED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => '접수',
            self::STATUS_REVIEWING => '확인',
            self::STATUS_INVESTIGATING => '조사',
            self::STATUS_HANDLED => '처리',
            self::STATUS_COMPLETED => '완료',
        ];
    }

    /**
     * 다음 처리 단계 목록 — 현재 상태보다 뒤에 있는 상태 전부 (관리자 선택 진행).
     *
     * @return array<int, string>
     */
    public function nextStatuses(): array
    {
        $flow = self::statusFlow();
        $position = array_search($this->status, $flow, true);

        return $position === false ? [] : array_slice($flow, $position + 1);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * 신고 대상 사용자 — 조사·제재 대상 (user 대상이면 그 자신, chat이면 상대방).
     *
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
