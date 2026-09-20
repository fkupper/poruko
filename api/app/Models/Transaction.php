<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ledger_id
 * @property int|null $source_recurring_transaction_id
 * @property int|null $settlement_id
 * @property int $payer_account_id
 * @property int|null $destination_account_id
 * @property int $amount
 * @property TransactionType $type
 * @property TransactionSource $source
 * @property array<string, mixed>|null $source_metadata
 * @property TransactionSplitRule $split_rule
 * @property array<int, array{user_id: int, share?: int|float}> $participants
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Account|null $payerAccount
 * @property-read Account|null $destinationAccount
 */
class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'ledger_id',
        'source_recurring_transaction_id',
        'settlement_id',
        'payer_account_id',
        'destination_account_id',
        'amount',
        'type',
        'source',
        'source_metadata',
        'split_rule',
        'participants',
        'description',
        'date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'type' => TransactionType::class,
            'source' => TransactionSource::class,
            'source_metadata' => 'array',
            'split_rule' => TransactionSplitRule::class,
            'participants' => 'array',
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<RecurringTransaction, $this> */
    public function sourceRecurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class, 'source_recurring_transaction_id');
    }

    /** @return BelongsTo<Settlement, $this> */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function payerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payer_account_id');
    }

    /** @return HasMany<Posting, $this> */
    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class);
    }

    /**
     * @param Builder<Transaction> $query
     * @return Builder<Transaction>
     */
    public function scopeForLedger(Builder $query, int $ledgerId): Builder
    {
        return $query->where('ledger_id', $ledgerId);
    }

    /**
     * @param Builder<Transaction> $query
     * @return Builder<Transaction>
     */
    public function scopeBetweenDates(Builder $query, ?string $fromDate, ?string $toDate): Builder
    {
        if ($fromDate !== null) {
            $query->whereDate('date', '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->whereDate('date', '<=', $toDate);
        }

        return $query;
    }
}
