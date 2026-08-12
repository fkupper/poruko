<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UiColorMode;
use App\Enums\UiTheme;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property UiTheme $theme
 * @property UiColorMode $color_mode
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read LedgerUser|null $pivot
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'theme' => 'poruko',
        'color_mode' => 'system',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'theme',
        'color_mode',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'theme' => UiTheme::class,
            'color_mode' => UiColorMode::class,
        ];
    }

    /** @return BelongsToMany<Ledger, $this, LedgerUser> */
    public function ledgers(): BelongsToMany
    {
        return $this->belongsToMany(Ledger::class)
            ->using(LedgerUser::class)
            ->withPivot('role', 'main_personal_account_id', 'default_payment_account_id', 'default_expense_account_id', 'deleted_at')
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Ledger, $this, LedgerUser> */
    public function allLedgers(): BelongsToMany
    {
        return $this->belongsToMany(Ledger::class)
            ->using(LedgerUser::class)
            ->withPivot('role', 'main_personal_account_id', 'default_payment_account_id', 'default_expense_account_id', 'deleted_at')
            ->withTimestamps();
    }

    /** @return HasMany<Account, $this> */
    public function ownedAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'owner_id');
    }

    /** @return HasMany<FinancialProfile, $this> */
    public function financialProfiles(): HasMany
    {
        return $this->hasMany(FinancialProfile::class);
    }
}
