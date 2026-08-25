<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 운행 전 리마인더 — 서비스 1시간 전 기사에게 출발 준비 알림
Schedule::command('rides:remind')->everyMinute()->withoutOverlapping();

// 매일 운행 자동 등록 — 설정(admin/auto-order-settings)에서 중지/시작·건수 조정 가능
Schedule::command('orders:auto-register')->dailyAt('09:00')->timezone('Asia/Seoul')->withoutOverlapping();
