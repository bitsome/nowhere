<?php

use App\Http\Controllers\Api\ActionCenterController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminOperationController;
use App\Http\Controllers\Api\AdminSupportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BehaviorEventController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderOfferController;
use App\Http\Controllers\Api\OrderTemplateController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\StreamController;
use App\Http\Controllers\Api\SupportController;
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

// 비밀번호 찾기/재설정 — 이메일 인증코드 발송 → 코드 확인 후 변경 (로그인 없이 접근)
Route::post('/auth/password/forgot', [PasswordResetController::class, 'sendCode']);
Route::post('/auth/password/reset', [PasswordResetController::class, 'reset']);

// SSE는 EventSource가 헤더 인증을 못 하므로 ?token= 으로 직접 인증한다 (미들웨어 밖)
Route::get('/events', [StreamController::class, 'stream']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/me', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/options/orders', [OrderController::class, 'options']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markRead']);
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);
    Route::post('/verification/request', [VerificationController::class, 'request']);
    // 내 인증 현황 — 차량·면허별 최신 심사 상태
    Route::get('/verification/requests/mine', [VerificationController::class, 'myRequests']);
    // 증빙 심사(B-3) — 관리자 목록·승인/거절
    Route::get('/admin/verifications', [VerificationController::class, 'adminIndex']);
    Route::post('/admin/verifications/{verification}/review', [VerificationController::class, 'review']);
    Route::patch('/admin/users/{user}/verification', [VerificationController::class, 'update']);
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::patch('/admin/users/{user}/role', [AdminController::class, 'setUserRole']);
    Route::get('/admin/drivers', [AdminController::class, 'drivers']);
    Route::patch('/admin/drivers/{user}/status', [AdminController::class, 'updateDriverStatus']);
    Route::get('/admin/auto-order-settings', [AdminController::class, 'autoOrderSettings']);
    Route::patch('/admin/auto-order-settings', [AdminController::class, 'updateAutoOrderSettings']);
    Route::get('/admin/auto-orders', [AdminController::class, 'autoOrderHistory']);
    Route::post('/admin/auto-orders/delete', [AdminController::class, 'deleteAutoOrders']);
    // 운행별 적합 기사 자동 매칭 랭킹 (상위 N명)
    Route::get('/admin/orders/{order}/matching-drivers', [AdminController::class, 'matchingDrivers']);

    // 정산·출금 — 관리자 출금 처리 (기사별 처리 대기 목록 / 지급 / 거절)
    Route::get('/admin/payouts', [SettlementController::class, 'adminPayouts']);
    Route::post('/admin/payouts/{payout}/pay', [SettlementController::class, 'pay']);
    Route::post('/admin/payouts/{payout}/reject', [SettlementController::class, 'reject']);

    // 신고/분쟁 — 사용자 접수(옵션 포함) + 관리자 목록·처리 단계 진행
    Route::get('/reports/options', [ReportController::class, 'meta']);
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/admin/reports', [ReportController::class, 'adminIndex']);
    Route::patch('/admin/reports/{report}', [ReportController::class, 'advance']);

    // 관리자 개입(B-2) — 사용자 제재·운행 숨김/보류/강제취소·채팅 운영·정산 보류·일일 요약
    Route::get('/admin/operations/meta', [AdminOperationController::class, 'meta']);
    Route::get('/admin/operations/daily', [AdminOperationController::class, 'daily']);
    Route::get('/admin/operations/metrics', [AdminOperationController::class, 'metrics']);
    Route::get('/admin/operations/audit', [AdminOperationController::class, 'audit']);
    Route::get('/admin/operations/orders', [AdminOperationController::class, 'orders']);
    Route::patch('/admin/users/{user}/moderation', [AdminOperationController::class, 'moderateUser']);
    Route::post('/admin/orders/{order}/hide', [AdminOperationController::class, 'hide']);
    Route::post('/admin/orders/{order}/hold', [AdminOperationController::class, 'hold']);
    Route::post('/admin/orders/{order}/force-cancel', [AdminOperationController::class, 'forceCancel']);
    Route::get('/admin/operations/conversations', [AdminOperationController::class, 'conversations']);
    Route::get('/admin/conversations/{conversation}/messages', [AdminOperationController::class, 'conversationMessages']);
    Route::post('/admin/conversations/{conversation}/moderate', [AdminOperationController::class, 'moderate']);
    Route::get('/admin/operations/settlements', [AdminOperationController::class, 'settlements']);
    Route::post('/admin/settlements/{settlement}/hold', [AdminOperationController::class, 'holdSettlement']);

    // 고객지원(B-4) — 사용자 공지·FAQ 조회 + 1:1 문의 작성/내 문의
    Route::get('/support/posts', [SupportController::class, 'posts']);
    Route::post('/support/tickets', [SupportController::class, 'storeTicket']);
    Route::get('/support/tickets/mine', [SupportController::class, 'myTickets']);

    // 고객지원 관리(B-4) — 공지·FAQ 작성/수정/삭제 + 문의 답변
    Route::get('/admin/support/posts', [AdminSupportController::class, 'posts']);
    Route::post('/admin/support/posts', [AdminSupportController::class, 'store']);
    Route::patch('/admin/support/posts/{post}', [AdminSupportController::class, 'update']);
    Route::delete('/admin/support/posts/{post}', [AdminSupportController::class, 'destroy']);
    Route::get('/admin/support/tickets', [AdminSupportController::class, 'tickets']);
    Route::patch('/admin/support/tickets/{ticket}/answer', [AdminSupportController::class, 'answer']);

    // 기사 운영 — 상태/오늘 통계/차량
    Route::get('/me/driver', [DriverController::class, 'show']);
    Route::patch('/me/driver/status', [DriverController::class, 'status']);
    Route::patch('/me/driver/match', [DriverController::class, 'matchEnabled']);
    Route::get('/me/driver/stats', [DriverController::class, 'stats']);
    Route::get('/me/settlements', [DriverController::class, 'settlements']);

    // 정산·출금 — 기사 정산 화면(요약/계좌/출금 신청·내역)
    Route::get('/me/settlement', [SettlementController::class, 'summary']);
    Route::post('/me/bank-account', [SettlementController::class, 'saveAccount']);
    Route::post('/me/payouts', [SettlementController::class, 'requestPayout']);
    Route::get('/me/payouts', [SettlementController::class, 'myPayouts']);
    Route::get('/me/vehicles', [DriverController::class, 'vehicles']);
    Route::post('/me/vehicles', [DriverController::class, 'storeVehicle']);
    Route::patch('/me/vehicles/{vehicle}', [DriverController::class, 'updateVehicle']);
    Route::delete('/me/vehicles/{vehicle}', [DriverController::class, 'destroyVehicle']);

    // 자동 매칭 설정
    Route::get('/me/match-preferences', [MatchController::class, 'index']);
    Route::post('/me/match-preferences', [MatchController::class, 'store']);
    Route::patch('/me/match-preferences/{preference}', [MatchController::class, 'update']);
    Route::delete('/me/match-preferences/{preference}', [MatchController::class, 'destroy']);

    Route::get('/chats', [ChatController::class, 'index']);
    Route::post('/chats', [ChatController::class, 'store']);
    Route::get('/chats/images/archive', [ChatController::class, 'archive']);
    Route::post('/chats/images', [ChatController::class, 'uploadImage']);
    Route::delete('/chats/images/archive/{message}', [ChatController::class, 'destroyArchiveImage']);
    // 모든 대화방 안 읽은 메시지를 읽음 처리 (목록 '모두 읽음')
    Route::post('/chats/read-all', [ChatController::class, 'markAllRead']);
    Route::get('/chats/{conversation}', [ChatController::class, 'show']);
    Route::get('/chats/{conversation}/sync', [ChatController::class, 'sync']);
    Route::post('/chats/{conversation}/messages', [ChatController::class, 'send']);
    Route::delete('/chats/{conversation}/messages/{message}', [ChatController::class, 'destroy']);
    Route::post('/chats/{conversation}/requests', [ChatController::class, 'request']);
    Route::post('/chats/{conversation}/requests/{message}/resolve', [ChatController::class, 'resolve']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/return-routes', [OrderController::class, 'returnRoutes']);
    Route::get('/orders/recommendations', [OrderController::class, 'recommendations']);
    // 찜한 운행 — 마켓에서 아직 가져올 수 있는 운행만 (빠진 찜은 조회 시 자동 정리)
    Route::get('/orders/favorites', [OrderController::class, 'favorites']);
    Route::post('/orders/{order}/favorite', [OrderController::class, 'favorite']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('can:create,App\Models\Order');
    Route::post('/orders/batch', [OrderController::class, 'batchStore'])->middleware('can:create,App\Models\Order');
    Route::post('/orders/batch-settle', [OrderController::class, 'batchSettle']);
    Route::post('/orders/batch-claim', [OrderController::class, 'batchClaim']);
    Route::post('/orders/claims/summary', [OrderController::class, 'claimSummary']);
    Route::post('/orders/structure', [OrderController::class, 'structure'])->middleware('can:create,App\Models\Order');
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}', [OrderController::class, 'update'])->middleware('can:update,order');
    Route::post('/orders/{order}/claim', [OrderController::class, 'claim']);
    Route::post('/orders/{order}/claim/withdraw', [OrderController::class, 'withdrawClaim']);
    Route::post('/orders/{order}/claim/withdraw-expired', [OrderController::class, 'withdrawExpiredClaim']);
    Route::post('/orders/{order}/claim/{claim}/approve', [OrderController::class, 'approveClaim']);
    Route::post('/orders/{order}/claim/{claim}/reject', [OrderController::class, 'rejectClaim']);
    // 상세 정보 부족 시 등록자에게 더 자세한 입력 요청 (알림 발송)
    Route::post('/orders/{order}/request-details', [OrderController::class, 'requestDetails']);
    // 처리할 일(액션 센터) — 운행 관련 대기 액션 한 페이지
    Route::get('/actions', [ActionCenterController::class, 'index']);

    // 요금 제안(오퍼) — 기사 운임 제안 / 등록자 수락·거절 / 제안 받은 편지함
    Route::get('/offers/inbox', [OrderOfferController::class, 'inbox']);
    Route::get('/orders/{order}/offers', [OrderOfferController::class, 'index']);
    Route::post('/orders/{order}/offers', [OrderOfferController::class, 'store']);
    Route::post('/orders/{order}/offers/{offer}/accept', [OrderOfferController::class, 'accept']);
    Route::post('/orders/{order}/offers/{offer}/reject', [OrderOfferController::class, 'reject']);
    Route::delete('/orders/{order}/offers/{offer}', [OrderOfferController::class, 'destroy']);
    Route::post('/orders/{order}/status', [OrderController::class, 'transition'])->middleware('can:transition,order');
    Route::post('/orders/{order}/ride-step', [OrderController::class, 'advanceRideStep']);
    Route::post('/orders/{order}/duplicate', [OrderController::class, 'duplicate'])->middleware('can:create,App\Models\Order');
    Route::post('/orders/{order}/detach', [OrderController::class, 'detachFromGroup'])->middleware('can:update,order');
    Route::post('/orders/{order}/review', [ReviewController::class, 'store']);
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/order-templates', [OrderTemplateController::class, 'index']);
    Route::post('/order-templates', [OrderTemplateController::class, 'store']);
    Route::delete('/order-templates/{template}', [OrderTemplateController::class, 'destroy']);
    Route::get('/stats/orders', [StatsController::class, 'orders']);

    // 행동 이벤트(노출·클릭) 일괄 저장 — 개인화 추천 원료 (신청·거절·완료 등은 서버가 직접 기록)
    Route::post('/behavior-events', [BehaviorEventController::class, 'store']);

    Route::get('/community/posts', [CommunityController::class, 'index']);
    Route::post('/community/posts', [CommunityController::class, 'store']);
    Route::get('/community/posts/{post}', [CommunityController::class, 'show']);
    Route::get('/community/users/{user}', [CommunityController::class, 'showUser']);
    Route::post('/community/posts/{post}/like', [CommunityController::class, 'toggleLike']);
    Route::post('/community/posts/{post}/comments', [CommunityController::class, 'comment']);
    Route::delete('/community/posts/{post}', [CommunityController::class, 'destroy']);
    Route::put('/community/posts/{post}', [CommunityController::class, 'update']);
    Route::delete('/community/posts/{post}/comments/{comment}', [CommunityController::class, 'destroyComment']);
});

// 커뮤니티·채팅·증빙 이미지 — <img> 태그는 Authorization 헤더를 못 보내므로 인증 밖(공개)에서 서빙
Route::get('/community/images/{filename}', [CommunityController::class, 'image']);
Route::get('/chat/images/{filename}', [ChatController::class, 'image']);
Route::get('/verification/images/{filename}', [VerificationController::class, 'image']);
