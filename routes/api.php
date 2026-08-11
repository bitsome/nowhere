<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — 독립 프론트엔드(SPA) 전용
|--------------------------------------------------------------------------
|
| 설계 기준: docs/API_SPLIT.md
| 인증: Laravel Sanctum Bearer 토큰
|
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// SSE는 EventSource가 헤더 인증을 못 하므로 ?token= 으로 직접 인증한다 (미들웨어 밖)
Route::get('/events', [StreamController::class, 'stream']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/me', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/options/orders', [OrderController::class, 'options']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markRead']);
    Route::post('/verification/request', [VerificationController::class, 'request']);
    Route::patch('/admin/users/{user}/verification', [VerificationController::class, 'update']);
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::get('/chats/{conversation}', [ChatController::class, 'show']);
    Route::post('/chats/{conversation}/messages', [ChatController::class, 'send']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('can:create,App\Models\Order');
    Route::post('/orders/batch', [OrderController::class, 'batchStore'])->middleware('can:create,App\Models\Order');
    Route::post('/orders/batch-settle', [OrderController::class, 'batchSettle']);
    Route::post('/orders/structure', [OrderController::class, 'structure'])->middleware('can:create,App\Models\Order');
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}', [OrderController::class, 'update'])->middleware('can:update,order');
    Route::post('/orders/{order}/claim', [OrderController::class, 'claim'])->middleware('can:create,App\Models\Order');
    Route::post('/orders/{order}/status', [OrderController::class, 'transition'])->middleware('can:transition,order');
    Route::post('/orders/{order}/duplicate', [OrderController::class, 'duplicate'])->middleware('can:create,App\Models\Order');
    Route::post('/orders/{order}/detach', [OrderController::class, 'detachFromGroup'])->middleware('can:update,order');
    Route::post('/orders/{order}/review', [ReviewController::class, 'store']);
    Route::get('/stats/orders', [StatsController::class, 'orders']);

    Route::get('/community/posts', [CommunityController::class, 'index']);
    Route::post('/community/posts', [CommunityController::class, 'store']);
    Route::get('/community/posts/{post}', [CommunityController::class, 'show']);
    Route::get('/community/users/{user}', [CommunityController::class, 'showUser']);
    Route::post('/community/posts/{post}/like', [CommunityController::class, 'toggleLike']);
    Route::post('/community/posts/{post}/comments', [CommunityController::class, 'comment']);
    Route::delete('/community/posts/{post}', [CommunityController::class, 'destroy']);
});

// 커뮤니티·채팅 이미지 — <img> 태그는 Authorization 헤더를 못 보내므로 인증 밖(공개)에서 서빙
Route::get('/community/images/{filename}', [CommunityController::class, 'image']);
Route::get('/chat/images/{filename}', [ChatController::class, 'image']);
