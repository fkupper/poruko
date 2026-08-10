<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLedgerUserPreferencesRequest;
use App\Http\Resources\LedgerResource;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LedgerUserPreferencesController extends Controller
{
    public function update(UpdateLedgerUserPreferencesRequest $request, Ledger $ledger): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ledgerUser = LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->first();

        if ($ledgerUser === null) {
            throw new NotFoundHttpException('Ledger membership not found.');
        }

        $validated = $request->validated();

        $ledgerUser->update([
            'default_payment_account_id' => $validated['default_payment_account_id'] ?? null,
            'default_expense_account_id' => $validated['default_expense_account_id'] ?? null,
        ]);

        $userLedger = $user->ledgers()->where('ledgers.id', $ledger->id)->firstOrFail();

        return LedgerResource::make($userLedger)->response();
    }
}
