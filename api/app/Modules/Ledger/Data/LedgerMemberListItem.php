<?php

namespace App\Modules\Ledger\Data;

final readonly class LedgerMemberListItem
{
    public function __construct(
        public int $id,
        public string $name,
        public int $shareable_income,
    ) {}
}
