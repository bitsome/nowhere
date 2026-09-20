<?php

use App\Models\OrderIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->driver = User::factory()->create(['role' => User::ROLE_DRIVER]);
});

/**
 * 지정한 시각에 들어온 것처럼 보이는 유입 기록 하나를 만든다.
 */
function ingestionAt(string $at): OrderIngestion
{
    $ingestion = OrderIngestion::query()->create([
        'endpoint' => 'orders',
        'payload' => ['original_summary' => '送机 08:00 중구'],
        'status' => OrderIngestion::STATUS_PROCESSED,
    ]);

    $ingestion->forceFill(['created_at' => $at])->save();

    return $ingestion;
}

test('최근 유입이 있으면 알리지 않는다', function () {
    ingestionAt(now()->subMinutes(30)->toDateTimeString());

    $this->artisan('orders:watch-ingestion')->assertSuccessful();

    expect($this->admin->notifications()->count())->toBe(0);
});

test('유입이 기준 시간 넘게 끊기면 관리자에게 알린다', function () {
    ingestionAt(now()->subHours(10)->toDateTimeString());

    $this->artisan('orders:watch-ingestion')->assertSuccessful();

    expect($this->admin->notifications()->where('data->title', '유입 중단 감지')->count())->toBe(1)
        ->and($this->driver->notifications()->count())->toBe(0);
});

test('기준 시간은 옵션으로 조절한다', function () {
    // 4시간 전 유입 — 기본 기준(6시간)으로는 정상이다
    ingestionAt(now()->subHours(4)->toDateTimeString());

    $this->artisan('orders:watch-ingestion')->assertSuccessful();
    expect($this->admin->notifications()->count())->toBe(0);

    $this->artisan('orders:watch-ingestion', ['--hours' => 3])->assertSuccessful();
    expect($this->admin->notifications()->where('data->title', '유입 중단 감지')->count())->toBe(1);
});

test('같은 경보는 중복 창 안에서 다시 보내지 않는다', function () {
    ingestionAt(now()->subHours(20)->toDateTimeString());

    $this->artisan('orders:watch-ingestion')->assertSuccessful();
    $this->artisan('orders:watch-ingestion')->assertSuccessful();

    expect($this->admin->notifications()->where('data->title', '유입 중단 감지')->count())->toBe(1);
});

test('유입 기록이 없으면 판단을 건너뛴다', function () {
    $this->artisan('orders:watch-ingestion')->assertSuccessful();

    expect($this->admin->notifications()->count())->toBe(0);
});
