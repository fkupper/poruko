<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FinancialProfileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LedgerAccountController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\LedgerTransactionController;
use App\Http\Controllers\Api\LedgerUserController;
use App\Http\Controllers\Api\PendingTransactionController;
use App\Http\Controllers\Api\RecurringTransactionController;
use App\Http\Controllers\Api\SettlementController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:api')
    ->name('auth.register');

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::post('/auth/two-factor-challenge', [AuthController::class, 'challenge'])
    ->middleware(['auth:sanctum', 'ability:issue-2fa', 'throttle:two-factor'])
    ->name('auth.two-factor-challenge');

Route::post('/invitations/accept', [App\Http\Controllers\Api\InvitationController::class, 'accept'])
    ->middleware('throttle:api')
    ->name('invitations.accept');

Route::middleware(['auth:sanctum', 'ability:*', App\Http\Middleware\EnforceTwoFactor::class])->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::put('/auth/me/appearance', [AuthController::class, 'updateAppearance'])->name('auth.appearance.update');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('/currencies', [App\Http\Controllers\Api\CurrenciesController::class, 'index'])->name('currencies.index');

    Route::get('/ledgers', [LedgerController::class, 'index'])->name('ledgers.index');
    Route::post('/ledgers', [LedgerController::class, 'store'])->name('ledgers.store');

    Route::prefix('/ledgers/{ledger}')
        ->scopeBindings()
        ->middleware([
            'can:view,ledger',
            App\Http\Middleware\SetLedgerTeamId::class,
        ])
        ->name('ledgers.')
        ->group(function (): void {
            Route::put('/settings', [LedgerController::class, 'updateSettings'])->name('settings.update');
            Route::put('/my-preferences', [App\Http\Controllers\Api\LedgerUserPreferencesController::class, 'update'])->name('my-preferences.update');

            Route::get('/accounts', [LedgerAccountController::class, 'index'])->name('accounts.index');
            Route::post('/accounts', [LedgerAccountController::class, 'store'])->name('accounts.store');
            Route::get('/accounts/{account}', [LedgerAccountController::class, 'show'])->name('accounts.show');
            Route::patch('/accounts/{account}', [LedgerAccountController::class, 'update'])->name('accounts.update');
            Route::delete('/accounts/{account}', [LedgerAccountController::class, 'destroy'])->name('accounts.destroy');

            Route::get('/users', [LedgerUserController::class, 'index'])->name('users.index');
            Route::get('/roles', [App\Http\Controllers\Api\RoleController::class, 'index'])->name('roles.index');
            Route::delete('/users/{user}', [LedgerUserController::class, 'destroy'])->name('users.destroy');
            Route::post('/users/{user}/restore', [LedgerUserController::class, 'restore'])
                ->withoutScopedBindings()
                ->name('users.restore');
            Route::delete('/users/{user}/two-factor', [LedgerUserController::class, 'resetTwoFactor'])->name('users.two-factor.destroy');
            Route::post('/invitations', [App\Http\Controllers\Api\InvitationController::class, 'store'])->name('invitations.store');

            Route::get('/transactions', [LedgerTransactionController::class, 'index'])->name('transactions.index');
            Route::post('/transactions', [LedgerTransactionController::class, 'store'])->name('transactions.store');
            Route::get('/transactions/{transaction}', [LedgerTransactionController::class, 'show'])->name('transactions.show');
            Route::patch('/transactions/{transaction}', [LedgerTransactionController::class, 'update'])->name('transactions.update');
            Route::delete('/transactions/{transaction}', [LedgerTransactionController::class, 'destroy'])->name('transactions.destroy');

            Route::get('/pending-transactions', [PendingTransactionController::class, 'index'])->name('pending-transactions.index');
            Route::post('/pending-transactions/approve-batch', [PendingTransactionController::class, 'approveBatch'])->name('pending-transactions.approve-batch');
            Route::post('/pending-transactions/reject-batch', [PendingTransactionController::class, 'rejectBatch'])->name('pending-transactions.reject-batch');
            Route::post('/pending-transactions/{pendingTransaction}/approve', [PendingTransactionController::class, 'approve'])->name('pending-transactions.approve');
            Route::post('/pending-transactions/{pendingTransaction}/reject', [PendingTransactionController::class, 'reject'])->name('pending-transactions.reject');

            Route::get('/recurring-transactions', [RecurringTransactionController::class, 'index'])->name('recurring-transactions.index');
            Route::post('/recurring-transactions', [RecurringTransactionController::class, 'store'])->name('recurring-transactions.store');
            Route::patch('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurring-transactions.update');
            Route::delete('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurring-transactions.destroy');

            Route::get('/users/{user}/financial-profile/active', [FinancialProfileController::class, 'show'])->name('users.financial-profile.show');
            Route::put('/users/{user}/financial-profile/active', [FinancialProfileController::class, 'update'])->name('users.financial-profile.update');

            Route::get('/settlements/preview', [SettlementController::class, 'preview'])->name('settlements.preview');
            Route::get('/settlements/periods', [SettlementController::class, 'periods'])->name('settlements.periods');
            Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
            Route::post('/settlements/{cycle}/confirm', [SettlementController::class, 'confirm'])->name('settlements.confirm');
            Route::post('/settlements/{cycle}/transfers', [SettlementController::class, 'recordTransfer'])->name('settlements.transfers.store');
            Route::patch('/cycle-config', [SettlementController::class, 'updateCycleConfig'])->name('cycle-config.update');
        });
});
