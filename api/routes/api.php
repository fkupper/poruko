<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LedgerAccountController;
use App\Http\Controllers\Api\LedgerTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::middleware('throttle:api')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('/ledgers/{ledger}')
        ->scopeBindings()
        ->middleware('can:view,ledger')
        ->group(function (): void {
            Route::get('/accounts', [LedgerAccountController::class, 'index']);
            Route::post('/accounts', [LedgerAccountController::class, 'store']);
            Route::get('/accounts/{account}', [LedgerAccountController::class, 'show']);
            Route::patch('/accounts/{account}', [LedgerAccountController::class, 'update']);
            Route::delete('/accounts/{account}', [LedgerAccountController::class, 'destroy']);

            Route::get('/transactions', [LedgerTransactionController::class, 'index']);
            Route::post('/transactions', [LedgerTransactionController::class, 'store']);
            Route::get('/transactions/{transaction}', [LedgerTransactionController::class, 'show']);
        });
});
