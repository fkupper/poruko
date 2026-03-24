<?php

namespace App\Observers;

use App\Models\LedgerUser;
use App\Modules\Ledger\Actions\EnsureMainPersonalAccountForLedgerMemberAction;

class LedgerUserObserver
{
    public function __construct(
        private readonly EnsureMainPersonalAccountForLedgerMemberAction $ensureMainPersonalAccountForLedgerMember,
    ) {}

    public function created(LedgerUser $ledgerUser): void
    {
        $this->ensureMainPersonalAccountForLedgerMember->execute($ledgerUser);
    }
}
