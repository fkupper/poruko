<?php

namespace App\Modules\Ledger\Data;

final readonly class CreateInvitationData
{
    public function __construct(
        public int $ledgerId,
        public int $expiresInDays = 7,
    ) {}
}
