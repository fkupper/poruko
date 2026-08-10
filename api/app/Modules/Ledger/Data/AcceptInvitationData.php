<?php

namespace App\Modules\Ledger\Data;

final readonly class AcceptInvitationData
{
    public function __construct(
        public string $token,
        public ?string $name,
        public string $email,
        public string $password,
    ) {}
}
