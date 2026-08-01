<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BoardManagementController;
use App\Http\Controllers\DashboardWorkspaceController;
use App\Http\Controllers\FileManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardWorkspaceController::class, 'index'])->name('dashboard');
    Route::prefix('/dashboard/modules')->name('dashboard.modules.')->group(function () {
        Route::get('/notification', [DashboardWorkspaceController::class, 'notification'])->name('notification');
        Route::get('/files', [FileManagementController::class, 'index'])->name('files');
        Route::get('/files/library', [FileManagementController::class, 'library'])->name('files.library');
        Route::post('/files', [FileManagementController::class, 'store'])->name('files.store');
        Route::get('/files/{media}/download', [FileManagementController::class, 'download'])->name('files.download');
        Route::delete('/files/{media}', [FileManagementController::class, 'destroy'])->name('files.destroy');
        Route::get('/boards', [BoardManagementController::class, 'index'])->name('boards');
        Route::get('/boards/create', [BoardManagementController::class, 'create'])->name('boards.create');
        Route::post('/boards', [BoardManagementController::class, 'store'])->name('boards.store');
        Route::get('/boards/{board}', [BoardManagementController::class, 'show'])->name('boards.show');
        Route::get('/boards/{board}/edit', [BoardManagementController::class, 'edit'])->name('boards.edit');
        Route::patch('/boards/{board}', [BoardManagementController::class, 'update'])->name('boards.update');
        Route::delete('/boards/{board}', [BoardManagementController::class, 'destroy'])->name('boards.destroy');
        Route::get('/users', [UserManagementController::class, 'index'])->name('users');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/permissions', [UserManagementController::class, 'permissions'])->name('users.permissions');
        Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.role.update');
        Route::patch('/users/{user}/status', [UserManagementController::class, 'updateStatus'])->name('users.status.update');
        Route::patch('/users/{user}/permissions', [UserManagementController::class, 'updatePermissions'])->name('users.permissions.update');
        Route::get('/dropdown', [DashboardWorkspaceController::class, 'dropdown'])->name('dropdown');
        Route::get('/datatable', [DashboardWorkspaceController::class, 'datatable'])->name('datatable');
        Route::get('/editor', [DashboardWorkspaceController::class, 'editor'])->name('editor');
    });
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

// Route::fallback(function () {
//     return response('Laravel 404', 404);
// });
