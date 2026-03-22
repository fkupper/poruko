<?php

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Data\CreateRecurringTransactionData;
use App\Models\Ledger;
use App\Models\RecurringTransaction;

final readonly class CreateRecurringTransactionAction
{
    public function execute(Ledger $ledger, CreateRecurringTransactionData $data): RecurringTransaction
    {
        return RecurringTransaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $data->creditAccountId,
            'debit_account_id' => $data->debitAccountId,
            'amount' => $data->amount,
            'description' => $data->description,
            'split_rule' => $data->splitRule,
            'participants' => $data->participants,
            'frequency' => $data->frequency,
            'valid_from' => $data->startDate,
            'valid_to' => null,
        ]);
    }
}
