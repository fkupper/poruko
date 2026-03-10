<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LedgerAccountController;
use App\Http\Controllers\Api\LedgerTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/ledgers/{ledger}/accounts', [LedgerAccountController::class, 'index']);
    Route::post('/ledgers/{ledger}/accounts', [LedgerAccountController::class, 'store']);
    Route::get('/ledgers/{ledger}/accounts/{account}', [LedgerAccountController::class, 'show']);
    Route::patch('/ledgers/{ledger}/accounts/{account}', [LedgerAccountController::class, 'update']);
    Route::delete('/ledgers/{ledger}/accounts/{account}', [LedgerAccountController::class, 'destroy']);

    Route::get('/ledgers/{ledger}/transactions', [LedgerTransactionController::class, 'index']);
    Route::post('/ledgers/{ledger}/transactions', [LedgerTransactionController::class, 'store']);
    Route::get('/ledgers/{ledger}/transactions/{transaction}', [LedgerTransactionController::class, 'show']);
});
