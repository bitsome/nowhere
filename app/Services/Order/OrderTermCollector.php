<?php

namespace App\Services\Order;

use App\Models\OrderTerm;
use App\Support\Orders\ChineseTextNormalizer;

/**
 * 사전에 없어 중국어가 남은 표기를 용어 사전(order_terms)에 모은다.
 *
 * 저장을 막지 않는다 — 값을 그대로 두고 관리자가 화면에서 한국어로 매핑하도록 알려만 준다.
 */
class OrderTermCollector
{
    public function collect(string $field, ?string $value): void
    {
        foreach ($this->chineseSegments($value) as $segment) {
            $this->record($field, $segment);
        }
    }

    /**
     * 값에서 중국어가 남은 조각만 골라낸다. '明洞—回击' 처럼 이어진 값은 각각 따로 본다.
     *
     * @return array<int, string>
     */
    private function chineseSegments(?string $value): array
    {
        if ($value === null || ! ChineseTextNormalizer::containsChinese($value)) {
            return [];
        }

        $parts = preg_split('/\s*—\s*/u', $value) ?: [];

        return array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $part): bool => $part !== ''
                && ChineseTextNormalizer::containsChinese($part)
                && ! self::looksLikePhrase($part),
        ));
    }

    /**
     * 문장 조각인지 — 지명에는 올 수 없는 문자(문장부호·이모지)가 있거나 너무 길면 지명이 아니다.
     *
     * 이런 값은 용어 사전에 쌓지 않는다. 원본은 order_ingestions 에 남아 있으므로 추적은 가능하다.
     */
    private static function looksLikePhrase(string $value): bool
    {
        if (mb_strlen($value) > 12) {
            return true;
        }

        return preg_match('/[?？!！。，、,…~～·"\'“”‘’()（）\[\]【】]/u', $value) === 1
            || preg_match('/\p{So}/u', $value) === 1;
    }

    private function record(string $field, string $term): void
    {
        // 태그로 등록된 표기는 지명이 아니므로 미매핑으로 다시 쌓지 않는다
        if (ChineseTextNormalizer::isTagTerm($term)) {
            return;
        }

        $row = OrderTerm::query()->firstOrCreate(
            ['field' => $field, 'term' => $term],
            [
                'status' => OrderTerm::STATUS_PENDING,
                'occurrences' => 0,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ],
        );

        $row->increment('occurrences', 1, ['last_seen_at' => now()]);
    }
}
