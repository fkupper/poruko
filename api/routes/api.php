<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FinancialProfileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LedgerAccountController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\LedgerTransactionController;
use App\Http\Controllers\Api\LedgerUserController;
use App\Http\Controllers\Api\RecurringTransactionController;
use App\Http\Controllers\Api\SettlementController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::middleware('throttle:api')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('/ledgers', [LedgerController::class, 'index'])->name('ledgers.index');

    Route::prefix('/ledgers/{ledger}')
        ->scopeBindings()
        ->middleware('can:view,ledger')
        ->name('ledgers.')
        ->group(function (): void {
            Route::get('/accounts', [LedgerAccountController::class, 'index'])->name('accounts.index');
            Route::post('/accounts', [LedgerAccountController::class, 'store'])->name('accounts.store');
            Route::get('/accounts/{account}', [LedgerAccountController::class, 'show'])->name('accounts.show');
            Route::patch('/accounts/{account}', [LedgerAccountController::class, 'update'])->name('accounts.update');
            Route::delete('/accounts/{account}', [LedgerAccountController::class, 'destroy'])->name('accounts.destroy');

            Route::get('/users', [LedgerUserController::class, 'index'])->name('users.index');

            Route::get('/transactions', [LedgerTransactionController::class, 'index'])->name('transactions.index');
            Route::post('/transactions', [LedgerTransactionController::class, 'store'])->name('transactions.store');
            Route::get('/transactions/{transaction}', [LedgerTransactionController::class, 'show'])->name('transactions.show');

            Route::get('/recurring-transactions', [RecurringTransactionController::class, 'index'])->name('recurring-transactions.index');
            Route::post('/recurring-transactions', [RecurringTransactionController::class, 'store'])->name('recurring-transactions.store');
            Route::patch('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurring-transactions.update');
            Route::delete('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurring-transactions.destroy');

            Route::get('/users/{user}/financial-profile/active', [FinancialProfileController::class, 'show'])->name('users.financial-profile.show');
            Route::put('/users/{user}/financial-profile/active', [FinancialProfileController::class, 'update'])->name('users.financial-profile.update');

            Route::get('/settlements/preview', [SettlementController::class, 'preview'])->name('settlements.preview');
            Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
            Route::post('/settlements/{cycle}/confirm', [SettlementController::class, 'confirm'])->name('settlements.confirm');
            Route::patch('/cycle-config', [SettlementController::class, 'updateCycleConfig'])->name('cycle-config.update');
        });
});
