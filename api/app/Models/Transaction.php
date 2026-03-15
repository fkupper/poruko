<?php

namespace App\Models;

use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'ledger_id',
        'settlement_id',
        'credit_account_id',
        'debit_account_id',
        'amount',
        'type',
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

    /** @return BelongsTo<Settlement, $this> */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
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
