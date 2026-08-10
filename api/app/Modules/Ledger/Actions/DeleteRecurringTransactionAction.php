<?php

namespace App\Modules\Ledger\Actions;

use App\Models\RecurringTransaction;

final readonly class DeleteRecurringTransactionAction
{
    public function execute(RecurringTransaction $blueprint): void
    {
        $blueprint->delete();
    }
}
