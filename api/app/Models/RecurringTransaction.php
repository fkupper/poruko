<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionSplitRule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property TransactionSplitRule $split_rule
 * @property RecurringFrequency $frequency
 * @property CarbonInterface $valid_from
 * @property CarbonInterface|null $valid_to
 * @property array<int, array{user_id: int, share?: int|float}> $participants
 */
class RecurringTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\RecurringTransactionFactory> */
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'ledger_id',
        'credit_account_id',
        'debit_account_id',
        'amount',
        'description',
        'split_rule',
        'participants',
        'frequency',
        'valid_from',
        'valid_to',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'split_rule' => TransactionSplitRule::class,
            'participants' => 'array',
            'frequency' => RecurringFrequency::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    /** @return HasMany<Transaction, $this> */
    public function materializedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'source_recurring_transaction_id');
    }

    /**
     * @param Builder<RecurringTransaction> $query
     * @return Builder<RecurringTransaction>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('valid_to');
    }

    /**
     * @param Builder<RecurringTransaction> $query
     * @return Builder<RecurringTransaction>
     */
    public function scopeForLedger(Builder $query, int $ledgerId): Builder
    {
        return $query->where('ledger_id', $ledgerId);
    }
}
