<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Modules\Ledger\Actions\AcceptInvitationAction;
use App\Modules\Ledger\Actions\CreateInvitationAction;
use App\Modules\Ledger\Data\AcceptInvitationData;
use App\Modules\Ledger\Data\CreateInvitationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function store(Request $request, Ledger $ledger, CreateInvitationAction $action): JsonResponse
    {
        Gate::authorize('users');

        $validator = Validator::make($request->all(), [
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = new CreateInvitationData(
            ledgerId: $ledger->id,
            expiresInDays: $request->input('expires_in_days', 7),
        );

        $invitation = $action->execute($request->user(), $data);

        return response()->json([
            'message' => 'Invitation created successfully.',
            'invitation' => $invitation,
        ], 201);
    }

    public function accept(Request $request, AcceptInvitationAction $action): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = new AcceptInvitationData(
            token: $request->input('token'),
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
        );

        $user = $action->execute($data);

        // Optionally, generate an API token for them so they are logged in immediately.
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Invitation accepted successfully.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }
}
