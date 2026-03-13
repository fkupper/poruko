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

    /**
     * @var list<string>
     */
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

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $date);
            });
    }

    public function scopeForLedgerUser(Builder $query, int $ledgerId, int $userId): Builder
    {
        return $query->where('ledger_id', $ledgerId)
            ->where('user_id', $userId);
    }

    public function totalIncome(): int
    {
        return (int) collect($this->incomes)->sum('amount');
    }

    public function totalDeductions(): int
    {
        return (int) collect($this->deductions)->sum('amount');
    }

    public function shareableIncome(): int
    {
        return max($this->totalIncome() - $this->totalDeductions(), 0);
    }
}
