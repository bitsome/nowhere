<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BoardManagementController;
use App\Http\Controllers\DashboardWorkspaceController;
use App\Http\Controllers\FileManagementController;
use App\Http\Controllers\OrderManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\File;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

// 메인 진입점 — 로그인 사용자는 독립 프론트엔드(SPA)로, 비로그인은 기존 테스트 페이지로
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->away(config('app.frontend_url'));
    }

    return view('welcome', [
        'orders' => collect(),
        'orderRows' => [],
        'statusOptions' => Order::statusOptions(),
    ]);
})->name('home');

// ── 레거시 Blade (테스트·관리용) — SPA와 경로 완전 분리 ──
// SPA(Vue Router)는 /login·/profile·/dashboard 경로를 사용하므로,
// Blade 버전은 /legacy/* 하위로 격리한다 (라우트명은 유지 → 기존 참조 안전).
Route::middleware('guest')->group(function () {
    Route::get('/legacy/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/legacy/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/legacy/dashboard', [DashboardWorkspaceController::class, 'index'])->name('dashboard');
    Route::get('/legacy/market', [DashboardWorkspaceController::class, 'market'])->name('market');
    Route::get('/legacy/my-orders', [DashboardWorkspaceController::class, 'myOrders'])->name('my-orders');

    // 비즈니스 (언제든 재개발 대상) — 공통/데모와 분리
    Route::prefix('/dashboard/business')->name('dashboard.business.')->group(function () {
        Route::get('/files', [FileManagementController::class, 'index'])->name('files');
        Route::get('/files/library', [FileManagementController::class, 'library'])->name('files.library');
        Route::post('/files', [FileManagementController::class, 'store'])->name('files.store');
        Route::get('/files/{media}/download', [FileManagementController::class, 'download'])->name('files.download');
        Route::delete('/files/{media}', [FileManagementController::class, 'destroy'])->name('files.destroy');
        Route::get('/boards', [BoardManagementController::class, 'index'])->name('boards')->middleware('can:viewAny,App\Models\Board');
        Route::get('/boards/create', [BoardManagementController::class, 'create'])->name('boards.create')->middleware('can:create,App\Models\Board');
        Route::post('/boards', [BoardManagementController::class, 'store'])->name('boards.store')->middleware('can:create,App\Models\Board');
        Route::get('/boards/{board}', [BoardManagementController::class, 'show'])->name('boards.show')->middleware('can:view,board');
        Route::get('/boards/{board}/edit', [BoardManagementController::class, 'edit'])->name('boards.edit')->middleware('can:update,board');
        Route::patch('/boards/{board}', [BoardManagementController::class, 'update'])->name('boards.update')->middleware('can:update,board');
        Route::delete('/boards/{board}', [BoardManagementController::class, 'destroy'])->name('boards.destroy')->middleware('can:delete,board');
        Route::get('/users', [UserManagementController::class, 'index'])->name('users');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/permissions', [UserManagementController::class, 'permissions'])->name('users.permissions');
        Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.role.update')->middleware('can:manage,user');
        Route::patch('/users/{user}/status', [UserManagementController::class, 'updateStatus'])->name('users.status.update')->middleware('can:manage,user');
        Route::patch('/users/{user}/permissions', [UserManagementController::class, 'updatePermissions'])->name('users.permissions.update')->middleware('can:manage,user');
        Route::get('/nowhere', [DashboardWorkspaceController::class, 'nowhere'])->name('nowhere');
        Route::get('/order', [DashboardWorkspaceController::class, 'order'])->name('order');
        Route::get('/order/create', [OrderManagementController::class, 'create'])->name('order.create')->middleware('can:create,App\Models\Order');
        Route::post('/order/structure-summary', [OrderManagementController::class, 'structureSummary'])->name('order.structure')->middleware('can:create,App\Models\Order');
        Route::post('/order/store-structured', [OrderManagementController::class, 'storeStructured'])->name('order.storeStructured')->middleware('can:create,App\Models\Order');
        Route::post('/order', [OrderManagementController::class, 'store'])->name('order.store')->middleware('can:create,App\Models\Order');
        Route::get('/order/{order}/edit', [OrderManagementController::class, 'edit'])->name('order.edit');
        Route::patch('/order/{order}', [OrderManagementController::class, 'update'])->name('order.update');
        Route::post('/order/{order}/status', [OrderManagementController::class, 'transition'])->name('order.status.transition')->middleware('can:transition,order');
        Route::post('/order/{order}/claim', [OrderManagementController::class, 'claim'])->name('order.claim')->middleware('can:create,App\Models\Order');
        Route::get('/order/{order}', [OrderManagementController::class, 'show'])->name('order.show');
    });

    // 컴포넌트 데모 (안정 기준)
    Route::prefix('/dashboard/modules')->name('dashboard.modules.')->group(function () {
        Route::get('/notification', [DashboardWorkspaceController::class, 'notification'])->name('notification');
        Route::get('/dropdown', [DashboardWorkspaceController::class, 'dropdown'])->name('dropdown');
        Route::get('/tabs', [DashboardWorkspaceController::class, 'tabs'])->name('tabs');
        Route::get('/datatable', [DashboardWorkspaceController::class, 'datatable'])->name('datatable');
        Route::get('/editor', [DashboardWorkspaceController::class, 'editor'])->name('editor');
        Route::get('/dialog', [DashboardWorkspaceController::class, 'dialog'])->name('dialog');
        Route::get('/components', [DashboardWorkspaceController::class, 'components'])->name('components');
        Route::get('/buttons', [DashboardWorkspaceController::class, 'buttons'])->name('buttons');
        Route::get('/modal', [DashboardWorkspaceController::class, 'modal'])->name('modal');
        Route::get('/cards', [DashboardWorkspaceController::class, 'cards'])->name('cards');
        Route::get('/lists', [DashboardWorkspaceController::class, 'lists'])->name('lists');
        Route::get('/forms', [DashboardWorkspaceController::class, 'forms'])->name('forms');
        Route::get('/toast', [DashboardWorkspaceController::class, 'toast'])->name('toast');
        Route::get('/loading', [DashboardWorkspaceController::class, 'loading'])->name('loading');
        Route::get('/alert', [DashboardWorkspaceController::class, 'alert'])->name('alert');
    });
    Route::get('/legacy/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/legacy/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/legacy/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

// SPA 폴백 — 프론트엔드(Vue Router)가 처리하는 라우트를 index.html로 안내.
// Blade(관리자 대시보드)와 /api 라우트는 위에서 우선 매칭되므로 충돌하지 않는다.
Route::fallback(function () {
    return File::get(public_path('index.html'));
});
