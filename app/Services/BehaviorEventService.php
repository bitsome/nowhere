<?php

namespace App\Services;

use App\Models\BehaviorEvent;
use App\Models\User;

/**
 * 행동 이벤트 로그 기록 — 개인화 추천·운영 분석의 원료를 담당한다.
 *
 * 서버가 확정 시점(신청·철회·거절·완료·취소)에 record()로 남기고,
 * 노출·클릭은 앱에서 일괄 수신해 recordMany()로 저장한다.
 */
class BehaviorEventService
{
    /**
     * 단건 행동을 기록한다.
     *
     * @param  array<string, mixed>  $meta
     */
    public function record(User $user, string $event, ?int $orderId = null, array $meta = []): void
    {
        BehaviorEvent::query()->create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'event' => $event,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    /**
     * 같은 사용자의 여러 노출·클릭을 일괄 기록한다 (저장 횟수 절감).
     *
     * @param  array<int, array{event: string, order_id?: int|null, meta?: array<string, mixed>}>  $rows
     */
    public function recordMany(User $user, array $rows): int
    {
        $now = now();

        $chunk = [];

        foreach ($rows as $row) {
            $meta = $row['meta'] ?? [];

            $chunk[] = [
                'user_id' => $user->id,
                'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : null,
                'event' => (string) $row['event'],
                // insert()는 캐스트를 거치지 않으므로 JSON 문자열로 직접 인코딩한다
                'meta' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($chunk === []) {
            return 0;
        }

        BehaviorEvent::query()->insert($chunk);

        return count($chunk);
    }
}
