<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerResource;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LedgerUserPreferencesController extends Controller
{
    public function update(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'default_payment_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'default_expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::table('ledger_user')
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->update([
                'default_payment_account_id' => $validated['default_payment_account_id'] ?? null,
                'default_expense_account_id' => $validated['default_expense_account_id'] ?? null,
            ]);

        $userLedger = $user->ledgers()->where('ledgers.id', $ledger->id)->firstOrFail();

        return LedgerResource::make($userLedger)->response();
    }
}
