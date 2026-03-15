<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialProfile extends Model
{
    /** @use HasFactory<\Database\Factories\FinancialProfileFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'ledger_id',
        'user_id',
        'valid_from',
        'valid_to',
        'incomes',
        'deductions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
            'incomes' => 'array',
            'deductions' => 'array',
        ];
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param Builder<FinancialProfile> $query
     * @return Builder<FinancialProfile>
     */
    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $date);
            });
    }

    /**
     * @param Builder<FinancialProfile> $query
     * @return Builder<FinancialProfile>
     */
    public function scopeForLedgerUser(Builder $query, int $ledgerId, int $userId): Builder
    {
        return $query->where('ledger_id', $ledgerId)
            ->where('user_id', $userId);
    }

    public function totalIncome(): int
    {
        return (int) collect((array) $this->incomes)->sum('amount');
    }

    public function totalDeductions(): int
    {
        return (int) collect((array) $this->deductions)->sum('amount');
    }

    public function shareableIncome(): int
    {
        return max($this->totalIncome() - $this->totalDeductions(), 0);
    }

    public function getTotalIncomeAttribute(): int
    {
        return $this->totalIncome();
    }

    public function getTotalDeductionsAttribute(): int
    {
        return $this->totalDeductions();
    }

    public function getShareableIncomeAttribute(): int
    {
        return $this->shareableIncome();
    }
}
