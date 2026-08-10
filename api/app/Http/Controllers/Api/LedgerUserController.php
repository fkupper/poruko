<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerMemberResource;
use App\Models\Ledger;
use App\Modules\Ledger\Queries\LedgerMembersIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Ledger Users
 */
class LedgerUserController extends Controller
{
    /**
     * List ledger members with shareable income.
     *
     * Returns all users attached to the ledger, each with their shareable_income
     * from their active financial profile on the given date.
     *
     * @urlParam ledger integer required The ledger ID. Example: 1
     *
     * @queryParam date string Optional date (Y-m-d) for shareable_income resolution. Defaults to today. Example: 2026-03-17
     */
    public function index(Request $request, Ledger $ledger, LedgerMembersIndexQuery $query): AnonymousResourceCollection
    {
        $date = $request->query('date', now()->format('Y-m-d'));

        $members = $query->execute($ledger, $date);

        return LedgerMemberResource::collection($members);
    }

    public function resetTwoFactor(Request $request, Ledger $ledger, \App\Models\User $user, \App\Modules\Ledger\Actions\DisableUserTwoFactorAction $action): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('users');

        if (!$ledger->users()->where('users.id', $user->id)->exists()) {
            abort(404, 'User not found in this ledger.');
        }

        $action->execute($user);

        return response()->json(['message' => 'Two-factor authentication has been disabled for this user.']);
    }

    public function destroy(Request $request, Ledger $ledger, \App\Models\User $user, \App\Modules\Ledger\Actions\DeactivateLedgerUserAction $action): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('users');

        $action->execute($ledger, $user, $request->user());

        return response()->json(['message' => 'User has been deactivated.']);
    }
}
