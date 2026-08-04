<?php

namespace App\Models;

use App\Enums\SettlementMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SettlementMode $settlement_mode
 */
class Ledger extends Model
{
    /** @use HasFactory<\Database\Factories\LedgerFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'currency',
        'settlement_mode',
        'settlement_timezone',
        'settlement_cutoff_day',
        'settlement_cutoff_time',
        'settlement_auto_execute_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settlement_auto_execute_enabled' => 'boolean',
            'settlement_mode' => SettlementMode::class,
        ];
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return BelongsToMany<User, $this, LedgerUser> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(LedgerUser::class)
            ->withPivot('role', 'main_personal_account_id')
            ->withTimestamps();
    }

    /** @return HasMany<FinancialProfile, $this> */
    public function financialProfiles(): HasMany
    {
        return $this->hasMany(FinancialProfile::class);
    }

    /** @return HasMany<Settlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    /** @return HasMany<RecurringTransaction, $this> */
    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }
}
