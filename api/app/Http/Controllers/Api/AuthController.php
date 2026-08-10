<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\TwoFactorChallengeRequest;
use App\Http\Resources\UserResource;
use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Auth\Actions\RegisterUserAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        /** @var array{name: string, email: string, password: string} $data */
        $data = $request->validated();
        $result = $action->execute($data);

        return response()->json([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        /** @var array{email: string, password: string} $credentials */
        $credentials = $request->validated();
        $result = $action->execute($credentials);

        if ($result === null) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
            'two_factor' => $result['two_factor'] ?? false,
        ]);
    }

    public function challenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $valid = false;

        $engine = app(TwoFactorAuthenticationProvider::class);

        if ($code = $request->input('code')) {
            $valid = $engine->verify(decrypt($user->two_factor_secret), $code);
        } elseif ($recoveryCode = $request->input('recovery_code')) {
            /** @var list<string> $recoveryCodes */
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];

            $matchedIndex = null;
            foreach ($recoveryCodes as $index => $storedCode) {
                if (hash_equals((string) $storedCode, (string) $recoveryCode)) {
                    $matchedIndex = $index;
                    break;
                }
            }

            $valid = $matchedIndex !== null;

            if ($valid) {
                unset($recoveryCodes[$matchedIndex]);
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
                ])->save();
            }
        }

        if (!$valid) {
            return response()->json(['message' => 'The provided two factor authentication code was invalid.'], 422);
        }

        $user->currentAccessToken()->delete();

        $token = $user->createToken('api-token', ['*'])->plainTextToken;

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserResource::make($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();

        if ($currentToken !== null) {
            $currentToken->delete();
        } else {
            $user?->tokens()->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
