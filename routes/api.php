<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MemberController;
use Illuminate\Support\Facades\Route;

// Öffentliche Auth-Routen
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Authentifizierte Routen
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Mitglieder API
    Route::prefix('members')->group(function () {
        Route::get('/', [MemberController::class, 'index']);
        Route::post('/', [MemberController::class, 'store']);
        Route::get('/statistics', [MemberController::class, 'statistics']);
        Route::get('/{member}', [MemberController::class, 'show']);
        Route::put('/{member}', [MemberController::class, 'update']);
        Route::delete('/{member}', [MemberController::class, 'destroy']);
    });
});
