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
        if (config('auth.lock_registration_after_first_user') && User::count() > 0) {
            abort(403, 'Public registration is currently disabled on this instance.');
        }

        $user = User::query()->create($data);
        $token = $user->createToken('api-token', ['*'])->plainTextToken;


        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
