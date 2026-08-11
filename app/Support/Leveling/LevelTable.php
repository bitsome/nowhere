<?php

namespace App\Support\Leveling;

/**
 * 드라이버 레벨 정의 — XP 합계에 따라 레벨이 파생된다 (레벨 다운 없음).
 *
 * 레벨은 [level, minXp, title] 형태로 구성된다.
 */
class LevelTable
{
    public const MAX_LEVEL = 10;

    /**
     * @return array<int, array{level: int, min_xp: int, title: string}>
     */
    public static function levels(): array
    {
        return [
            ['level' => 1, 'min_xp' => 0, 'title' => '신입 드라이버'],
            ['level' => 2, 'min_xp' => 100, 'title' => '일반 드라이버'],
            ['level' => 3, 'min_xp' => 300, 'title' => '인기 드라이버'],
            ['level' => 4, 'min_xp' => 600, 'title' => '베테랑'],
            ['level' => 5, 'min_xp' => 1000, 'title' => '스타 드라이버'],
            ['level' => 6, 'min_xp' => 1500, 'title' => '슈퍼 드라이버'],
            ['level' => 7, 'min_xp' => 2200, 'title' => '마스터 드라이버'],
            ['level' => 8, 'min_xp' => 3200, 'title' => '레전드'],
            ['level' => 9, 'min_xp' => 4600, 'title' => '프리미엄 레전드'],
            ['level' => 10, 'min_xp' => 6400, 'title' => 'VIP 마스터'],
        ];
    }

    /**
     * @param  int  $xp  누적 XP
     * @return array{level: int, title: string, min_xp: int, next_xp: int|null, progress: float}
     */
    public static function resolve(int $xp): array
    {
        $levels = self::levels();

        $current = $levels[0];
        $next = null;

        foreach ($levels as $index => $level) {
            if ($xp >= $level['min_xp']) {
                $current = $level;
                $next = $levels[$index + 1] ?? null;
            }
        }

        $currentStart = $current['min_xp'];
        $nextStart = $next['min_xp'] ?? $currentStart;
        $span = max(1, $nextStart - $currentStart);
        $progress = min(100.0, (($xp - $currentStart) / $span) * 100);

        return [
            'level' => $current['level'],
            'title' => $current['title'],
            'min_xp' => $currentStart,
            'next_xp' => $next !== null ? $nextStart : null,
            'progress' => round($progress, 1),
        ];
    }
}
