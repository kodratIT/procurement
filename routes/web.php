<?php

use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\Auth\KeycloakController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OfficeContextController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/up', HealthController::class)
    ->withoutMiddleware([
        AddQueuedCookiesToResponse::class,
        PreventRequestForgery::class,
        StartSession::class,
        ShareErrorsFromSession::class,
    ])
    ->name('health');
Route::get('/health/ready', HealthController::class)
    ->withoutMiddleware([
        AddQueuedCookiesToResponse::class,
        PreventRequestForgery::class,
        StartSession::class,
        ShareErrorsFromSession::class,
    ])
    ->name('readiness');
Route::get('/', fn () => view('welcome'));
Route::middleware('guest')->group(function () {
    Route::get('/auth/keycloak/redirect', [KeycloakController::class, 'redirect'])->name('keycloak.redirect');
    Route::get('/auth/keycloak/callback', [KeycloakController::class, 'callback'])->name('keycloak.callback');
});
Route::post('/logout', [KeycloakController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/admin/logout', [KeycloakController::class, 'logout'])->middleware('auth')->name('filament.admin.auth.logout');
Route::get('/admin/logout', [KeycloakController::class, 'logout'])->middleware('auth');
Route::post('/office/switch', [OfficeContextController::class, 'switch'])
    ->middleware(['auth', 'active.office'])->name('office.switch');
Route::post('/office/confirm-mutation', [OfficeContextController::class, 'confirmMutation'])
    ->middleware(['auth', 'active.office'])->name('office.confirm-mutation');
Route::get('/attachments/{attachment}/download', AttachmentDownloadController::class)
    ->middleware('auth')->name('attachments.download');
