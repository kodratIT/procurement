<?php

use App\Http\Controllers\Auth\KeycloakController;
use App\Http\Controllers\OfficeContextController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::middleware('guest')->group(function () {
    Route::get('/auth/keycloak/redirect', [KeycloakController::class, 'redirect'])->name('keycloak.redirect');
    Route::get('/auth/keycloak/callback', [KeycloakController::class, 'callback'])->name('keycloak.callback');
});
Route::post('/logout', [KeycloakController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/office/switch', [OfficeContextController::class, 'switch'])
    ->middleware('auth')->name('office.switch');
