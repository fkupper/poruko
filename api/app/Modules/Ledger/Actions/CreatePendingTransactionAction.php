<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\PendingTransactionStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Modules\Ledger\Data\CreatePendingTransactionData;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;

final readonly class CreatePendingTransactionAction
{
    public function execute(CreatePendingTransactionData $data): PendingTransaction
    {
        $ledger = Ledger::query()->findOrFail($data->ledgerId);

        if (!$ledger->users()->whereKey($data->userId)->exists()) {
            throw new InvalidLedgerPostingException('The proposer must be an active member of the target ledger.');
        }

        $accountIds = array_values(array_filter([
            $data->payerAccountId,
            $data->destinationAccountId,
        ]));

        if (
            $accountIds !== []
            && Account::query()
                ->where('ledger_id', $data->ledgerId)
                ->whereIn('id', $accountIds)
                ->count() !== count(array_unique($accountIds))
        ) {
            throw new InvalidLedgerPostingException('One or more suggested accounts do not belong to the target ledger.');
        }

        if (TransactionSource::tryFrom($data->source) === null) {
            throw new InvalidLedgerPostingException('The transaction source is invalid.');
        }

        if ($data->splitRule !== null && TransactionSplitRule::tryFrom($data->splitRule) === null) {
            throw new InvalidLedgerPostingException('The suggested split rule is invalid.');
        }

        if ($data->amount !== null && $data->amount <= 0) {
            throw new InvalidLedgerPostingException('The suggested amount must be greater than zero.');
        }

        if ($data->confidence !== null && ($data->confidence < 0 || $data->confidence > 1)) {
            throw new InvalidLedgerPostingException('Confidence must be between zero and one.');
        }

        return PendingTransaction::query()->create([
            'ledger_id' => $data->ledgerId,
            'user_id' => $data->userId,
            'payer_account_id' => $data->payerAccountId,
            'destination_account_id' => $data->destinationAccountId,
            'raw_data' => $data->rawData,
            'suggested_description' => $data->description,
            'suggested_amount' => $data->amount,
            'suggested_split_rule' => $data->splitRule,
            'suggested_participants' => $data->participants,
            'date' => $data->date,
            'source' => $data->source,
            'confidence' => $data->confidence,
            'rationale' => $data->rationale,
            'status' => PendingTransactionStatus::Pending,
        ]);
    }
}
