<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ledger_id
 * @property int $user_id
 * @property string $external_account_fingerprint
 * @property string $external_account_name
 * @property string|null $masked_identifier
 * @property string $ownership_type
 * @property int|null $account_id
 * @property int|null $suggested_account_id
 * @property-read Ledger $ledger
 * @property-read User $user
 * @property-read Account|null $account
 * @property-read Account|null $suggestedAccount
 */
class BankAccountMapping extends Model
{
    /** @use HasFactory<\Database\Factories\BankAccountMappingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'ledger_id',
        'user_id',
        'external_account_fingerprint',
        'external_account_name',
        'masked_identifier',
        'ownership_type',
        'account_id',
        'suggested_account_id',
    ];

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

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function suggestedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'suggested_account_id');
    }

}
