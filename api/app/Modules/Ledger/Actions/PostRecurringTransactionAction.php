<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Modules\Ledger\Data\MaterializeRecurringTransactionData;
use App\Modules\Ledger\Data\PostManualTransactionData;
use Illuminate\Support\Facades\DB;

final readonly class PostRecurringTransactionAction
{
    public function __construct(
        private PostManualTransactionAction $postManualTransactionAction,
    ) {}

    /**
     * Materialize a recurring blueprint into a ledger transaction.
     */
    public function execute(MaterializeRecurringTransactionData $data): Transaction
    {
        $blueprint = RecurringTransaction::query()->findOrFail($data->blueprintId);

        $splitRule = $blueprint->split_rule instanceof TransactionSplitRule
            ? $blueprint->split_rule->value
            : (string) $blueprint->split_rule;
        $participants = is_array($blueprint->participants) ? $blueprint->participants : [];

        $postData = PostManualTransactionData::fromArray([
            'ledger_id' => $blueprint->ledger_id,
            'payer_account_id' => $blueprint->payer_account_id,
            'destination_account_id' => $blueprint->destination_account_id,
            'amount' => $blueprint->amount,
            'split_rule' => $splitRule,
            'participants' => $participants,
            'description' => $blueprint->description,
            'date' => $data->periodStart,
            'type' => TransactionType::Recurring->value,
            'source' => TransactionSource::Blueprint->value,
            'source_metadata' => [
                'recurring_transaction_id' => $blueprint->id,
            ],
        ]);

        return DB::transaction(function () use ($postData, $blueprint): Transaction {
            $transaction = $this->postManualTransactionAction->execute($postData);
            $transaction->update(['source_recurring_transaction_id' => $blueprint->id]);

            return $transaction->fresh(['payerAccount', 'postings']);
        });
    }
}
