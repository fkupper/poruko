<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Auth\Actions\RegisterUserAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function challenge(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        if (!$user->tokenCan('issue-2fa')) {
            return response()->json(['message' => 'Invalid token.'], Response::HTTP_UNAUTHORIZED);
        }

        $valid = false;

        $engine = app(\Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class);

        if ($code = $request->input('code')) {
            $valid = $engine->verify(decrypt($user->two_factor_secret), $code);
        } elseif ($recoveryCode = $request->input('recovery_code')) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            $valid = collect($recoveryCodes)->contains($recoveryCode);

            if ($valid) {
                // Remove the used recovery code
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode(collect($recoveryCodes)->reject(fn ($code) => $code === $recoveryCode)->values()->all())),
                ])->save();
            }
        }

        if (!$valid) {
            return response()->json(['message' => 'The provided two factor authentication code was invalid.'], 422);
        }

        // Delete the temporary token
        $user->currentAccessToken()->delete();

        // Issue real token
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
