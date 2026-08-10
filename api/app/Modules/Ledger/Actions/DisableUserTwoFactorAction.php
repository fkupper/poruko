<?php

namespace App\Modules\Ledger\Actions;

use App\Models\User;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

final readonly class DisableUserTwoFactorAction
{
    public function __construct(
        private DisableTwoFactorAuthentication $disableTwoFactor
    ) {}

    public function execute(User $user): void
    {
        ($this->disableTwoFactor)($user);

        $user->tokens()->delete();
    }
}
