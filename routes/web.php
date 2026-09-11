<?php

use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// 메인 진입점 — 모든 요청은 독립 프론트엔드(SPA)로 안내한다.
// 비로그인 사용자에게는 간단한 안내 화면(welcome)을 보여준다.
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->away(config('app.frontend_url'));
    }

    return view('welcome');
})->name('home');

// 운행 공유 링크 — 카카오톡·SNS 미리보기용 OG 메타를 서버에서 렌더하고 SPA 공개 화면으로 넘긴다.
// (SPA는 모든 경로가 같은 정적 index.html이라 운행별 OG를 만들 수 없다)
Route::get('/s/order/{token}', [ShareController::class, 'order'])->name('share.order');

// SPA 폴백 — 프론트엔드(Vue Router)가 처리하는 라우트를 해당 index.html로 안내.
// /api 라우트는 위에서 우선 매칭되므로 충돌하지 않는다.
// index.html은 항상 최신 자산(해시 파일명)을 참조하도록 캐시 금지한다.
Route::fallback(function () {
    // 존재하지 않는 API 경로는 SPA index.html(200)로 감싸지 않고 404 JSON으로 응답한다.
    if (request()->is('api/*')) {
        return response()->json(['message' => '요청한 API를 찾을 수 없습니다.'], 404);
    }

    // /spa/* 는 Vue SPA(index.html은 public/spa/에 배치) — 루트의 Flutter 앱과 분리
    if (str_starts_with(request()->path(), 'spa/')) {
        return response(File::get(public_path('spa/index.html')), 200, [
            'Content-Type' => 'text/html',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    return response(File::get(public_path('index.html')), 200, [
        'Content-Type' => 'text/html',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});
