<?php

namespace App\Modules\Ledger\Actions;

use App\Models\RecurringTransaction;
use App\Modules\Ledger\Data\UpdateRecurringTransactionData;
use App\Modules\Ledger\Exceptions\CannotUpdateClosedRecurringTransactionException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRecurringTransactionAction
{
    /**
     * Bi-temporal edit: close current row, create new row.
     */
    public function execute(RecurringTransaction $blueprint, UpdateRecurringTransactionData $data): RecurringTransaction
    {
        if ($blueprint->valid_to !== null) {
            throw new CannotUpdateClosedRecurringTransactionException();
        }

        $today = CarbonImmutable::now()->toDateString();

        if ($blueprint->valid_from->toDateString() === $today) {
            $blueprint->update($data->toAttributes());

            return $blueprint;
        }

        $yesterday = CarbonImmutable::yesterday()->toDateString();

        return DB::transaction(function () use ($blueprint, $data, $today, $yesterday): RecurringTransaction {
            $blueprint->update(['valid_to' => $yesterday]);

            $merged = array_merge($blueprint->only([
                'ledger_id',
                'series_id',
                'payer_account_id',
                'destination_account_id',
                'amount',
                'description',
                'split_rule',
                'participants',
                'frequency',
            ]), $data->toAttributes());

            $merged['valid_from'] = $today;
            $merged['valid_to'] = null;

            return RecurringTransaction::query()->create($merged);
        });
    }
}
