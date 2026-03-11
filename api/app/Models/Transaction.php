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

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ledger_id',
        'payer_account_id',
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

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function payerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payer_account_id');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class);
    }

    public function scopeForLedger(Builder $query, int $ledgerId): Builder
    {
        return $query->where('ledger_id', $ledgerId);
    }

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
