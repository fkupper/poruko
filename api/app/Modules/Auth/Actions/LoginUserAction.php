<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final readonly class LoginUserAction
{
    /**
     * @param array{email:string, password:string} $credentials
     * @return array{user: User, token: string}|null
     */
    public function execute(array $credentials): ?array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user instanceof User || !Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        if ($user->two_factor_secret) {
            $token = $user->createToken('2fa-token', ['issue-2fa'])->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'two_factor' => true,
            ];
        }

        $token = $user->createToken('api-token', ['*'])->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'two_factor' => false,
        ];
    }
}
