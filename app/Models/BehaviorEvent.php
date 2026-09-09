<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 행동 이벤트 로그 — 개인화 추천의 원료가 되는 기사 행동(신청·철회·거절·완료·취소)과
 * 추천/마켓 노출·클릭을 한 테이블에 담는다.
 *
 * - impression/click: 앱(홈 추천·마켓 카드)이 일괄 전송
 * - claim 계열·완료·취소: claim/상태 전이 서비스가 확정 시점에 기록
 */
#[Fillable(['user_id', 'order_id', 'event', 'meta'])]
class BehaviorEvent extends Model
{
    /** 추천·마켓 카드 노출 (앱 전송) */
    public const EVENT_IMPRESSION = 'impression';

    /** 운행 카드 클릭 (앱 전송) */
    public const EVENT_CLICK = 'click';

    /** 기사 가져오기 신청 */
    public const EVENT_CLAIM = 'claim';

    /** 기사 신청 철회 — 수동/만료 (meta.cause: manual|auto) */
    public const EVENT_CLAIM_WITHDRAWN = 'claim_withdrawn';

    /** 등록자가 기사 신청 거절 */
    public const EVENT_CLAIM_REJECTED = 'claim_rejected';

    /** 운행 완료 */
    public const EVENT_RIDE_COMPLETED = 'ride_completed';

    /** 운행 취소 */
    public const EVENT_RIDE_CANCELLED = 'ride_cancelled';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
