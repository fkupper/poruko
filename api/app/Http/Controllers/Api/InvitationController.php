<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Ledger;
use App\Modules\Ledger\Actions\AcceptInvitationAction;
use App\Modules\Ledger\Actions\CreateInvitationAction;
use App\Modules\Ledger\Data\AcceptInvitationData;
use App\Modules\Ledger\Data\CreateInvitationData;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, Ledger $ledger, CreateInvitationAction $action): JsonResponse
    {
        $data = new CreateInvitationData(
            ledgerId: $ledger->id,
            expiresInDays: (int) $request->input('expires_in_days', 7),
            email: $request->input('email'),
        );

        $invitation = $action->execute($request->user(), $data);

        return response()->json([
            'message' => 'Invitation created successfully.',
            'invitation' => $invitation,
        ], 201);
    }

    public function accept(AcceptInvitationRequest $request, AcceptInvitationAction $action): JsonResponse
    {
        $data = new AcceptInvitationData(
            token: $request->input('token'),
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
        );

        $user = $action->execute($data);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Invitation accepted successfully.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }
}
