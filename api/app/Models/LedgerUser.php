<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $ledger_id
 * @property int $user_id
 * @property string $role
 * @property int $main_personal_account_id
 */
class LedgerUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'ledger_user';

    /** @var list<string> */
    protected $fillable = [
        'ledger_id',
        'user_id',
        'role',
        'main_personal_account_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'main_personal_account_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function mainPersonalAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'main_personal_account_id');
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
}
