<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 운행 전 리마인더 — 서비스 1시간 전 기사에게 출발 준비 알림
Schedule::command('rides:remind')->everyMinute()->withoutOverlapping();

// 승인 대기 가져오기 요청 자동 만료 — 30분 안에 등록자가 승인하지 않으면 마켓으로 복귀
Schedule::command('orders:expire-claims')->everyMinute()->withoutOverlapping();

// 시작 시각 + 2시간 유예가 지난 대기 요금 제안 자동 철회 (제안 기사에게 만료 알림)
Schedule::command('orders:expire-offers')->everyTenMinutes()->withoutOverlapping();

// 시작 시각 + 2시간 유예가 지나도록 매칭되지 않은 공개 운행 자동 취소 (등록자 알림)
Schedule::command('orders:close-published')->everyTenMinutes()->withoutOverlapping();

// 완료 운행 자동 정산 — 완료 후 유예 시간(기본 24시간)이 지난 정상 운행만 처리
Schedule::command('orders:auto-settle')->everyFiveMinutes()->withoutOverlapping();

// 매일 운행 자동 등록 — 설정(admin/auto-order-settings)에서 중지/시작·건수 조정 가능
Schedule::command('orders:auto-register')->dailyAt('09:00')->timezone('Asia/Seoul')->withoutOverlapping();

// 내장 사전 소급 적용 — 사전에 추가된 표기를 이미 저장된 운행·미매핑 용어에 자동 반영
Schedule::command('orders:apply-terms')->hourly()->withoutOverlapping();

// 유입 중단 감시 — 발신 측(위챗 파서·브리지)이 멈추면 관리자에게 알린다
Schedule::command('orders:watch-ingestion')->hourly()->withoutOverlapping();
