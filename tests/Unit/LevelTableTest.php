<?php

use App\Support\Leveling\LevelTable;

test('level table resolves levels by xp', function () {
    expect(LevelTable::resolve(0)['level'])->toBe(1);
    expect(LevelTable::resolve(0)['title'])->toBe('신입 드라이버');

    expect(LevelTable::resolve(99)['level'])->toBe(1);
    expect(LevelTable::resolve(100)['level'])->toBe(2);

    expect(LevelTable::resolve(1200)['level'])->toBe(5);
    expect(LevelTable::resolve(1200)['title'])->toBe('스타 드라이버');

    expect(LevelTable::resolve(6400)['level'])->toBe(10);
    expect(LevelTable::resolve(6400)['title'])->toBe('VIP 마스터');
    expect(LevelTable::resolve(6400)['next_xp'])->toBeNull();
});

test('level table reports progress to next level', function () {
    // Lv1(0~100)에서 xp=50 → 50%
    $info = LevelTable::resolve(50);

    expect($info['level'])->toBe(1);
    expect($info['min_xp'])->toBe(0);
    expect($info['next_xp'])->toBe(100);
    expect($info['progress'])->toBe(50.0);
});
