<?php

namespace App\Modules\Ledger\Data;

final readonly class LedgerMemberListItem
{
    public function __construct(
        public int $id,
        public string $name,
        public int $shareable_income,
        public string $email,
        public string $role,
        public bool $is_active = true,
    ) {}
}
