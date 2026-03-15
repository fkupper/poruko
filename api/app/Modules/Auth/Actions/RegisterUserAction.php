<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;

final readonly class RegisterUserAction
{
    /**
     * @param array{name:string, email:string, password:string} $data
     * @return array{user: User, token: string}
     */
    public function execute(array $data): array
    {
        $user = User::query()->create($data);
        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
