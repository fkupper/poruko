<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Transaction;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;

final readonly class DeleteTransactionAction
{
    public function execute(Transaction $transaction): void
    {
        if ($transaction->settlement_id !== null) {
            throw new InvalidLedgerPostingException('Cannot delete a transaction that has been settled.');
        }

        $transaction->postings()->delete();
        $transaction->delete();
    }
}
