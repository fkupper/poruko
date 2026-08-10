<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Modules\Ledger\Data\CreateRecurringTransactionData;

final readonly class CreateRecurringTransactionAction
{
    public function execute(Ledger $ledger, CreateRecurringTransactionData $data): RecurringTransaction
    {
        return RecurringTransaction::query()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $data->payerAccountId,
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
