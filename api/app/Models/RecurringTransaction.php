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

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PREVIOUS_VERSION = 'previous_version';

    public const STATUS_DELETED = 'deleted';

    /** @var list<string> */
    protected $fillable = [
        'ledger_id',
        'payer_account_id',
        'destination_account_id',
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
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function payerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payer_account_id');
    }

    /** @return HasMany<Transaction, $this> */
    public function materializedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'source_recurring_transaction_id');
    }

    public function getStatusAttribute(): string
    {
        if ($this->trashed()) {
            return self::STATUS_DELETED;
        }

        if ($this->valid_to !== null) {
            return self::STATUS_PREVIOUS_VERSION;
        }

        return self::STATUS_ACTIVE;
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
    public function scopePreviousVersion(Builder $query): Builder
    {
        return $query->whereNotNull('valid_to');
    }

    /**
     * @param Builder<RecurringTransaction> $query
     * @return Builder<RecurringTransaction>
     */
    public function scopeDeleted(Builder $query): Builder
    {
        return $query->onlyTrashed();
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
