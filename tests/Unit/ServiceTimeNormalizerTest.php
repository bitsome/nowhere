<?php

use App\Support\Orders\ServiceTimeNormalizer;

test('한 자리 시각을 두 자리로 맞춘다', function () {
    expect(ServiceTimeNormalizer::normalize('7:30'))->toBe('07:30')
        ->and(ServiceTimeNormalizer::normalize('7.30'))->toBe('07:30')
        ->and(ServiceTimeNormalizer::normalize('7时30'))->toBe('07:30')
        ->and(ServiceTimeNormalizer::normalize('730'))->toBe('07:30')
        ->and(ServiceTimeNormalizer::normalize('7点'))->toBe('07:00');
});

test('이미 맞는 시각은 그대로 둔다', function () {
    expect(ServiceTimeNormalizer::normalize('09:00'))->toBe('09:00')
        ->and(ServiceTimeNormalizer::normalize('23:59'))->toBe('23:59')
        ->and(ServiceTimeNormalizer::normalize(' 14:02 '))->toBe('14:02');
});

test('읽지 못한 값과 빈 값은 손대지 않는다', function () {
    expect(ServiceTimeNormalizer::normalize('오전'))->toBe('오전')
        ->and(ServiceTimeNormalizer::normalize(''))->toBe('')
        ->and(ServiceTimeNormalizer::normalize(null))->toBeNull();
});
