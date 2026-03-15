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

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
